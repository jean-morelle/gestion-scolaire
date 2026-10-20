<?php

namespace App\Http\Controllers\Pedagogie;

use App\Enums\StatutInscription;
use App\Http\Controllers\Controller;
use App\Models\Affectation;
use App\Models\Devoir;
use App\Models\Inscription;
use App\Models\RemiseDevoir;
use App\Services\ApprentissageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class DevoirController extends Controller
{
    public function __construct(private ApprentissageService $service) {}

    public function create(Request $request): View
    {
        $affectation = Affectation::with(['classe', 'matiere', 'cours'])->findOrFail($request->integer('affectation'));
        $this->authorize('create', [Devoir::class, $affectation]);

        return view('pedagogie.devoirs.form', [
            'devoir' => new Devoir([
                'affectation_id' => $affectation->id, 'cours_id' => $request->integer('cours') ?: null,
                'date_limite' => now()->addWeek()->setTime(18, 0), 'bareme' => 20,
                'remise_en_ligne' => (bool) etablissement()->parametre('remise_devoirs_en_ligne', true), 'accepte_retard' => false,
            ]),
            'affectation' => $affectation,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $affectation = Affectation::with('classe.anneeScolaire')->findOrFail($request->integer('affectation_id'));
        $this->authorize('create', [Devoir::class, $affectation]);

        try {
            $devoir = $this->service->enregistrerDevoir($affectation, $this->valider($request, $affectation), $request->file('sujet'), $request->user());
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return redirect()->route('devoirs.show', $devoir)->with('succes', 'Devoir enregistré (non publié). Publiez-le pour prévenir les élèves.');
    }

    /**
     * Suivi des remises : un tableau de tous les élèves de la classe avec leur statut et leur note.
     */
    public function show(Devoir $devoir): View
    {
        $this->authorize('view', $devoir);
        $devoir->load(['affectation.classe', 'affectation.matiere', 'affectation.enseignant.user', 'cours']);
        $remises = $devoir->remises()->with('correcteur')->get()->keyBy('inscription_id');
        $eleves = Inscription::query()->with('eleve')
            ->where('classe_id', $devoir->affectation->classe_id)
            ->where(fn ($q) => $q->where('inscriptions.statut', StatutInscription::Inscrit)->orWhereIn('inscriptions.id', $remises->keys()))
            ->join('eleves', 'eleves.id', '=', 'inscriptions.eleve_id')->orderBy('eleves.nom')->orderBy('eleves.prenoms')
            ->select('inscriptions.*')->get();

        return view('pedagogie.devoirs.show', [
            'devoir' => $devoir,
            'eleves' => $eleves,
            'remises' => $remises,
            'peutModifier' => request()->user()->can('update', $devoir),
            'enLigne' => $devoir->remise_en_ligne && etablissement()->parametre('remise_devoirs_en_ligne', true),
        ]);
    }

    public function edit(Devoir $devoir): View
    {
        $this->authorize('update', $devoir);

        return view('pedagogie.devoirs.form', ['devoir' => $devoir, 'affectation' => $devoir->affectation->load(['classe', 'matiere', 'cours'])]);
    }

    public function update(Request $request, Devoir $devoir): RedirectResponse
    {
        $this->authorize('update', $devoir);

        try {
            $this->service->enregistrerDevoir($devoir->affectation, $this->valider($request, $devoir->affectation), $request->file('sujet'), $request->user(), $devoir);
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return redirect()->route('devoirs.show', $devoir)->with('succes', 'Devoir modifié.');
    }

    public function destroy(Devoir $devoir): RedirectResponse
    {
        $this->authorize('update', $devoir);

        try {
            $this->service->supprimerDevoir($devoir);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return redirect()->route('cours.index')->with('succes', 'Devoir supprimé.');
    }

    public function publier(Devoir $devoir): RedirectResponse
    {
        $this->authorize('update', $devoir);
        $n = $this->service->publierDevoir($devoir);

        return back()->with('succes', "Devoir publié. {$n} compte(s) élève ou parent notifié(s).");
    }

    public function corriger(Request $request, Devoir $devoir, Inscription $inscription): RedirectResponse
    {
        $this->authorize('update', $devoir);
        $request->merge(['note' => $request->filled('note') ? str_replace(',', '.', (string) $request->input('note')) : null]);
        $donnees = $request->validate([
            'note' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'correction' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $this->service->corriger($devoir, $inscription, isset($donnees['note']) ? (float) $donnees['note'] : null, $donnees['correction'] ?? null, $request->user());
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return back()->with('succes', "Correction enregistrée pour {$inscription->eleve->nom_complet}.");
    }

    public function sujet(Devoir $devoir): Response
    {
        $this->authorize('view', $devoir);
        abort_unless($devoir->fichier_chemin && Storage::disk('local')->exists($devoir->fichier_chemin), 404);

        return Storage::disk('local')->download($devoir->fichier_chemin, $devoir->fichier_nom);
    }

    /**
     * Copie d'un élève : l'élève lui-même, l'enseignant, la direction — jamais un autre élève.
     */
    public function copie(Devoir $devoir, RemiseDevoir $remise): Response
    {
        $this->authorize('voirRemise', [$devoir, $remise]);
        abort_unless($remise->fichier_chemin && Storage::disk('local')->exists($remise->fichier_chemin), 404);

        return Storage::disk('local')->download($remise->fichier_chemin, $remise->fichier_nom);
    }

    private function valider(Request $request, Affectation $affectation): array
    {
        $tailleMax = (int) etablissement()->parametre('taille_max_fichier_ko', 5120);
        $request->merge(['bareme' => str_replace(',', '.', (string) $request->input('bareme'))]);

        return collect($request->validate([
            'titre' => ['required', 'string', 'max:150'],
            'consignes' => ['required', 'string', 'max:10000'],
            'cours_id' => ['nullable', Rule::exists('cours', 'id')->where('affectation_id', $affectation->id)],
            'date_limite' => ['required', 'date'],
            'bareme' => ['required', 'numeric', 'min:1', 'max:100'],
            'remise_en_ligne' => ['boolean'],
            'accepte_retard' => ['boolean'],
            'sujet' => ['nullable', 'file', 'mimes:'.ApprentissageService::MIMES_RESSOURCES, "max:{$tailleMax}"],
        ], [], ['date_limite' => 'date limite', 'bareme' => 'barème', 'sujet' => 'sujet']))->except('sujet')->all() + [
            'remise_en_ligne' => $request->boolean('remise_en_ligne'),
            'accepte_retard' => $request->boolean('accepte_retard'),
        ];
    }
}
