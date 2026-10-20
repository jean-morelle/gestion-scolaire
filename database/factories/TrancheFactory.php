<?php

namespace Database\Factories;

use App\Models\GrilleFrais;
use App\Models\Tranche;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tranche>
 */
class TrancheFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'grille_frais_id' => GrilleFrais::factory(),
            'libelle' => '1re tranche',
            'montant' => 50000,
            'date_limite' => now()->addMonth()->toDateString(),
            'ordre' => 1,
        ];
    }
}
