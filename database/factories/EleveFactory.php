<?php

namespace Database\Factories;

use App\Enums\Sexe;
use App\Enums\StatutEleve;
use App\Models\Eleve;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Eleve>
 */
class EleveFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $sexe = fake()->randomElement(Sexe::cases());

        return [
            'matricule' => fake()->unique()->numerify('2026-#####'),
            'nom' => fake()->lastName(),
            'prenoms' => fake()->firstName($sexe === Sexe::Masculin ? 'male' : 'female'),
            'sexe' => $sexe,
            'date_naissance' => fake()->dateTimeBetween('-19 years', '-10 years')->format('Y-m-d'),
            'lieu_naissance' => fake()->city(),
            'statut' => StatutEleve::Actif,
        ];
    }
}
