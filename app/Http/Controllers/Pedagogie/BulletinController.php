<?php

namespace App\Http\Controllers\Pedagogie;

use App\Enums\StatutEvaluation;
use App\Http\Controllers\Controller;
use App\Models\AnneeScolaire;
use App\Models\Appreciation;
use App\Models\Bulletin;
use App\Models\Classe;
use App\Models\Evaluation;
use App\Models\Periode;
use App\Models\Publication;
use App\Services\BulletinService;
use App\Services\MoyenneService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class BulletinController extends Controller
{
    public function __construct(private BulletinService $service) {}

    /**
     * Tableau classes × périodes : état de publication et évaluations restant à valider.
     */
    public function index(Request $request): View
    {
        $annees = AnneeScolaire::query()->orderByDesc('date_debut')->get();
        $annee = $annees->firstWhere('id', $request->integer('annee')) ?? $annees->firstWhere('est_active', true) ?? $annees->first();
        $classes = $annee ? Classe::query()->where('annee_scolaire_id', $annee->id)->with('niveau')
            ->join('niveaux', 'niveaux.id', '=', 'classes.niveau_id')->orderBy('niveaux.ordre')->orderBy('classes.nom')->select('classes.*')->get() : collect();
        $periodes = $annee?->periodes ?? collect();

        $publications = Publication::query()->whereIn('classe_id', $classes->pluck('id'))->get()
            ->keyBy(fn ($p) => $p->classe_id.'-'.$p->periode_id);
        $brouillons = Evaluation::query()->join('affectations', 'affectations.id', '=', 'evaluations.affectation_id')
            ->whereIn('affectations.classe_id', $classes->pluck('id'))->where('evaluations.statut', StatutEvaluation::Brouillon)
            ->selectRaw('affectations.classe_id, evaluations.periode_id, COUNT(*) as total')->groupBy('affectations.classe_id', 'evaluations.periode_id')
            ->get()->keyBy(fn ($b) => $b->classe_id.'-'.$b->periode_id);

        return view('pedagogie.bulletins.index', compact('annees', 'annee', 'classes', 'periodes', 'publications', 'brouillons'));
    }

    /**
     * Bulletins d'une classe pour une période : figés s'ils sont publiés, sinon aperçu calculé (évaluations validées).
     */
    public function classe(Classe $classe, Periode $periode, MoyenneService $moyennes): View
    {
        $this->verifierPeriode($classe, $periode);
        $classe->load(['anneeScolaire', 'professeurPrincipal.user']);
        $publication = $this->service->publication($classe, $periode);
        $publiee = $publication && ! $publication->depubliee_at;

        return view('pedagogie.bulletins.classe', [
            'classe' => $classe,
            'periode' => $periode,
            'publication' => $publication?->load('auteur'),
            'publiee' => $publiee,
            'bulletins' => Bulletin::query()->where('periode_id', $periode->id)
                ->whereHas('inscription', fn ($q) => $q->where('classe_id', $classe->id))
                ->with('inscription.eleve')->get()->sortBy(fn ($b) => [$b->rang ?? PHP_INT_MAX, $b->inscription->eleve->nom_complet]),
            'apercu' => $publiee ? null : $moyennes->resultatsClasse($classe, $periode, seulementValidees: true),
            'peutPublier' => request()->user()->can('bulletins.publier'),
            'peutApprecier' => $this->peutApprecier($classe),
            'nbAppreciations' => Appreciation::query()->where('periode_id', $periode->id)
                ->whereHas('inscription', fn ($q) => $q->where('classe_id', $classe->id))->count(),
        ]);
    }

    public function appreciations(Classe $classe, Periode $periode, MoyenneService $moyennes): View
    {
        $this->verifierPeriode($classe, $periode);
        abort_unless($this->peutApprecier($classe), 403);
        $resultats = $moyennes->resultatsClasse($classe, $periode);

        return view('pedagogie.bulletins.appreciations', [
            'classe' => $classe,
            'periode' => $periode,
            'resultats' => $resultats,
            'appreciations' => Appreciation::query()->where('periode_id', $periode->id)->whereIn('inscription_id', $resultats['eleves']->keys())
                ->get()->keyBy('inscription_id'),
            'publiee' => $this->service->estPubliee($classe, $periode),
            'distinctions' => ['Félicitations', 'Tableau d\'honneur', 'Encouragements', 'Avertissement travail', 'Avertissement conduite', 'Blâme'],
        ]);
    }

    public function enregistrerAppreciations(Request $request, Classe $classe, Periode $periode): RedirectResponse
    {
        $this->verifierPeriode($classe, $periode);
        abort_unless($this->peutApprecier($classe), 403);
        $request->validate([
            'lignes' => ['required', 'array'],
            'lignes.*.appreciation_generale' => ['nullable', 'string', 'max:500'],
            'lignes.*.mention' => ['nullable', 'string', 'max:100'],
        ], [], ['lignes.*.appreciation_generale' => 'appréciation', 'lignes.*.mention' => 'décision du conseil']);

        try {
            $n = $this->service->enregistrerAppreciations($classe, $periode, $request->input('lignes'), $request->user());
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return redirect()->route('bulletins.classe', [$classe, $periode])->with('succes', "{$n} appréciation(s) enregistrée(s).");
    }

    public function publier(Request $request, Classe $classe, Periode $periode): RedirectResponse
    {
        $this->verifierPeriode($classe, $periode);

        try {
            $n = $this->service->publier($classe, $periode, $request->user(), $request->boolean('forcer'));
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('succes', "{$n} bulletin(s) publié(s). Les familles disposant d'un compte ont été notifiées.");
    }

    public function depublier(Request $request, Classe $classe, Periode $periode): RedirectResponse
    {
        $this->verifierPeriode($classe, $periode);
        $donnees = $request->validate(['motif' => ['required', 'string', 'max:255']], [], ['motif' => 'motif']);

        try {
            $this->service->depublier($classe, $periode, $donnees['motif']);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('succes', 'Publication retirée : les familles ne voient plus ces bulletins. Corrigez puis republiez (nouvelle version).');
    }

    /**
     * Bulletin individuel en PDF (personnel, ou famille si publié — BulletinPolicy).
     */
    public function pdf(Bulletin $bulletin): Response
    {
        $this->authorize('view', $bulletin);

        return $this->rendre(new EloquentCollection([$bulletin]), 'bulletin-'.$bulletin->inscription->eleve->matricule.'-'.Str::slug($bulletin->periode->libelle).'.pdf');
    }

    /**
     * Tous les bulletins d'une classe, un par page, pour l'impression.
     */
    public function pdfClasse(Classe $classe, Periode $periode): Response
    {
        $this->verifierPeriode($classe, $periode);
        $bulletins = Bulletin::query()->where('periode_id', $periode->id)
            ->whereHas('inscription', fn ($q) => $q->where('classe_id', $classe->id))
            ->get()->sortBy(fn ($b) => $b->inscription->eleve->nom_complet)->values();
        abort_if($bulletins->isEmpty(), 404, 'Aucun bulletin publié pour cette classe et cette période.');

        return $this->rendre($bulletins, 'bulletins-'.Str::slug($classe->nom.'-'.$periode->libelle).'.pdf');
    }

    /**
     * @param  EloquentCollection<int, Bulletin>  $bulletins
     */
    private function rendre(EloquentCollection $bulletins, string $nom): Response
    {
        $bulletins->load(['periode.anneeScolaire', 'publication', 'inscription.eleve', 'inscription.classe.professeurPrincipal.user']);
        $etablissement = etablissement();
        // dompdf a besoin de l'extension GD pour les images : sans elle, le logo est omis plutôt que de faire échouer le PDF.
        $logo = $etablissement->logo_chemin && extension_loaded('gd') && Storage::disk('local')->exists($etablissement->logo_chemin)
            ? 'data:'.Storage::disk('local')->mimeType($etablissement->logo_chemin).';base64,'.base64_encode(Storage::disk('local')->get($etablissement->logo_chemin))
            : null;

        return Pdf::loadView('pedagogie.bulletins.pdf', [
            'bulletins' => $bulletins,
            'etablissement' => $etablissement,
            'logo' => $logo,
            'decimales' => (int) $etablissement->parametre('decimales', 2),
        ])->setPaper('a4')->setOption('isFontSubsettingEnabled', true)->stream($nom);
    }

    private function peutApprecier(Classe $classe): bool
    {
        $user = request()->user();

        return $user->can('bulletins.publier')
            || ($classe->professeur_principal_id !== null && $user->enseignant?->id === $classe->professeur_principal_id);
    }

    private function verifierPeriode(Classe $classe, Periode $periode): void
    {
        abort_unless($periode->annee_scolaire_id === $classe->annee_scolaire_id, 404);
    }
}
