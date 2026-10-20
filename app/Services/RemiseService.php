<?php

namespace App\Services;

use App\Models\FraisEleve;
use App\Models\Remise;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * RG-FIN-03 : une remise (bourse, fratrie, enfant du personnel…) est un enregistrement distinct, motivé et attribué,
 * jamais une modification du montant dû. Elle s'annule, elle ne se supprime pas.
 */
class RemiseService
{
    public function __construct(private JournalService $journal, private SoldeService $soldes) {}

    public function accorder(FraisEleve $frais, int $montant, string $motif, User $user): Remise
    {
        return DB::transaction(function () use ($frais, $montant, $motif, $user) {
            $ligne = $this->ligne($frais);
            if ($montant <= 0 || $montant > $ligne['reste']) {
                throw ValidationException::withMessages(['montant' => 'La remise doit être comprise entre 1 et le reste à payer de la ligne ('.montant($ligne['reste']).').']);
            }

            $remise = Remise::create(['frais_eleve_id' => $frais->id, 'montant' => $montant, 'motif' => $motif, 'accordee_par' => $user->id]);
            $this->journal->enregistrer('remise.accordee', $remise,
                'Remise de '.montant($montant)." sur « {$frais->libelle} » ({$frais->inscription->eleve->nom_complet})", null, ['montant' => $montant, 'motif' => $motif]);

            return $remise;
        });
    }

    public function annuler(Remise $remise, string $motif, User $user): void
    {
        DB::transaction(function () use ($remise, $motif, $user) {
            if ($remise->annulee_at) {
                throw ValidationException::withMessages(['motif_annulation' => 'Cette remise est déjà annulée.']);
            }
            // Pas de risque de trop-perçu : les imputations d'une ligne ne dépassent jamais « dû − remises »,
            // donc a fortiori « dû − (remises − cette remise) ». Le reste à payer augmente simplement.
            $remise->update(['annulee_at' => now(), 'annulee_par' => $user->id, 'motif_annulation' => $motif]);
            $this->journal->enregistrer('remise.annulee', $remise, 'Remise de '.montant($remise->montant)." annulée sur « {$remise->fraisEleve->libelle} »",
                ['montant' => $remise->montant, 'motif' => $remise->motif], ['motif_annulation' => $motif]);
        });
    }

    /**
     * @return array{du: int, remises: int, paye: int, reste: int}
     */
    private function ligne(FraisEleve $frais): array
    {
        $ligne = $this->soldes->situation($frais->inscription)['lignes']->first(fn ($l) => $l['frais']->id === $frais->id);

        return array_intersect_key($ligne, array_flip(['du', 'remises', 'paye', 'reste']));
    }
}
