<?php

namespace Database\Factories;

use App\Enums\StatutInscription;
use App\Enums\TypeInscription;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\Inscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Inscription>
 */
class InscriptionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'eleve_id' => Eleve::factory(),
            'classe_id' => Classe::factory(),
            // L'année de l'inscription est toujours celle de la classe.
            'annee_scolaire_id' => fn (array $attributs) => Classe::find($attributs['classe_id'])->annee_scolaire_id,
            'date_inscription' => now()->toDateString(),
            'type' => TypeInscription::Nouvelle,
            'statut' => StatutInscription::Inscrit,
        ];
    }
}
