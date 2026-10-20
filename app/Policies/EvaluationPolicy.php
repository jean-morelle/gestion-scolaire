<?php

namespace App\Policies;

use App\Models\Affectation;
use App\Models\Evaluation;
use App\Models\User;

/**
 * RG-ENS-02 : l'enseignant ne voit et ne saisit que les évaluations de ses affectations.
 * La direction et la vie scolaire consultent tout ; seule la direction déverrouille.
 */
class EvaluationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('notes.saisir') || $user->can('notes.consulter');
    }

    public function view(User $user, Evaluation $evaluation): bool
    {
        return $user->can('notes.consulter') || $this->update($user, $evaluation);
    }

    public function create(User $user, Affectation $affectation): bool
    {
        return $user->can('notes.saisir') && $affectation->enseignant_id !== null
            && $user->enseignant?->id === $affectation->enseignant_id;
    }

    /**
     * Modifier l'évaluation et saisir ses notes.
     */
    public function update(User $user, Evaluation $evaluation): bool
    {
        return $this->create($user, $evaluation->affectation);
    }

    public function valider(User $user, Evaluation $evaluation): bool
    {
        return $this->update($user, $evaluation) || $user->can('evaluations.deverrouiller');
    }

    public function deverrouiller(User $user, Evaluation $evaluation): bool
    {
        return $user->can('evaluations.deverrouiller');
    }
}
