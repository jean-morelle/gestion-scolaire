<?php

namespace App\Policies;

use App\Models\Paiement;
use App\Models\User;

/**
 * Reçus : personnel financier, ou responsables de l'élève et élève lui-même (RG-PAR-02/03).
 */
class PaiementPolicy
{
    public function view(User $user, Paiement $paiement): bool
    {
        if ($user->can('finances.consulter') || $user->can('paiements.enregistrer')) {
            return true;
        }
        $eleve = $paiement->inscription->eleve;

        return ($eleve->user_id !== null && $eleve->user_id === $user->id)
            || (bool) $user->responsable?->estResponsableDe($eleve);
    }
}
