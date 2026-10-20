<?php

namespace App\Http\Controllers\Finance;

use App\Enums\ModePaiement;
use App\Enums\StatutPaiement;
use App\Http\Controllers\Controller;
use App\Models\Inscription;
use App\Models\Paiement;
use App\Models\User;
use App\Services\PaiementService;
use App\Services\SoldeService;
use App\Support\ExportCsv;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class PaiementController extends Controller
{
    public function __construct(private PaiementService $service) {}

    /**
     * Journal des encaissements avec totaux (par défaut : le mois en cours).
     */
    public function index(Request $request): View|Response
    {
        $du = $this->date($request->input('du')) ?? CarbonImmutable::today()->startOfMonth();
        $au = $this->date($request->input('au')) ?? CarbonImmutable::today();

        $requete = Paiement::query()
            ->with(['inscription.eleve', 'inscription.classe', 'encaisseur'])
            ->whereDate('date_paiement', '>=', $du)->whereDate('date_paiement', '<=', $au)
            ->when($request->filled('mode'), fn (Builder $q) => $q->where('mode', $request->input('mode')))
            ->when($request->filled('statut'), fn (Builder $q) => $q->where('statut', $request->input('statut')))
            ->when($request->filled('encaisseur'), fn (Builder $q) => $q->where('encaisse_par', $request->integer('encaisseur')))
            ->when($request->filled('q'), fn (Builder $q) => $q->where(fn (Builder $w) => $w->where('numero_recu', 'like', '%'.$request->string('q').'%')
                ->orWhereHas('inscription.eleve', fn (Builder $e) => $e->where('nom', 'like', '%'.$request->string('q').'%')->orWhere('prenoms', 'like', '%'.$request->string('q').'%'))))
            ->orderByDesc('date_paiement')->orderByDesc('id');

        if ($request->query('format') === 'csv') {
            return ExportCsv::telecharger("paiements-{$du->toDateString()}-{$au->toDateString()}.csv",
                ['Reçu', 'Date', 'Matricule', 'Élève', 'Classe', 'Montant', 'Mode', 'Référence', 'Payeur', 'Encaissé par', 'Statut', 'Motif d\'annulation'],
                $requete->lazy()->map(fn (Paiement $p) => [$p->numero_recu, $p->date_paiement, $p->inscription->eleve->matricule, $p->inscription->eleve->nom_complet,
                    $p->inscription->classe->nom, $p->montant, $p->mode, $p->reference_externe, $p->payeur_nom, $p->encaisseur->name, $p->statut, $p->motif_annulation]));
        }

        $valides = (clone $requete)->reorder()->setEagerLoads([])->where('statut', StatutPaiement::Valide);

        return view('finance.paiements.index', [
            'paiements' => $requete->paginate(25)->withQueryString(),
            'du' => $du, 'au' => $au,
            'total' => (int) (clone $valides)->sum('montant'),
            'parMode' => (clone $valides)->selectRaw('mode, SUM(montant) as total, COUNT(*) as nombre')->groupBy('mode')->get(),
            'annules' => (clone $requete)->reorder()->setEagerLoads([])->where('statut', StatutPaiement::Annule)->count(),
            'encaisseurs' => User::query()->whereIn('id', Paiement::query()->select('encaisse_par')->distinct())->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function store(Request $request, Inscription $inscription): RedirectResponse
    {
        $request->merge(['montant' => preg_replace('/\D/', '', (string) $request->input('montant'))]);
        $donnees = $request->validate([
            'montant' => ['required', 'integer', 'min:1'],
            'date_paiement' => ['required', 'date'],
            'mode' => ['required', Rule::enum(ModePaiement::class)],
            'reference_externe' => ['nullable', 'string', 'max:100'],
            'payeur_nom' => ['nullable', 'string', 'max:150'],
            'repartition' => ['nullable', 'in:auto,manuelle'],
            'imputations' => ['nullable', 'array'],
            'imputations.*' => ['nullable', 'integer', 'min:0'],
        ], [], ['date_paiement' => 'date', 'reference_externe' => 'référence', 'payeur_nom' => 'payeur']);

        $manuelle = ($donnees['repartition'] ?? 'auto') === 'manuelle'
            ? array_map('intval', array_filter($donnees['imputations'] ?? [], fn ($v) => $v !== null && $v !== ''))
            : null;

        try {
            $paiement = $this->service->encaisser($inscription, (int) $donnees['montant'], $donnees['date_paiement'], ModePaiement::from($donnees['mode']),
                $donnees['reference_externe'] ?? null, $donnees['payeur_nom'] ?? null, $request->user(), $manuelle);
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return redirect()->route('finances.show', $inscription)
            ->with('succes', "Paiement enregistré : reçu {$paiement->numero_recu} (".montant($paiement->montant).').')
            ->with('recu', $paiement->id);
    }

    public function annuler(Request $request, Paiement $paiement): RedirectResponse
    {
        $donnees = $request->validate(['motif_annulation' => ['required', 'string', 'max:255']], [], ['motif_annulation' => 'motif']);

        try {
            $this->service->annuler($paiement, $donnees['motif_annulation'], $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('succes', "Le reçu {$paiement->numero_recu} est annulé. Il reste consultable avec la mention « ANNULÉ ».");
    }

    /**
     * Reçu PDF (RG-FIN-07/08) : personnel financier, ou famille / élève (PaiementPolicy).
     */
    public function recu(Paiement $paiement, SoldeService $soldes): Response
    {
        $this->authorize('view', $paiement);
        $paiement->load(['inscription.eleve', 'inscription.classe', 'inscription.anneeScolaire', 'encaisseur', 'annulateur', 'imputations.fraisEleve']);

        return Pdf::loadView('finance.paiements.recu', [
            'paiement' => $paiement,
            'etablissement' => etablissement(),
            'solde' => $soldes->soldes([$paiement->inscription_id])[$paiement->inscription_id]['solde'],
        ])->setPaper('a5', 'landscape')->setOption('isFontSubsettingEnabled', true)->stream("recu-{$paiement->numero_recu}.pdf");
    }

    private function date(mixed $valeur): ?CarbonImmutable
    {
        return is_string($valeur) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $valeur) ? CarbonImmutable::parse($valeur) : null;
    }
}
