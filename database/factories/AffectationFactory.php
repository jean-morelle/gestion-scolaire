<?php

namespace Database\Factories;

use App\Models\Affectation;
use App\Models\Classe;
use App\Models\Enseignant;
use App\Models\Matiere;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Affectation>
 */
class AffectationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'classe_id' => Classe::factory(),
            'matiere_id' => Matiere::factory(),
            'enseignant_id' => Enseignant::factory(),
            'coefficient' => fake()->randomElement([1, 2, 3, 4]),
            'ordre_bulletin' => 0,
        ];
    }
}
