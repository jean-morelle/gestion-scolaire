<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\Enseignant;
use App\Models\Inscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RechercheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initialiserApplication();
        $classe = Classe::factory()->create(['annee_scolaire_id' => AnneeScolaire::factory()->active()->create()->id, 'nom' => '6e Kara']);
        Inscription::factory()->create(['classe_id' => $classe->id])->eleve->update(['nom' => 'KARAMOKO', 'prenoms' => 'Awa']);
        $prof = $this->utilisateur(Role::Enseignant);
        $prof->update(['name' => 'Mme Karima Adjo']);
        Enseignant::factory()->create(['user_id' => $prof->id]);
    }

    public function test_les_resultats_respectent_les_droits(): void
    {
        // Directeur : élèves, enseignants et classes.
        $this->connecterEn(Role::Directeur);
        $categories = collect($this->getJson('/recherche?q=kar')->assertOk()->json('resultats'))->pluck('categorie')->unique()->sort()->values()->all();
        $this->assertSame(['Classes', 'Enseignants', 'Élèves'], $categories);

        // Secrétaire : pas les fiches enseignants.
        $this->connecterEn(Role::Secretaire);
        $this->assertNotContains('Enseignants', collect($this->getJson('/recherche?q=kar')->json('resultats'))->pluck('categorie')->all());

        // Enseignant, parent : aucune catégorie autorisée, donc aucun résultat.
        foreach ([Role::Enseignant, Role::Parent] as $role) {
            $this->connecterEn($role);
            $this->assertSame([], $this->getJson('/recherche?q=kar')->json('resultats'));
        }
    }

    public function test_page_de_resultats_et_barre_de_recherche(): void
    {
        $this->connecterEn(Role::Secretaire);
        $this->get('/recherche?q=awa')->assertOk()->assertSee('KARAMOKO Awa');
        $this->get('/recherche?q=a')->assertOk()->assertSee('au moins deux caractères');
        $this->get('/tableau-de-bord')->assertSee('Recherche globale', false);

        $this->connecterEn(Role::Enseignant);
        $this->get('/tableau-de-bord')->assertDontSee('Recherche globale', false);
    }
}
