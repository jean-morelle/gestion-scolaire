<?php

namespace Database\Factories;

use App\Models\Responsable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Responsable>
 */
class ResponsableFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nom' => fake()->lastName(),
            'prenoms' => fake()->firstName(),
            'telephone' => fake()->numerify('07########'),
            'email' => fake()->optional()->safeEmail(),
            'profession' => fake()->jobTitle(),
        ];
    }
}
