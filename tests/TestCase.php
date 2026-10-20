<?php

namespace Tests;

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\ReferentielSeeder;
use Database\Seeders\RolesEtPermissionsSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    /**
     * Référentiel (établissement, niveaux, matières) + rôles et permissions.
     */
    protected function initialiserApplication(): void
    {
        $this->seed([ReferentielSeeder::class, RolesEtPermissionsSeeder::class]);
    }

    protected function utilisateur(Role ...$roles): User
    {
        $user = User::factory()->create();
        $user->syncRoles(array_map(fn (Role $r) => $r->value, $roles));

        return $user;
    }

    protected function connecterEn(Role ...$roles): User
    {
        $user = $this->utilisateur(...$roles);
        $this->actingAs($user);

        return $user;
    }
}
