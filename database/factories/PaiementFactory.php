<?php

namespace Database\Factories;

use App\Enums\ModePaiement;
use App\Enums\StatutPaiement;
use App\Models\Inscription;
use App\Models\Paiement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Paiement>
 */
class PaiementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'numero_recu' => fake()->unique()->numerify('REC-2026-######'),
            'inscription_id' => Inscription::factory(),
            'montant' => 25000,
            'date_paiement' => now()->toDateString(),
            'mode' => ModePaiement::Especes,
            'payeur_nom' => fake()->name(),
            'encaisse_par' => User::factory(),
            'statut' => StatutPaiement::Valide,
        ];
    }
}
