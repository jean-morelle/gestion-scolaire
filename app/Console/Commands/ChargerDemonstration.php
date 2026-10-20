<?php

namespace App\Console\Commands;

use Database\Seeders\DemonstrationSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Crée (ou recrée) une base de démonstration distincte et la remplit de données fictives.
 * La base de travail configurée dans .env n'est jamais touchée.
 */
class ChargerDemonstration extends Command
{
    protected $signature = 'ecole:demo {--base=gestion-ecole-demo : Nom de la base de démonstration (doit contenir « demo »)}';

    protected $description = 'Charge les données de démonstration fictives dans une base MySQL séparée';

    public function handle(): int
    {
        $base = (string) $this->option('base');
        $connexion = config('database.default');
        if (! str_contains(mb_strtolower($base), 'demo') || ! preg_match('/^[A-Za-z0-9_-]+$/', $base)) {
            $this->error('Le nom de la base doit contenir « demo » et ne comporter que des lettres, chiffres, « - » ou « _ ».');

            return self::FAILURE;
        }
        if ($base === config("database.connections.{$connexion}.database")) {
            $this->error('Cette base est celle configurée dans .env : choisissez une base de démonstration distincte.');

            return self::FAILURE;
        }
        if (! $this->confirm("La base « {$base} » va être (re)créée et remplie de données fictives. Continuer ?", true)) {
            return self::FAILURE;
        }

        // Création de la base sur le même serveur, puis bascule de la connexion vers elle pour cette commande.
        DB::connection()->statement("CREATE DATABASE IF NOT EXISTS `{$base}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        config(["database.connections.{$connexion}.database" => $base]);
        DB::purge($connexion);

        $this->info('Création des tables…');
        Artisan::call('migrate:fresh', ['--force' => true]);
        $this->info('Chargement des données fictives (quelques dizaines de secondes)…');
        Artisan::call('db:seed', ['--class' => DemonstrationSeeder::class, '--force' => true]);

        $mdp = DemonstrationSeeder::MOT_DE_PASSE;
        $this->newLine();
        $this->info("Base « {$base} » prête. Pour l'utiliser, mettez DB_DATABASE={$base} dans .env (puis php artisan config:clear).");
        $this->table(['Rôle', 'Identifiant', 'Mot de passe'], [
            ['Administrateur technique', 'admin@demo.test', $mdp],
            ['Directeur', 'direction@demo.test', $mdp],
            ['Secrétaire', 'secretariat@demo.test', $mdp],
            ['Censeur', 'censeur@demo.test', $mdp],
            ['Comptable', 'comptabilite@demo.test', $mdp],
            ['Enseignant (maths, prof. principal 6e A)', 'prof.math@demo.test', $mdp],
            ['Parent (6e A)', DB::table('users')->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
                ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')->where('roles.name', 'parent')->value('users.telephone'), $mdp],
            ['Élève (6e A)', DB::table('eleves')->whereNotNull('user_id')->value('matricule'), $mdp],
        ]);
        $this->warn('Données entièrement fictives : ne pas utiliser en production.');

        return self::SUCCESS;
    }
}
