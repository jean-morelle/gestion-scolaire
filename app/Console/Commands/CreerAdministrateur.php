<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RolesEtPermissionsSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreerAdministrateur extends Command
{
    protected $signature = 'ecole:creer-admin
                            {--nom= : Nom complet}
                            {--email= : Adresse e-mail (identifiant de connexion)}
                            {--password= : Mot de passe (sinon demandé de façon masquée)}';

    protected $description = 'Crée le compte administrateur technique (installation initiale)';

    public function handle(): int
    {
        $this->callSilently('db:seed', ['--class' => RolesEtPermissionsSeeder::class, '--force' => true]);

        $donnees = [
            'name' => $this->option('nom') ?: $this->ask('Nom complet'),
            'email' => $this->option('email') ?: $this->ask('Adresse e-mail'),
            'password' => $this->option('password') ?: $this->secret('Mot de passe (8 caractères minimum, lettres et chiffres)'),
        ];

        $validation = Validator::make($donnees, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', Password::min(8)->letters()->numbers()],
        ]);

        if ($validation->fails()) {
            foreach ($validation->errors()->all() as $erreur) {
                $this->error($erreur);
            }

            return self::FAILURE;
        }

        $user = User::create($donnees + ['est_actif' => true, 'doit_changer_mot_de_passe' => false]);
        $user->assignRole(Role::Administrateur->value);

        $this->info("Administrateur créé : {$user->email}");
        $this->line('Connectez-vous puis attribuez les autres rôles (directeur, secrétaire...) depuis Administration > Utilisateurs.');

        return self::SUCCESS;
    }
}
