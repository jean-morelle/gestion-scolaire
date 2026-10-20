<?php

namespace Database\Factories;

use App\Models\Enseignant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Enseignant>
 */
class EnseignantFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'matricule' => fake()->unique()->numerify('ENS-####'),
            'specialite' => fake()->randomElement(['Mathématiques', 'Français', 'Anglais', 'SVT', 'Physique-Chimie']),
            'est_actif' => true,
        ];
    }
}
