<?php

namespace Database\Factories;

use App\Models\AnneeScolaire;
use App\Models\GrilleFrais;
use App\Models\Niveau;
use App\Models\TypeFrais;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GrilleFrais>
 */
class GrilleFraisFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'annee_scolaire_id' => AnneeScolaire::factory(),
            'niveau_id' => Niveau::factory(),
            'type_frais_id' => TypeFrais::factory(),
            'categorie' => '',
            'montant' => 150000,
        ];
    }
}
