<?php

namespace Database\Factories;

use App\Models\Matiere;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Matiere>
 */
class MatiereFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('MAT-###'),
            'libelle' => fake()->words(2, true),
            'couleur' => fake()->hexColor(),
        ];
    }
}
