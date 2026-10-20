<?php

namespace Database\Factories;

use App\Models\Evaluation;
use App\Models\Inscription;
use App\Models\Note;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Note>
 */
class NoteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'evaluation_id' => Evaluation::factory(),
            'inscription_id' => Inscription::factory(),
            'valeur' => fake()->randomFloat(2, 0, 20),
            'est_absent' => false,
        ];
    }
}
