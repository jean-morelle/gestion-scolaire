<?php

namespace App\Http\Controllers\Finance;

use App\Enums\StatutPaiement;
use App\Http\Controllers\Controller;
use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\GrilleFrais;
use App\Models\Inscription;
use App\Models\Remise;
use App\Services\FraisService;
use App\Services\SoldeService;
use App\Support\ExportCsv;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Situation financière des élèves : soldes, retards, détail par élève.
 */
class SituationController extends Controller
{
    public function __construct(private SoldeService $soldes) {}

    public function index(Request $request): View|Response
    {
        $annees = AnneeScolaire::query()->orderByDesc('date_debut')->get();
        $annee = $annees->firstWhere('id', $request->integer('annee')) ?? $annees->firstWhere('est_active', true) ?? $annees->first();

        $inscriptions = Inscription::query()
            ->with(['eleve', 'classe'])
            ->where('inscriptions.annee_scolaire_id', $annee?->id)
            ->when($request->filled('classe'), fn (Builder $q) => $q->where('classe_id', $request->integer('classe')))
            ->when($request->filled('q'), function (Builder $q) use ($request) {
                foreach (preg_split('/\s+/', trim($request->string('q'))) as $mot) {
                    $q->whereHas('eleve', fn (Builder $e) => $e->where(fn (Builder $w) => $w->where('nom', 'like', "%{$mot}%")
                        ->orWhere('prenoms', 'like', "%{$mot}%")->orWhere('matricule', 'like', "%{$mot}%")));
                }
            })
            ->join('eleves', 'eleves.id', '=', 'inscriptions.eleve_id')
            ->orderBy('eleves.nom')->orderBy('eleves.prenoms')->select('inscriptions.*')
            ->get();

        $soldes = $this->soldes->soldes($inscriptions->pluck('id'));
        $filtre = $request->input('situation');
        $lignes = $inscriptions->map(fn ($i) => ['inscription' => $i] + $soldes[$i->id])
            ->filter(fn ($l) => match ($filtre) {
                'retard' => $l['retard'] > 0,
                'solde' => $l['solde'] > 0,
                'regle' => $l['du'] > 0 && $l['solde'] <= 0,
                'sans_frais' => $l['du'] === 0,
                default => true,
            })
            ->when($filtre === 'retard', fn ($c) => $c->sortByDesc('retard'))
            ->values();

        if ($request->query('format') === 'csv') {
            return ExportCsv::telecharger("situation-financiere-{$annee?->libelle}.csv",
                ['Matricule', 'Nom', 'Prénoms', 'Classe', 'Total dû', 'Remises', 'Payé', 'Reste à payer', 'Dont en retard'],
                $lignes->map(fn ($l) => [$l['inscription']->eleve->matricule, $l['inscription']->eleve->nom, $l['inscription']->eleve->prenoms,
                    $l['inscription']->classe->nom, $l['du'], $l['remises'], $l['paye'], $l['solde'], $l['retard']]));
        }

        $page = LengthAwarePaginator::resolveCurrentPage();
        $totaux = collect(['du', 'remises', 'paye', 'solde', 'retard'])->mapWithKeys(fn ($k) => [$k => $lignes->sum($k)]);

        return view('finance.situation.index', [
            'annees' => $annees,
            'annee' => $annee,
            'classes' => $annee ? Classe::query()->where('annee_scolaire_id', $annee->id)->orderBy('nom')->pluck('nom', 'id') : collect(),
            'lignes' => new LengthAwarePaginator($lignes->forPage($page, 25)->values(), $lignes->count(), 25, $page, ['path' => $request->url(), 'query' => $request->query()]),
            'totaux' => $totaux,
            'enRetard' => $lignes->where('retard', '>', 0)->count(),
        ]);
    }

    public function show(Inscription $inscription, FraisService $frais): View
    {
        $inscription->load(['eleve.responsables', 'classe', 'anneeScolaire']);
        $situation = $this->soldes->situation($inscription);
        $facturees = $situation['lignes']->pluck('frais.type_frais_id')->unique();

        return view('finance.situation.show', [
            'inscription' => $inscription,
            'situation' => $situation,
            'paiements' => $inscription->paiements()->with(['encaisseur', 'annulateur', 'imputations.fraisEleve'])->latest('date_paiement')->latest('id')->get(),
            'remises' => Remise::query()->whereIn('frais_eleve_id', $situation['lignes']->pluck('frais.id'))
                ->with(['fraisEleve', 'auteur', 'annulateur'])->latest()->get(),
            // Frais facultatifs du niveau non encore attribués (cantine, transport…).
            'facultatifs' => $frais->grillesApplicables($inscription)->filter(fn ($g) => ! $g->typeFrais->est_obligatoire && ! $facturees->contains($g->type_frais_id)),
            'payeur' => $inscription->eleve->responsables->firstWhere('pivot.est_payeur', true) ?? $inscription->eleve->responsables->first(),
            'nbPaiementsValides' => $inscription->paiements()->where('statut', StatutPaiement::Valide)->count(),
        ]);
    }

    public function attribuer(Request $request, Inscription $inscription, FraisService $frais): RedirectResponse
    {
        $donnees = $request->validate(['grille_frais_id' => ['required', 'exists:grilles_frais,id']], [], ['grille_frais_id' => 'frais']);

        try {
            $n = $frais->attribuer($inscription, GrilleFrais::with(['tranches', 'typeFrais'])->findOrFail($donnees['grille_frais_id']), $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('succes', "Frais attribué ({$n} échéance(s)).");
    }

    public function retirer(Request $request, Inscription $inscription, FraisService $frais): RedirectResponse
    {
        $donnees = $request->validate(['type_frais_id' => ['required', 'integer']]);

        try {
            $frais->retirer($inscription, (int) $donnees['type_frais_id']);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('succes', 'Frais retiré.');
    }

    /**
     * Génère les frais obligatoires d'un élève inscrit avant la création des grilles.
     */
    public function generer(Inscription $inscription, FraisService $frais): RedirectResponse
    {
        $n = $frais->genererObligatoires($inscription->load('classe'));

        return back()->with($n ? 'succes' : 'info', $n ? "{$n} échéance(s) ajoutée(s)." : 'Aucun frais obligatoire à ajouter pour cet élève.');
    }
}
