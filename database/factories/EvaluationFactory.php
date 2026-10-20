<?php

namespace Database\Factories;

use App\Enums\StatutEvaluation;
use App\Enums\TypeEvaluation;
use App\Models\Affectation;
use App\Models\Evaluation;
use App\Models\Periode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Evaluation>
 */
class EvaluationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'affectation_id' => Affectation::factory(),
            'periode_id' => Periode::factory(),
            'libelle' => 'Devoir n°'.fake()->numberBetween(1, 5),
            'type' => TypeEvaluation::Devoir,
            'date_evaluation' => now()->toDateString(),
            'bareme' => 20,
            'poids' => 1,
            'statut' => StatutEvaluation::Brouillon,
        ];
    }

    public function validee(): static
    {
        return $this->state(fn () => ['statut' => StatutEvaluation::Validee, 'validee_at' => now()]);
    }
}
