<?php

namespace App\Http\Controllers\Pedagogie;

use App\Enums\TypeRessource;
use App\Http\Controllers\Controller;
use App\Models\Affectation;
use App\Models\Cours;
use App\Models\Devoir;
use App\Models\Enseignant;
use App\Models\RemiseDevoir;
use App\Models\RessourcePedagogique;
use App\Services\ApprentissageService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class CoursController extends Controller
{
    public function __construct(private ApprentissageService $service) {}

    /**
     * Enseignant : ses matières avec cours et devoirs. Direction / vie scolaire : suivi de l'activité pédagogique.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $tout = $user->can('rapports.scolaires');
        $annee = annee_active();

        $affectations = Affectation::query()
            ->with(['classe.niveau', 'matiere', 'enseignant.user'])
            ->withCount(['cours', 'cours as cours_publies' => fn (Builder $q) => $q->where('est_publie', true), 'devoirs'])
            ->whereHas('classe', fn (Builder $q) => $q->where('annee_scolaire_id', $annee?->id))
            ->when(! $tout, fn (Builder $q) => $q->where('enseignant_id', $user->enseignant?->id ?? 0))
            ->when($request->filled('enseignant'), fn (Builder $q) => $q->where('enseignant_id', $request->integer('enseignant')))
            ->get()
            ->sortBy(fn ($a) => [$a->classe->niveau->ordre, $a->classe->nom, $a->matiere->libelle])->values();

        $devoirs = Devoir::query()->with(['affectation.classe', 'affectation.matiere'])
            ->withCount(['remises', 'remises as corrigees' => fn (Builder $q) => $q->whereNotNull('corrige_at')])
            ->whereIn('affectation_id', $affectations->pluck('id'))
            ->orderByDesc('date_limite')->limit(15)->get();

        return view('pedagogie.cours.index', [
            'affectations' => $affectations,
            'cours' => Cours::query()->with(['affectation.classe', 'affectation.matiere'])->withCount('ressources')
                ->whereIn('affectation_id', $affectations->pluck('id'))->latest('updated_at')->limit(15)->get(),
            'devoirs' => $devoirs,
            'tout' => $tout,
            'enseignants' => $tout ? Enseignant::query()->actifs()->with('user')->get()->sortBy('user.name')->mapWithKeys(fn ($e) => [$e->id => $e->user->name]) : collect(),
            'copiesACorriger' => RemiseDevoir::query()->whereNull('corrige_at')->whereIn('devoir_id', Devoir::query()->whereIn('affectation_id', $affectations->pluck('id'))->select('id'))->count(),
        ]);
    }

    public function create(Request $request): View
    {
        $affectation = Affectation::with(['classe', 'matiere'])->findOrFail($request->integer('affectation'));
        $this->authorize('create', [Cours::class, $affectation]);

        return view('pedagogie.cours.form', ['cours' => new Cours(['affectation_id' => $affectation->id]), 'affectation' => $affectation]);
    }

    public function store(Request $request): RedirectResponse
    {
        $affectation = Affectation::with('classe.anneeScolaire')->findOrFail($request->integer('affectation_id'));
        $this->authorize('create', [Cours::class, $affectation]);

        try {
            $cours = $this->service->creerCours($affectation, $this->valider($request), $request->user());
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return redirect()->route('cours.show', $cours)->with('succes', 'Cours créé : ajoutez ses chapitres et ressources, puis publiez-le.');
    }

    public function show(Cours $cours): View
    {
        $this->authorize('view', $cours);
        $cours->load(['affectation.classe', 'affectation.matiere', 'affectation.enseignant.user', 'ressources', 'devoirs']);

        return view('pedagogie.cours.show', [
            'cours' => $cours,
            'peutModifier' => request()->user()->can('update', $cours),
            'tailleMaxKo' => (int) etablissement()->parametre('taille_max_fichier_ko', 5120),
        ]);
    }

    public function edit(Cours $cours): View
    {
        $this->authorize('update', $cours);

        return view('pedagogie.cours.form', ['cours' => $cours, 'affectation' => $cours->affectation->load(['classe', 'matiere'])]);
    }

    public function update(Request $request, Cours $cours): RedirectResponse
    {
        $this->authorize('update', $cours);
        $cours->update($this->valider($request));

        return redirect()->route('cours.show', $cours)->with('succes', 'Cours modifié.');
    }

    public function destroy(Cours $cours): RedirectResponse
    {
        $this->authorize('update', $cours);

        try {
            $this->service->supprimerCours($cours);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()->route('cours.index')->with('succes', 'Cours supprimé.');
    }

    public function publier(Request $request, Cours $cours): RedirectResponse
    {
        $this->authorize('update', $cours);
        $publier = $request->boolean('publier');
        if ($publier && $cours->ressources()->doesntExist()) {
            return back()->with('erreur', 'Ajoutez au moins une ressource avant de publier le cours.');
        }
        $this->service->publierCours($cours, $publier);

        return back()->with('succes', $publier ? 'Cours publié : les élèves de la classe y ont accès.' : 'Cours retiré : les élèves n\'y ont plus accès.');
    }

    public function storeRessource(Request $request, Cours $cours): RedirectResponse
    {
        $this->authorize('update', $cours);
        $tailleMax = (int) etablissement()->parametre('taille_max_fichier_ko', 5120);
        $donnees = $request->validate([
            'type' => ['required', Rule::enum(TypeRessource::class)],
            'titre' => ['required', 'string', 'max:150'],
            'contenu' => ['nullable', 'string', 'max:20000'],
            'url' => ['nullable', 'url:http,https', 'max:255'],
            'fichier' => ['nullable', 'file', 'mimes:'.ApprentissageService::MIMES_RESSOURCES, "max:{$tailleMax}"],
        ], [], ['titre' => 'titre de la ressource', 'url' => 'lien']);

        try {
            $this->service->ajouterRessource($cours, $donnees, $request->file('fichier'));
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return back()->with('succes', "« {$donnees['titre']} » ajouté au cours.");
    }

    public function destroyRessource(Cours $cours, RessourcePedagogique $ressource): RedirectResponse
    {
        $this->authorize('update', $cours);
        $this->service->supprimerRessource($ressource);

        return back()->with('succes', 'Ressource retirée.');
    }

    /**
     * Téléchargement d'un document de cours, après contrôle d'accès (un élève : cours publié de sa classe).
     */
    public function fichier(Cours $cours, RessourcePedagogique $ressource): Response
    {
        $this->authorize('view', $cours);
        abort_unless($ressource->fichier_chemin && Storage::disk('local')->exists($ressource->fichier_chemin), 404);

        return Storage::disk('local')->download($ressource->fichier_chemin, $ressource->fichier_nom);
    }

    private function valider(Request $request): array
    {
        return $request->validate([
            'titre' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],
        ], [], ['titre' => 'titre du cours']);
    }
}
