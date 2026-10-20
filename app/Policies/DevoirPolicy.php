<?php

namespace App\Policies;

use App\Models\Affectation;
use App\Models\Devoir;
use App\Models\RemiseDevoir;
use App\Models\User;

/**
 * Devoirs et copies. Un élève ne voit jamais le travail d'un autre élève.
 */
class DevoirPolicy
{
    public function create(User $user, Affectation $affectation): bool
    {
        return $user->can('cours.gerer') && $affectation->enseignant_id !== null && $user->enseignant?->id === $affectation->enseignant_id;
    }

    /**
     * Gérer le devoir, suivre et corriger les copies.
     */
    public function update(User $user, Devoir $devoir): bool
    {
        return $this->create($user, $devoir->affectation);
    }

    public function view(User $user, Devoir $devoir): bool
    {
        if ($this->update($user, $devoir) || $user->can('rapports.scolaires')) {
            return true;
        }

        return $devoir->est_publie && CoursPolicy::eleveDeLaClasse($user, $devoir->affectation->classe_id);
    }

    public function remettre(User $user, Devoir $devoir): bool
    {
        return $devoir->est_publie && CoursPolicy::eleveDeLaClasse($user, $devoir->affectation->classe_id);
    }

    /**
     * Copie d'un élève : lui-même, l'enseignant du devoir, la direction / vie scolaire.
     */
    public function voirRemise(User $user, Devoir $devoir, RemiseDevoir $remise): bool
    {
        if ($remise->devoir_id !== $devoir->id) {
            return false;
        }

        return $this->update($user, $devoir)
            || $user->can('rapports.scolaires')
            || ($user->eleve !== null && $remise->inscription->eleve_id === $user->eleve->id);
    }
}
