<?php

namespace Database\Factories;

use App\Models\TypeFrais;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TypeFrais>
 */
class TypeFraisFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('FRAIS-###'),
            'libelle' => fake()->words(2, true),
            'est_obligatoire' => true,
        ];
    }
}
