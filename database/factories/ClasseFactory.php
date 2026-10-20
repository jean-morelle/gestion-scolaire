<?php

namespace Database\Factories;

use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\Niveau;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Classe>
 */
class ClasseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'annee_scolaire_id' => AnneeScolaire::factory(),
            'niveau_id' => Niveau::factory(),
            'nom' => fake()->unique()->bothify('Classe ##?'),
            'capacite' => 50,
        ];
    }
}
