<?php

namespace App\Services;

use App\Enums\StatutPaiement;
use App\Models\FraisEleve;
use App\Models\Inscription;
use App\Models\Paiement;
use App\Models\Remise;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Soldes toujours calculés, jamais stockés (RG-FIN-06) :
 * solde = Σ frais dus − Σ remises actives − Σ paiements valides.
 * Retard (RG-FIN-09) = part échue non couverte : Σ dus échus − Σ remises sur lignes échues − Σ paiements valides
 * (les paiements sont imputés d'abord sur les échéances les plus anciennes).
 */
class SoldeService
{
    /**
     * Détail ligne par ligne d'une inscription.
     *
     * @return array{lignes: Collection<int, array{frais: FraisEleve, du: int, remises: int, paye: int, reste: int, echu: bool}>,
     *     du: int, remises: int, paye: int, solde: int, retard: int}
     */
    public function situation(Inscription $inscription, ?CarbonImmutable $aDate = null): array
    {
        $aDate ??= CarbonImmutable::today();
        $frais = $inscription->fraisEleves()->with([
            'remises' => fn ($q) => $q->whereNull('annulee_at'),
            'imputations' => fn ($q) => $q->whereHas('paiement', fn ($p) => $p->where('statut', StatutPaiement::Valide)),
        ])->orderBy('date_limite')->orderBy('id')->get();

        $lignes = $frais->map(function (FraisEleve $f) use ($aDate) {
            $remises = (int) $f->remises->sum('montant');
            $paye = (int) $f->imputations->sum('montant');

            return [
                'frais' => $f,
                'du' => (int) $f->montant_du,
                'remises' => $remises,
                'paye' => $paye,
                'reste' => (int) $f->montant_du - $remises - $paye,
                'echu' => $f->date_limite->lte($aDate),
            ];
        });

        $totaux = $this->soldes([$inscription->id], $aDate)->get($inscription->id) ?? $this->vide();

        return ['lignes' => $lignes] + $totaux;
    }

    /**
     * Soldes de plusieurs inscriptions en quatre requêtes groupées (listes, rapports).
     *
     * @param  iterable<int>  $inscriptionIds
     * @return Collection<int, array{du: int, remises: int, paye: int, solde: int, retard: int}>
     */
    public function soldes(iterable $inscriptionIds, ?CarbonImmutable $aDate = null): Collection
    {
        $ids = collect($inscriptionIds)->values();
        if ($ids->isEmpty()) {
            return collect();
        }
        // Fin de journée : la comparaison reste juste que la date soit stockée avec ou sans heure (SQLite / MySQL).
        $jour = ($aDate ?? CarbonImmutable::today())->toDateString().' 23:59:59';

        $dus = FraisEleve::query()->whereIn('inscription_id', $ids)->groupBy('inscription_id')
            ->selectRaw('inscription_id, SUM(montant_du) as du, SUM(CASE WHEN date_limite <= ? THEN montant_du ELSE 0 END) as du_echu', [$jour])
            ->get()->keyBy('inscription_id');
        $remises = Remise::query()->whereNull('remises.annulee_at')
            ->join('frais_eleves', 'frais_eleves.id', '=', 'remises.frais_eleve_id')
            ->whereIn('frais_eleves.inscription_id', $ids)->groupBy('frais_eleves.inscription_id')
            ->selectRaw('frais_eleves.inscription_id, SUM(remises.montant) as remises, SUM(CASE WHEN frais_eleves.date_limite <= ? THEN remises.montant ELSE 0 END) as remises_echues', [$jour])
            ->get()->keyBy('inscription_id');
        $payes = Paiement::query()->where('statut', StatutPaiement::Valide)->whereIn('inscription_id', $ids)
            ->groupBy('inscription_id')->selectRaw('inscription_id, SUM(montant) as paye')
            ->get()->keyBy('inscription_id');

        return $ids->mapWithKeys(function ($id) use ($dus, $remises, $payes) {
            $du = (int) ($dus[$id]->du ?? 0);
            $r = (int) ($remises[$id]->remises ?? 0);
            $p = (int) ($payes[$id]->paye ?? 0);
            $retard = (int) ($dus[$id]->du_echu ?? 0) - (int) ($remises[$id]->remises_echues ?? 0) - $p;

            return [$id => ['du' => $du, 'remises' => $r, 'paye' => $p, 'solde' => $du - $r - $p, 'retard' => max(0, $retard)]];
        });
    }

    /**
     * @return array{du: int, remises: int, paye: int, solde: int, retard: int}
     */
    private function vide(): array
    {
        return ['du' => 0, 'remises' => 0, 'paye' => 0, 'solde' => 0, 'retard' => 0];
    }
}
