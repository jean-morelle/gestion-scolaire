<?php

namespace Database\Factories;

use App\Enums\Creneau;
use App\Models\Appel;
use App\Models\Classe;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appel>
 */
class AppelFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'classe_id' => Classe::factory(),
            'date_appel' => now()->toDateString(),
            'creneau' => Creneau::Matin,
        ];
    }
}
