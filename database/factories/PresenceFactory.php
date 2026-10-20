<?php

namespace Database\Factories;

use App\Enums\StatutPresence;
use App\Models\Appel;
use App\Models\Inscription;
use App\Models\Presence;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Presence>
 */
class PresenceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'appel_id' => Appel::factory(),
            'inscription_id' => Inscription::factory(),
            'statut' => StatutPresence::Present,
        ];
    }
}
