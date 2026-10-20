<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\Enseignant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Création des comptes par l'établissement (pas d'auto-inscription) avec un mot de passe
 * temporaire à changer à la première connexion (RG-PAR-05).
 */
class CompteService
{
    public function __construct(private JournalService $journal) {}

    /**
     * @param  list<string>  $roles
     * @return array{0: User, 1: string} le compte et son mot de passe temporaire (affiché une seule fois)
     */
    public function creer(string $nom, ?string $email, ?string $telephone, array $roles, bool $actif = true): array
    {
        $motDePasse = $this->motDePasseTemporaire();

        $user = DB::transaction(function () use ($nom, $email, $telephone, $roles, $actif, $motDePasse) {
            $user = User::create([
                'name' => $nom,
                'email' => $email,
                'telephone' => $telephone,
                'password' => $motDePasse,
                'est_actif' => $actif,
                'doit_changer_mot_de_passe' => true,
            ]);
            $user->syncRoles($roles);
            $this->assurerFicheEnseignant($user);

            $this->journal->enregistrer('compte.cree', $user, "Compte créé pour {$nom}", null, [
                'email' => $email, 'telephone' => $telephone, 'roles' => $roles, 'est_actif' => $actif,
            ]);

            return $user;
        });

        return [$user, $motDePasse];
    }

    /**
     * @param  list<string>|null  $roles  null = rôles inchangés
     */
    public function modifier(User $user, array $donnees, ?array $roles = null): void
    {
        DB::transaction(function () use ($user, $donnees, $roles) {
            $avant = ['name' => $user->name, 'email' => $user->email, 'telephone' => $user->telephone,
                'est_actif' => $user->est_actif, 'roles' => $user->getRoleNames()->all()];

            $user->fill($donnees)->save();
            if ($roles !== null) {
                $user->syncRoles($roles);
                $this->assurerFicheEnseignant($user);
            }

            $apres = ['name' => $user->name, 'email' => $user->email, 'telephone' => $user->telephone,
                'est_actif' => $user->est_actif, 'roles' => $user->getRoleNames()->all()];

            if ($avant !== $apres) {
                $this->journal->enregistrer('compte.modifie', $user, "Compte de {$user->name} modifié", $avant, $apres);
            }
        });
    }

    public function reinitialiserMotDePasse(User $user): string
    {
        $motDePasse = $this->motDePasseTemporaire();
        $user->forceFill(['password' => $motDePasse, 'doit_changer_mot_de_passe' => true])->save();
        $this->journal->enregistrer('compte.mot_de_passe_reinitialise', $user, "Mot de passe de {$user->name} réinitialisé");

        return $motDePasse;
    }

    public function motDePasseTemporaire(): string
    {
        // Lisible pour être communiqué oralement ou sur papier (pas de caractères ambigus).
        return collect(str_split(Str::upper(Str::random(4)).random_int(1000, 9999).Str::lower(Str::random(2))))
            ->map(fn ($c) => strtr($c, ['0' => '7', 'O' => 'P', 'o' => 'p', 'I' => 'J', 'l' => 'k', '1' => '8']))
            ->join('');
    }

    private function assurerFicheEnseignant(User $user): void
    {
        if ($user->aRole(Role::Enseignant) && ! $user->enseignant()->exists()) {
            Enseignant::create(['user_id' => $user->id, 'est_actif' => true]);
        }
    }
}
