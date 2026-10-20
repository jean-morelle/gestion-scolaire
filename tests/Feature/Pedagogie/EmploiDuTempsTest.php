<?php

namespace Tests\Feature\Pedagogie;

use App\Enums\Role;
use App\Enums\StatutAnnee;
use App\Models\Affectation;
use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\EmploiDuTemps;
use App\Models\Enseignant;
use App\Models\Matiere;
use App\Services\EmploiDuTempsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmploiDuTempsTest extends TestCase
{
    use RefreshDatabase;

    private AnneeScolaire $annee;

    private Classe $sixiemeA;

    private Classe $sixiemeB;

    private Enseignant $prof;

    private Affectation $mathsA;

    private Affectation $francaisA;

    private Affectation $mathsB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initialiserApplication();

        $this->annee = AnneeScolaire::factory()->active()->create();
        $this->sixiemeA = Classe::factory()->create(['annee_scolaire_id' => $this->annee->id, 'nom' => '6e A']);
        $this->sixiemeB = Classe::factory()->create(['annee_scolaire_id' => $this->annee->id, 'nom' => '6e B']);
        $this->prof = Enseignant::factory()->create(['user_id' => $this->utilisateur(Role::Enseignant)->id]);
        $maths = Matiere::where('code', 'MATH')->firstOrFail();
        $francais = Matiere::where('code', 'FR')->firstOrFail();

        $this->mathsA = Affectation::factory()->create(['classe_id' => $this->sixiemeA->id, 'matiere_id' => $maths->id, 'enseignant_id' => $this->prof->id]);
        $this->francaisA = Affectation::factory()->create(['classe_id' => $this->sixiemeA->id, 'matiere_id' => $francais->id]);
        $this->mathsB = Affectation::factory()->create(['classe_id' => $this->sixiemeB->id, 'matiere_id' => $maths->id, 'enseignant_id' => $this->prof->id]);
    }

    private function creneau(Affectation $a, string $debut, string $fin, ?string $salle = null, int $jour = 1): \Illuminate\Testing\TestResponse
    {
        return $this->post(route('emplois-du-temps.store', $a->classe_id), [
            'affectation_id' => $a->id, 'jour' => $jour, 'heure_debut' => $debut, 'heure_fin' => $fin, 'salle' => $salle,
        ]);
    }

    public function test_ajout_et_detection_des_chevauchements(): void
    {
        $this->connecterEn(Role::Censeur);

        $this->creneau($this->mathsA, '08:00', '10:00', 'B12')->assertSessionHasNoErrors()->assertSessionHas('succes');
        $this->assertSame('08:00:00', EmploiDuTemps::first()->heure_debut);

        // Même classe, chevauchement partiel.
        $this->creneau($this->francaisA, '09:00', '11:00')->assertSessionHasErrors('heure_debut');
        // Même enseignant dans une autre classe.
        $this->creneau($this->mathsB, '09:30', '10:30')->assertSessionHasErrors('heure_debut');
        // Même salle.
        $autre = Affectation::factory()->create(['classe_id' => Classe::factory()->create(['annee_scolaire_id' => $this->annee->id])->id]);
        $this->creneau($autre, '08:30', '09:00', 'b12')->assertSessionHasErrors('heure_debut');

        // Créneaux contigus et autre jour : acceptés.
        $this->creneau($this->francaisA, '10:00', '11:00')->assertSessionHasNoErrors();
        $this->creneau($this->mathsB, '08:00', '10:00', 'B12', 2)->assertSessionHasNoErrors();
        // Fin avant début.
        $this->creneau($this->francaisA, '15:00', '14:00')->assertSessionHasErrors('heure_fin');

        $this->assertSame(3, EmploiDuTemps::count());
    }

    public function test_les_autres_annees_ne_creent_pas_de_conflit(): void
    {
        $this->connecterEn(Role::Censeur);
        $ancienne = AnneeScolaire::factory()->create();
        $ancienneClasse = Classe::factory()->create(['annee_scolaire_id' => $ancienne->id]);
        $ancienneAffectation = Affectation::factory()->create(['classe_id' => $ancienneClasse->id, 'enseignant_id' => $this->prof->id]);
        EmploiDuTemps::create(['affectation_id' => $ancienneAffectation->id, 'jour' => 1, 'heure_debut' => '08:00:00', 'heure_fin' => '10:00:00']);

        $this->creneau($this->mathsA, '08:00', '10:00')->assertSessionHasNoErrors();
    }

    public function test_une_affectation_d_une_autre_classe_est_refusee(): void
    {
        $this->connecterEn(Role::Censeur);

        $this->post(route('emplois-du-temps.store', $this->sixiemeA), [
            'affectation_id' => $this->mathsB->id, 'jour' => 1, 'heure_debut' => '08:00', 'heure_fin' => '09:00',
        ])->assertSessionHasErrors('affectation_id');
    }

    public function test_annee_cloturee_en_lecture_seule(): void
    {
        $this->connecterEn(Role::Censeur);
        $this->annee->update(['statut' => StatutAnnee::Cloturee]);

        $this->creneau($this->mathsA, '08:00', '09:00')->assertSessionHasErrors('affectation_id');
    }

    public function test_suppression_d_un_creneau(): void
    {
        $this->connecterEn(Role::Censeur);
        $creneau = EmploiDuTemps::create(['affectation_id' => $this->mathsA->id, 'jour' => 1, 'heure_debut' => '08:00:00', 'heure_fin' => '09:00:00']);

        $this->delete(route('emplois-du-temps.destroy', $creneau))->assertSessionHas('succes');
        $this->assertModelMissing($creneau);
    }

    public function test_changer_d_enseignant_signale_un_conflit_d_horaire(): void
    {
        $autreProf = Enseignant::factory()->create(['user_id' => $this->utilisateur(Role::Enseignant)->id]);
        $autreAffectation = Affectation::factory()->create(['classe_id' => $this->sixiemeB->id, 'enseignant_id' => $autreProf->id]);
        EmploiDuTemps::create(['affectation_id' => $autreAffectation->id, 'jour' => 1, 'heure_debut' => '08:00:00', 'heure_fin' => '09:00:00']);
        EmploiDuTemps::create(['affectation_id' => $this->francaisA->id, 'jour' => 1, 'heure_debut' => '08:00:00', 'heure_fin' => '09:00:00']);

        $this->connecterEn(Role::Directeur);
        $this->put(route('affectations.update', $this->sixiemeA), ['lignes' => [
            $this->francaisA->id => ['enseignant_id' => $autreProf->id, 'coefficient' => 2, 'ordre_bulletin' => 1],
        ]])->assertSessionHas('succes')->assertSessionHas('avertissement');
    }

    public function test_consultation_selon_le_role(): void
    {
        EmploiDuTemps::create(['affectation_id' => $this->mathsA->id, 'jour' => 1, 'heure_debut' => '08:00:00', 'heure_fin' => '10:00:00', 'salle' => 'B12']);

        // L'enseignant voit son emploi du temps et celui de ses classes, sans pouvoir le modifier.
        $this->actingAs($this->prof->user);
        $this->get('/emplois-du-temps')->assertRedirect(route('emplois-du-temps.enseignant', $this->prof));
        $this->get(route('emplois-du-temps.enseignant', $this->prof))->assertOk()->assertSee('08:00 - 10:00')->assertSee('6e A');
        $this->get(route('emplois-du-temps.classe', $this->sixiemeA))->assertOk()->assertDontSee('Ajouter un créneau');
        $this->creneau($this->mathsA, '14:00', '15:00')->assertForbidden();

        $autreProf = Enseignant::factory()->create(['user_id' => $this->utilisateur(Role::Enseignant)->id]);
        $this->get(route('emplois-du-temps.enseignant', $autreProf))->assertForbidden();
        $autreClasse = Classe::factory()->create(['annee_scolaire_id' => $this->annee->id]);
        $this->get(route('emplois-du-temps.classe', $autreClasse))->assertForbidden();

        // Le censeur construit ; la secrétaire consulte.
        $this->connecterEn(Role::Censeur);
        $this->get('/emplois-du-temps')->assertOk();
        $this->get(route('emplois-du-temps.classe', $this->sixiemeA))->assertOk()->assertSee('Ajouter un créneau');
        $this->connecterEn(Role::Secretaire);
        $this->get(route('emplois-du-temps.classe', $this->sixiemeA))->assertOk()->assertDontSee('Ajouter un créneau');

        $this->connecterEn(Role::Parent);
        $this->get('/emplois-du-temps')->assertForbidden();
        $this->get(route('emplois-du-temps.classe', $this->sixiemeA))->assertForbidden();
    }

    public function test_volume_horaire_et_normalisation_des_heures(): void
    {
        $this->assertSame('08:05:00', EmploiDuTempsService::heure('8:05'));
        $creneaux = collect([
            new EmploiDuTemps(['heure_debut' => '08:00:00', 'heure_fin' => '10:00:00']),
            new EmploiDuTemps(['heure_debut' => '14:00:00', 'heure_fin' => '14:30:00']),
        ]);
        $this->assertSame(2.5, EmploiDuTempsService::volumeHoraire($creneaux));
    }
}
