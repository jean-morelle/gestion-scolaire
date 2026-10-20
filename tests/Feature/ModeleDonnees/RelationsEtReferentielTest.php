<?php

namespace Tests\Feature\ModeleDonnees;

use App\Enums\LienParente;
use App\Models\Eleve;
use App\Models\Etablissement;
use App\Models\Matiere;
use App\Models\Niveau;
use App\Models\Responsable;
use App\Models\TypeFrais;
use App\Models\User;
use Database\Seeders\ReferentielSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RelationsEtReferentielTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_referentiel_est_cree_et_peut_etre_relance_sans_doublon(): void
    {
        $this->seed(ReferentielSeeder::class);
        Etablissement::courant()->update(['nom' => 'Lycée de test']);

        $this->seed(ReferentielSeeder::class);

        $this->assertSame(1, Etablissement::count());
        $this->assertSame('Lycée de test', Etablissement::courant()->nom);
        $this->assertSame(13, Niveau::count());
        $this->assertSame(12, Matiere::count());
        $this->assertSame(2, TypeFrais::count());
        $this->assertSame(20, Etablissement::courant()->parametre('note_sur'));
    }

    public function test_un_responsable_peut_suivre_plusieurs_enfants_sans_voir_les_autres(): void
    {
        $parent = Responsable::factory()->create(['user_id' => User::factory()->create()->id]);
        [$enfant1, $enfant2] = Eleve::factory()->count(2)->create();
        $autreEleve = Eleve::factory()->create();

        $parent->eleves()->attach($enfant1, ['lien' => LienParente::Mere->value, 'est_contact_principal' => true]);
        $parent->eleves()->attach($enfant2, ['lien' => LienParente::Mere->value]);

        $this->assertTrue($parent->estResponsableDe($enfant1));
        $this->assertTrue($parent->estResponsableDe($enfant2));
        $this->assertFalse($parent->estResponsableDe($autreEleve));
        $this->assertTrue($parent->user->responsable->is($parent));
        $this->assertSame('mere', $enfant1->responsables->first()->pivot->lien);
    }
}
