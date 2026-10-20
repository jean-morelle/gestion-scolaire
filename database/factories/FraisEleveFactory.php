<?php

namespace Database\Factories;

use App\Models\FraisEleve;
use App\Models\Inscription;
use App\Models\TypeFrais;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FraisEleve>
 */
class FraisEleveFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'inscription_id' => Inscription::factory(),
            'type_frais_id' => TypeFrais::factory(),
            'libelle' => 'Scolarité - 1re tranche',
            'montant_du' => 50000,
            'date_limite' => now()->addMonth()->toDateString(),
        ];
    }
}
