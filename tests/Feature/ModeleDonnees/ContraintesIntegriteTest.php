<?php

namespace Tests\Feature\ModeleDonnees;

use App\Models\Affectation;
use App\Models\AnneeScolaire;
use App\Models\Appel;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\Evaluation;
use App\Models\GrilleFrais;
use App\Models\Inscription;
use App\Models\Note;
use App\Models\Presence;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Vérifie que les règles d'unicité et d'intégrité sont garanties par la base elle-même.
 */
class ContraintesIntegriteTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_eleve_ne_peut_avoir_qu_une_inscription_par_annee(): void
    {
        $inscription = Inscription::factory()->create();
        $autreClasseMemeAnnee = Classe::factory()->create(['annee_scolaire_id' => $inscription->annee_scolaire_id]);

        $this->expectException(UniqueConstraintViolationException::class);

        Inscription::factory()->create([
            'eleve_id' => $inscription->eleve_id,
            'classe_id' => $autreClasseMemeAnnee->id,
        ]);
    }

    public function test_un_eleve_peut_etre_inscrit_sur_deux_annees_differentes(): void
    {
        $inscription = Inscription::factory()->create();
        $classeAnneeSuivante = Classe::factory()->create();

        $reinscription = Inscription::factory()->create([
            'eleve_id' => $inscription->eleve_id,
            'classe_id' => $classeAnneeSuivante->id,
        ]);

        $this->assertSame(2, $inscription->eleve->inscriptions()->count());
        $this->assertSame($classeAnneeSuivante->annee_scolaire_id, $reinscription->annee_scolaire_id);
    }

    public function test_le_matricule_est_unique(): void
    {
        Eleve::factory()->create(['matricule' => '2026-00001']);

        $this->expectException(UniqueConstraintViolationException::class);

        Eleve::factory()->create(['matricule' => '2026-00001']);
    }

    public function test_une_matiere_n_est_affectee_qu_une_fois_par_classe(): void
    {
        $affectation = Affectation::factory()->create();

        $this->expectException(UniqueConstraintViolationException::class);

        Affectation::factory()->create([
            'classe_id' => $affectation->classe_id,
            'matiere_id' => $affectation->matiere_id,
        ]);
    }

    public function test_un_eleve_n_a_qu_une_note_par_evaluation(): void
    {
        $note = Note::factory()->create();

        $this->expectException(UniqueConstraintViolationException::class);

        Note::factory()->create([
            'evaluation_id' => $note->evaluation_id,
            'inscription_id' => $note->inscription_id,
        ]);
    }

    public function test_un_seul_appel_par_classe_date_et_creneau(): void
    {
        $appel = Appel::factory()->create();

        $this->expectException(UniqueConstraintViolationException::class);

        Appel::factory()->create([
            'classe_id' => $appel->classe_id,
            'date_appel' => $appel->date_appel,
            'creneau' => $appel->creneau,
        ]);
    }

    public function test_un_eleve_n_a_qu_une_presence_par_appel(): void
    {
        $presence = Presence::factory()->create();

        $this->expectException(UniqueConstraintViolationException::class);

        Presence::factory()->create([
            'appel_id' => $presence->appel_id,
            'inscription_id' => $presence->inscription_id,
        ]);
    }

    public function test_une_grille_de_frais_est_unique_par_annee_niveau_type_et_categorie(): void
    {
        $grille = GrilleFrais::factory()->create();

        $this->expectException(UniqueConstraintViolationException::class);

        GrilleFrais::factory()->create($grille->only(['annee_scolaire_id', 'niveau_id', 'type_frais_id', 'categorie']));
    }

    public function test_un_eleve_inscrit_ne_peut_pas_etre_supprime(): void
    {
        $inscription = Inscription::factory()->create();

        $this->expectException(QueryException::class);

        $inscription->eleve->delete();
    }

    public function test_une_evaluation_notee_ne_peut_pas_etre_supprimee(): void
    {
        $note = Note::factory()->create();

        $this->expectException(QueryException::class);

        Evaluation::find($note->evaluation_id)->delete();
    }

    public function test_une_annee_avec_classes_ne_peut_pas_etre_supprimee(): void
    {
        $classe = Classe::factory()->create();

        $this->expectException(QueryException::class);

        AnneeScolaire::find($classe->annee_scolaire_id)->delete();
    }
}
