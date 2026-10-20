<?php

namespace Database\Factories;

use App\Enums\StatutAnnee;
use App\Models\AnneeScolaire;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AnneeScolaire>
 */
class AnneeScolaireFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Plage volontairement lointaine : jamais en collision avec les années écrites en dur dans les tests.
        $debut = fake()->unique()->numberBetween(2100, 2199);

        return [
            'libelle' => $debut.'-'.($debut + 1),
            'date_debut' => "{$debut}-09-01",
            'date_fin' => ($debut + 1).'-07-15',
            'est_active' => false,
            'statut' => StatutAnnee::EnCours,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['est_active' => true]);
    }
}
