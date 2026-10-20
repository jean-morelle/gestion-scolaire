<?php

namespace App\Policies;

use App\Models\Eleve;
use App\Models\User;

/**
 * Accès au dossier d'un élève : le personnel autorisé, ses responsables légaux (RG-PAR-02)
 * et l'élève lui-même. Les pièces du dossier sont réservées au personnel.
 */
class ElevePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('eleves.consulter');
    }

    public function view(User $user, Eleve $eleve): bool
    {
        if ($user->can('eleves.consulter')) {
            return true;
        }
        if ($eleve->user_id !== null && $eleve->user_id === $user->id) {
            return true;
        }

        return (bool) $user->responsable?->estResponsableDe($eleve);
    }

    /**
     * Espace famille / élève : uniquement ses enfants (lien responsable enregistré, RG-PAR-02) ou soi-même.
     * Le personnel n'utilise pas ces écrans : il a ses propres accès.
     */
    public function suivre(User $user, Eleve $eleve): bool
    {
        return ($eleve->user_id !== null && $eleve->user_id === $user->id)
            || (bool) $user->responsable?->estResponsableDe($eleve);
    }

    /**
     * Frais, paiements et soldes : réservés aux responsables (l'élève ne voit pas la situation financière).
     */
    public function suivreFinances(User $user, Eleve $eleve): bool
    {
        return (bool) $user->responsable?->estResponsableDe($eleve);
    }

    public function voirDossier(User $user, Eleve $eleve): bool
    {
        return $user->can('eleves.consulter');
    }

    public function create(User $user): bool
    {
        return $user->can('eleves.gerer');
    }

    public function update(User $user, Eleve $eleve): bool
    {
        return $user->can('eleves.gerer');
    }

    public function delete(User $user, Eleve $eleve): bool
    {
        return $user->can('eleves.gerer');
    }
}
