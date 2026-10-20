<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Données indispensables à toute installation (aucune donnée fictive).
     * Le premier administrateur se crée avec `php artisan ecole:creer-admin`,
     * les données de démonstration avec `php artisan db:seed --class=DemonstrationSeeder`.
     */
    public function run(): void
    {
        $this->call([
            ReferentielSeeder::class,
            RolesEtPermissionsSeeder::class,
        ]);
    }
}
