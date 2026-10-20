<?php

namespace Database\Factories;

use App\Enums\StatutPeriode;
use App\Models\AnneeScolaire;
use App\Models\Periode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Periode>
 */
class PeriodeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'annee_scolaire_id' => AnneeScolaire::factory(),
            'libelle' => '1er trimestre',
            'ordre' => 1,
            'date_debut' => now()->startOfMonth(),
            'date_fin' => now()->addMonths(3),
            'statut' => StatutPeriode::Ouverte,
        ];
    }
}
