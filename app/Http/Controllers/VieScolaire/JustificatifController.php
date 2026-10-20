<?php

namespace App\Http\Controllers\VieScolaire;

use App\Enums\StatutPresence;
use App\Http\Controllers\Controller;
use App\Models\Classe;
use App\Models\Inscription;
use App\Models\Justificatif;
use App\Models\Presence;
use App\Services\JustificatifService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class JustificatifController extends Controller
{
    public function __construct(private JustificatifService $service) {}

    public function index(Request $request): View
    {
        $annee = annee_active();
        $classes = $annee ? Classe::query()->deLAnneeActive()->get() : collect();

        $justificatifs = Justificatif::query()
            ->with(['inscription.eleve', 'inscription.classe', 'auteur'])
            ->withCount('presences')
            ->whereHas('inscription', fn (Builder $q) => $q->where('annee_scolaire_id', $annee?->id)
                ->when($request->filled('classe'), fn (Builder $c) => $c->where('classe_id', $request->integer('classe'))))
            ->when($request->filled('q'), fn (Builder $q) => $q->whereHas('inscription.eleve', fn (Builder $e) => $e->where(fn (Builder $w) => $w
                ->where('nom', 'like', '%'.$request->string('q').'%')->orWhere('prenoms', 'like', '%'.$request->string('q').'%'))))
            ->latest()
            ->paginate(20)->withQueryString();

        // Absences non justifiées récentes de l'année : la liste de travail de la vie scolaire.
        $aJustifier = Presence::query()
            ->with(['appel', 'inscription.eleve', 'inscription.classe'])
            ->where('statut', StatutPresence::Absent)
            ->whereHas('inscription', fn (Builder $q) => $q->where('annee_scolaire_id', $annee?->id)
                ->when($request->filled('classe'), fn (Builder $c) => $c->where('classe_id', $request->integer('classe'))))
            ->join('appels', 'appels.id', '=', 'presences.appel_id')
            ->orderByDesc('appels.date_appel')->select('presences.*')
            ->limit(30)->get();

        return view('vie-scolaire.justificatifs.index', compact('justificatifs', 'aJustifier', 'classes'));
    }

    public function create(Request $request): View
    {
        $classes = Classe::query()->deLAnneeActive()->get();
        $classe = $classes->firstWhere('id', $request->integer('classe'));
        $inscription = $request->filled('inscription') ? Inscription::with('eleve')->find($request->integer('inscription')) : null;
        $classe ??= $inscription?->classe;

        return view('vie-scolaire.justificatifs.create', [
            'classes' => $classes,
            'classe' => $classe,
            'inscription' => $inscription,
            'eleves' => $classe ? $classe->inscriptions()->with('eleve')->get()->sortBy('eleve.nom_complet')
                ->mapWithKeys(fn ($i) => [$i->id => $i->eleve->nom_complet]) : collect(),
            'date' => $request->input('date'),
            'tailleMaxKo' => (int) etablissement()->parametre('taille_max_fichier_ko', 5120),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $annee = annee_active();
        $tailleMax = (int) etablissement()->parametre('taille_max_fichier_ko', 5120);
        $donnees = $request->validate([
            'inscription_id' => ['required', Rule::exists('inscriptions', 'id')->where('annee_scolaire_id', $annee?->id)],
            'date_debut' => ['required', 'date'],
            'date_fin' => ['required', 'date', 'after_or_equal:date_debut'],
            'motif' => ['required', 'string', 'max:255'],
            'fichier' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', "max:{$tailleMax}"],
        ], [], ['inscription_id' => 'élève', 'date_debut' => 'date de début', 'date_fin' => 'date de fin', 'fichier' => 'pièce justificative']);

        $inscription = Inscription::with('eleve')->findOrFail($donnees['inscription_id']);
        [, $excusees] = $this->service->enregistrer($inscription, $donnees['date_debut'], $donnees['date_fin'], $donnees['motif'], $request->file('fichier'));

        return redirect()->route('justificatifs.index')
            ->with('succes', "Justificatif enregistré pour {$inscription->eleve->nom_complet} : {$excusees} absence(s) excusée(s).");
    }

    public function fichier(Justificatif $justificatif): Response
    {
        abort_unless($justificatif->fichier_chemin && Storage::disk('local')->exists($justificatif->fichier_chemin), 404);

        return Storage::disk('local')->download($justificatif->fichier_chemin, 'justificatif-'.$justificatif->id.'.'.pathinfo($justificatif->fichier_chemin, PATHINFO_EXTENSION));
    }

    public function destroy(Justificatif $justificatif): RedirectResponse
    {
        $retablies = $this->service->supprimer($justificatif);

        return back()->with('succes', "Justificatif supprimé : {$retablies} absence(s) redevenue(s) non justifiée(s).");
    }
}
