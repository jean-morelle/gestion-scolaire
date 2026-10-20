<?php

namespace App\Http\Controllers\Finance;

use App\Enums\StatutDepense;
use App\Enums\StatutInscription;
use App\Enums\StatutPaiement;
use App\Http\Controllers\Controller;
use App\Models\AnneeScolaire;
use App\Models\Depense;
use App\Models\Imputation;
use App\Models\Inscription;
use App\Models\Paiement;
use App\Services\SoldeService;
use App\Support\ExportCsv;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rapports financiers : recettes, dépenses, solde de trésorerie de la période, impayés de l'année active.
 */
class RapportFinancierController extends Controller
{
    public function __invoke(Request $request, SoldeService $soldes): View|Response
    {
        $annee = annee_active();
        $du = $this->date($request->input('du')) ?? ($annee ? CarbonImmutable::parse($annee->date_debut) : CarbonImmutable::today()->startOfYear());
        $au = $this->date($request->input('au')) ?? CarbonImmutable::today();

        $paiements = Paiement::query()->where('statut', StatutPaiement::Valide)
            ->whereDate('date_paiement', '>=', $du)->whereDate('date_paiement', '<=', $au);
        $depenses = Depense::query()->where('statut', StatutDepense::Valide)
            ->whereDate('date_depense', '>=', $du)->whereDate('date_depense', '<=', $au);

        $recettesParType = Imputation::query()
            ->join('paiements', 'paiements.id', '=', 'imputations.paiement_id')
            ->join('frais_eleves', 'frais_eleves.id', '=', 'imputations.frais_eleve_id')
            ->join('types_frais', 'types_frais.id', '=', 'frais_eleves.type_frais_id')
            ->where('paiements.statut', StatutPaiement::Valide)
            ->whereDate('paiements.date_paiement', '>=', $du)->whereDate('paiements.date_paiement', '<=', $au)
            ->selectRaw('types_frais.libelle, SUM(imputations.montant) as total')->groupBy('types_frais.libelle')->orderByDesc('total')->get();

        $mois = fn ($colonne) => DB::connection()->getDriverName() === 'sqlite' ? "strftime('%Y-%m', {$colonne})" : "DATE_FORMAT({$colonne}, '%Y-%m')";
        $recettesParMois = (clone $paiements)->selectRaw($mois('date_paiement').' as mois, SUM(montant) as total')->groupBy('mois')->pluck('total', 'mois');
        $depensesParMois = (clone $depenses)->selectRaw($mois('date_depense').' as mois, SUM(montant) as total')->groupBy('mois')->pluck('total', 'mois');
        $parMois = $recettesParMois->keys()->merge($depensesParMois->keys())->unique()->sort()->values()
            ->mapWithKeys(fn ($m) => [$m => ['recettes' => (int) ($recettesParMois[$m] ?? 0), 'depenses' => (int) ($depensesParMois[$m] ?? 0)]]);

        $impayes = $this->impayesParClasse($annee, $soldes);
        $type = $request->query('export');
        if ($type) {
            return $this->exporter($type, $du, $au, $impayes, $parMois);
        }

        return view('finance.rapports.index', [
            'du' => $du, 'au' => $au, 'annee' => $annee,
            'totalRecettes' => (int) (clone $paiements)->sum('montant'),
            'totalDepenses' => (int) (clone $depenses)->sum('montant'),
            'parMode' => (clone $paiements)->selectRaw('mode, SUM(montant) as total, COUNT(*) as nombre')->groupBy('mode')->get(),
            'recettesParType' => $recettesParType,
            'depensesParCategorie' => (clone $depenses)->join('categories_depenses', 'categories_depenses.id', '=', 'depenses.categorie_depense_id')
                ->selectRaw('categories_depenses.libelle, SUM(depenses.montant) as total')->groupBy('categories_depenses.libelle')->orderByDesc('total')->get(),
            'parMois' => $parMois,
            'impayes' => $impayes,
            'annulations' => Paiement::query()->where('statut', StatutPaiement::Annule)
                ->whereDate('annule_at', '>=', $du)->whereDate('annule_at', '<=', $au->endOfDay())->count(),
        ]);
    }

    /**
     * @return Collection<string, array{classe: string, eleves: int, du: int, remises: int, paye: int, solde: int, retard: int, en_retard: int}>
     */
    private function impayesParClasse(?AnneeScolaire $annee, SoldeService $soldes): Collection
    {
        if (! $annee) {
            return collect();
        }
        $inscriptions = Inscription::query()->with('classe.niveau')->where('annee_scolaire_id', $annee->id)
            ->where('statut', StatutInscription::Inscrit)->get();
        $calcul = $soldes->soldes($inscriptions->pluck('id'));

        return $inscriptions->groupBy('classe_id')->map(function ($groupe) use ($calcul) {
            $s = $groupe->map(fn ($i) => $calcul[$i->id]);

            return [
                'classe' => $groupe->first()->classe->nom,
                'ordre' => [$groupe->first()->classe->niveau->ordre, $groupe->first()->classe->nom],
                'eleves' => $groupe->count(),
                'du' => $s->sum('du'), 'remises' => $s->sum('remises'), 'paye' => $s->sum('paye'),
                'solde' => $s->sum('solde'), 'retard' => $s->sum('retard'), 'en_retard' => $s->where('retard', '>', 0)->count(),
            ];
        })->sortBy('ordre')->values();
    }

    private function exporter(string $type, CarbonImmutable $du, CarbonImmutable $au, Collection $impayes, Collection $parMois): Response
    {
        $periode = $du->toDateString().'-'.$au->toDateString();

        return match ($type) {
            'mensuel' => ExportCsv::telecharger("recettes-depenses-{$periode}.csv", ['Mois', 'Recettes', 'Dépenses', 'Solde'],
                $parMois->map(fn ($m, $mois) => [$mois, $m['recettes'], $m['depenses'], $m['recettes'] - $m['depenses']])->values()),
            'impayes' => ExportCsv::telecharger('impayes-par-classe.csv', ['Classe', 'Élèves', 'Total dû', 'Remises', 'Payé', 'Reste à payer', 'En retard', 'Élèves en retard'],
                $impayes->map(fn ($c) => [$c['classe'], $c['eleves'], $c['du'], $c['remises'], $c['paye'], $c['solde'], $c['retard'], $c['en_retard']])),
            default => abort(404),
        };
    }

    private function date(mixed $valeur): ?CarbonImmutable
    {
        return is_string($valeur) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $valeur) ? CarbonImmutable::parse($valeur) : null;
    }
}
