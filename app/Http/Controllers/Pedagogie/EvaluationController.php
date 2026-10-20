<?php

namespace App\Http\Controllers\Pedagogie;

use App\Enums\StatutPeriode;
use App\Enums\TypeEvaluation;
use App\Http\Controllers\Controller;
use App\Models\Affectation;
use App\Models\Evaluation;
use App\Models\Periode;
use App\Services\EvaluationService;
use App\Services\MoyenneService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class EvaluationController extends Controller
{
    public function __construct(private EvaluationService $service) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Evaluation::class);
        $user = $request->user();
        $annee = annee_active();
        // Sans le droit de tout consulter, l'enseignant ne voit que ses affectations (RG-ENS-02).
        $tout = $user->can('notes.consulter');
        $enseignantId = $user->enseignant?->id ?? 0;

        $affectations = Affectation::query()
            ->with(['classe.niveau', 'matiere', 'enseignant.user'])
            ->whereHas('classe', fn (Builder $q) => $q->where('annee_scolaire_id', $annee?->id))
            ->when(! $tout, fn (Builder $q) => $q->where('enseignant_id', $enseignantId))
            ->get()
            ->sortBy(fn ($a) => [$a->classe->niveau->ordre, $a->classe->nom, $a->matiere->libelle])->values();

        $evaluations = Evaluation::query()
            ->with(['affectation.classe', 'affectation.matiere', 'periode'])
            ->withCount(['notes', 'notes as absents' => fn (Builder $q) => $q->where('est_absent', true)])
            ->whereIn('affectation_id', $affectations->pluck('id'))
            ->when($request->filled('affectation'), fn (Builder $q) => $q->where('affectation_id', $request->integer('affectation')))
            ->when($request->filled('classe'), fn (Builder $q) => $q->whereHas('affectation', fn (Builder $a) => $a->where('classe_id', $request->integer('classe'))))
            ->when($request->filled('periode'), fn (Builder $q) => $q->where('periode_id', $request->integer('periode')))
            ->when($request->filled('statut'), fn (Builder $q) => $q->where('statut', $request->input('statut')))
            ->orderByDesc('date_evaluation')->orderByDesc('id')
            ->paginate(20)->withQueryString();

        return view('pedagogie.evaluations.index', [
            'affectations' => $affectations,
            'evaluations' => $evaluations,
            'periodes' => $annee ? $annee->periodes()->pluck('libelle', 'id') : collect(),
            'classes' => $affectations->pluck('classe')->unique('id')->pluck('nom', 'id'),
            'tout' => $tout,
        ]);
    }

    public function create(Request $request): View
    {
        $affectation = Affectation::with(['classe.anneeScolaire', 'matiere'])->findOrFail($request->integer('affectation'));
        $this->authorize('create', [Evaluation::class, $affectation]);
        $periodeCourante = $affectation->classe->anneeScolaire->periodePour(now());

        return view('pedagogie.evaluations.form', [
            'evaluation' => new Evaluation([
                'affectation_id' => $affectation->id,
                'periode_id' => $periodeCourante?->id,
                'date_evaluation' => now(),
                'type' => TypeEvaluation::Devoir,
                'bareme' => etablissement()->parametre('note_sur', 20),
                'poids' => 1,
            ]),
            'affectation' => $affectation,
            'periodes' => $this->periodesOuvertes($affectation),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $affectation = Affectation::with('classe')->findOrFail($request->integer('affectation_id'));
        $this->authorize('create', [Evaluation::class, $affectation]);

        try {
            $evaluation = $this->service->creer($affectation, $this->valider($request), $request->user());
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return redirect()->route('evaluations.show', $evaluation)->with('succes', 'Évaluation créée : saisissez maintenant les notes.');
    }

    /**
     * Grille de saisie (ou consultation) des notes, avec statistiques.
     */
    public function show(Evaluation $evaluation, MoyenneService $moyennes): View
    {
        $this->authorize('view', $evaluation);
        $evaluation->load(['affectation.classe', 'affectation.matiere', 'affectation.enseignant.user', 'periode', 'validateur', 'auteur']);
        $notes = $evaluation->notes()->get()->keyBy('inscription_id');
        $valeurs = $notes->reject(fn ($n) => $n->est_absent || $n->valeur === null)->map(fn ($n) => (float) $n->valeur);
        $bareme = (float) $evaluation->bareme;

        return view('pedagogie.evaluations.show', [
            'evaluation' => $evaluation,
            'eleves' => $this->service->eleves($evaluation),
            'notes' => $notes,
            'blocage' => $this->service->blocage($evaluation),
            'peutSaisir' => request()->user()->can('update', $evaluation),
            'stats' => [
                'notees' => $valeurs->count(),
                'absents' => $notes->where('est_absent', true)->count(),
                'moyenne' => $valeurs->isEmpty() ? null : $valeurs->avg() / $bareme * 20,
                'min' => $valeurs->min(),
                'max' => $valeurs->max(),
                'sous_moyenne' => $valeurs->filter(fn ($v) => $v / $bareme * 20 < 10)->count(),
            ],
        ]);
    }

    public function edit(Evaluation $evaluation): View
    {
        $this->authorize('update', $evaluation);
        $evaluation->load('affectation.classe', 'affectation.matiere');

        return view('pedagogie.evaluations.form', [
            'evaluation' => $evaluation,
            'affectation' => $evaluation->affectation,
            'periodes' => $this->periodesOuvertes($evaluation->affectation),
        ]);
    }

    public function update(Request $request, Evaluation $evaluation): RedirectResponse
    {
        $this->authorize('update', $evaluation);

        try {
            $this->service->modifier($evaluation, $this->valider($request));
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return redirect()->route('evaluations.show', $evaluation)->with('succes', 'L\'évaluation a été modifiée.');
    }

    public function destroy(Evaluation $evaluation): RedirectResponse
    {
        $this->authorize('update', $evaluation);

        try {
            $this->service->supprimer($evaluation);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()->route('evaluations.index')->with('succes', 'L\'évaluation a été supprimée.');
    }

    public function notes(Request $request, Evaluation $evaluation): RedirectResponse
    {
        $this->authorize('update', $evaluation);
        $request->validate([
            'notes' => ['required', 'array'],
            'notes.*.valeur' => ['nullable', 'string', 'max:10'],
            'notes.*.est_absent' => ['nullable', 'boolean'],
            'notes.*.observation' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $total = $this->service->saisirNotes($evaluation, $request->input('notes'));
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        if ($request->boolean('valider')) {
            return $this->verrouiller($request, $evaluation, "Notes enregistrées ({$total}).");
        }

        return back()->with('succes', "Notes enregistrées : {$total} élève(s) noté(s) ou absent(s).");
    }

    public function validerEvaluation(Request $request, Evaluation $evaluation): RedirectResponse
    {
        return $this->verrouiller($request, $evaluation);
    }

    /**
     * Validation (RG-NOT-09) ; $prefixe rappelle l'enregistrement des notes qui l'a précédée.
     */
    private function verrouiller(Request $request, Evaluation $evaluation, ?string $prefixe = null): RedirectResponse
    {
        $this->authorize('valider', $evaluation);

        try {
            $this->service->valider($evaluation, $request->user());
        } catch (ValidationException $e) {
            $retour = back()->withErrors($e->errors());

            return $prefixe ? $retour->with('succes', $prefixe.' Validation impossible pour l\'instant.') : $retour;
        }

        return back()->with('succes', trim($prefixe.' Évaluation validée : les notes sont verrouillées.'));
    }

    public function deverrouiller(Request $request, Evaluation $evaluation): RedirectResponse
    {
        $this->authorize('deverrouiller', $evaluation);
        $donnees = $request->validate(['motif' => ['required', 'string', 'max:255']], [], ['motif' => 'motif du déverrouillage']);

        try {
            $this->service->deverrouiller($evaluation, $donnees['motif']);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('succes', 'Évaluation déverrouillée : les modifications de notes seront historisées.');
    }

    /**
     * @return array<string, mixed>
     */
    private function valider(Request $request): array
    {
        $request->merge([
            'bareme' => str_replace(',', '.', (string) $request->input('bareme')),
            'poids' => str_replace(',', '.', (string) $request->input('poids')),
        ]);

        return $request->validate([
            'periode_id' => ['required', 'exists:periodes,id'],
            'libelle' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::enum(TypeEvaluation::class)],
            'date_evaluation' => ['required', 'date'],
            'bareme' => ['required', 'numeric', 'min:1', 'max:100', 'decimal:0,2'],
            'poids' => ['required', 'numeric', 'min:0.25', 'max:10', 'decimal:0,2'],
        ], [], ['periode_id' => 'période', 'libelle' => 'intitulé', 'date_evaluation' => 'date', 'bareme' => 'barème']);
    }

    /**
     * @return \Illuminate\Support\Collection<int, string>
     */
    private function periodesOuvertes(Affectation $affectation): \Illuminate\Support\Collection
    {
        return Periode::query()->where('annee_scolaire_id', $affectation->classe->annee_scolaire_id)
            ->where('statut', StatutPeriode::Ouverte)->orderBy('ordre')->get()
            ->mapWithKeys(fn ($p) => [$p->id => $p->libelle.' ('.$p->date_debut->format('d/m').' – '.$p->date_fin->format('d/m/Y').')']);
    }
}
