<?php

namespace Database\Factories;

use App\Enums\Cycle;
use App\Models\Niveau;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Niveau>
 */
class NiveauFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'libelle' => fake()->unique()->bothify('Niveau ##??'),
            'cycle' => Cycle::College,
            'ordre' => fake()->numberBetween(1, 7),
        ];
    }
}
