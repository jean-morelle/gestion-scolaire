<?php

namespace App\Policies;

use App\Models\Classe;
use App\Models\User;

/**
 * Liste des élèves et emploi du temps d'une classe : personnel autorisé, ou enseignant
 * qui y intervient (affectation ou professeur principal, RG-ENS-02).
 */
class ClassePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('classes.gerer') || $user->can('eleves.consulter');
    }

    public function view(User $user, Classe $classe): bool
    {
        return $this->viewAny($user) || (bool) $user->enseignant?->intervientDans($classe);
    }

    public function voirEmploiDuTemps(User $user, Classe $classe): bool
    {
        return $user->can('emplois_du_temps.gerer') || $this->view($user, $classe);
    }
}
