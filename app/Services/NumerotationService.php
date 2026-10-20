<?php

namespace App\Services;

use App\Models\Sequence;
use Illuminate\Support\Facades\DB;

/**
 * Numéros uniques et séquentiels (matricules, reçus, dépenses).
 *
 * Le compteur est lu avec SELECT ... FOR UPDATE dans la transaction de l'appelant : si cette
 * transaction échoue, l'incrément est annulé avec elle, ce qui évite les trous et les doublons.
 */
class NumerotationService
{
    public function suivant(string $cle): int
    {
        return DB::transaction(function () use ($cle) {
            Sequence::query()->insertOrIgnore([
                'cle' => $cle, 'valeur' => 0, 'created_at' => now(), 'updated_at' => now(),
            ]);

            $sequence = Sequence::query()->where('cle', $cle)->lockForUpdate()->firstOrFail();
            $sequence->increment('valeur');

            return $sequence->valeur;
        });
    }

    /**
     * Matricule interne AAAA-NNNNN (année d'entrée + séquence), RG-ELV-01.
     */
    public function matricule(int $annee): string
    {
        return sprintf('%d-%05d', $annee, $this->suivant("matricule-{$annee}"));
    }

    /**
     * Numéro de reçu REC-AAAA-NNNNNN, RG-FIN-07.
     */
    public function recu(int $annee): string
    {
        return sprintf('REC-%d-%06d', $annee, $this->suivant("recu-{$annee}"));
    }

    public function depense(int $annee): string
    {
        return sprintf('DEP-%d-%05d', $annee, $this->suivant("depense-{$annee}"));
    }
}
