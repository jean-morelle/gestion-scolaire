<?php

namespace App\Policies;

use App\Models\Bulletin;
use App\Models\User;

/**
 * Personnel autorisé : tous les bulletins. Familles et élève : uniquement les bulletins publiés
 * de leurs enfants / d'eux-mêmes (RG-PAR-02, RG-NOT-10).
 */
class BulletinPolicy
{
    public function view(User $user, Bulletin $bulletin): bool
    {
        if ($user->can('bulletins.consulter') || $user->can('bulletins.publier')) {
            return true;
        }
        if (! $bulletin->estVisibleDesFamilles()) {
            return false;
        }
        $eleve = $bulletin->inscription->eleve;

        return ($eleve->user_id !== null && $eleve->user_id === $user->id)
            || (bool) $user->responsable?->estResponsableDe($eleve);
    }
}
