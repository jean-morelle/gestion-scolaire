<?php

namespace Database\Seeders;

use App\Support\Autorisations;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Crée les rôles et permissions et applique la matrice d'Autorisations. Idempotent.
 */
class RolesEtPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (array_keys(Autorisations::PERMISSIONS) as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        // Le cache n'est pas invalidé automatiquement quand le seeder tourne sans événements de modèle.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Autorisations::matrice() as $role => $permissions) {
            Role::findOrCreate($role, 'web')->syncPermissions($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
