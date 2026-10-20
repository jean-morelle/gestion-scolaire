<?php

namespace App\Policies;

use App\Enums\StatutInscription;
use App\Models\Affectation;
use App\Models\Cours;
use App\Models\User;

/**
 * Cours : l'enseignant de l'affectation les gère ; la direction et la vie scolaire (statistiques scolaires)
 * les consultent ; un élève ne voit que les cours publiés de sa classe.
 */
class CoursPolicy
{
    public function create(User $user, Affectation $affectation): bool
    {
        return $user->can('cours.gerer') && $affectation->enseignant_id !== null && $user->enseignant?->id === $affectation->enseignant_id;
    }

    public function update(User $user, Cours $cours): bool
    {
        return $this->create($user, $cours->affectation);
    }

    public function view(User $user, Cours $cours): bool
    {
        if ($this->update($user, $cours) || $user->can('rapports.scolaires')) {
            return true;
        }

        return $cours->est_publie && self::eleveDeLaClasse($user, $cours->affectation->classe_id);
    }

    /**
     * Élève connecté actuellement inscrit dans la classe.
     */
    public static function eleveDeLaClasse(User $user, int $classeId): bool
    {
        return (bool) $user->eleve?->inscriptions()->where('classe_id', $classeId)->where('statut', StatutInscription::Inscrit)->exists();
    }
}
