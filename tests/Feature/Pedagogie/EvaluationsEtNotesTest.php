<?php

namespace Tests\Feature\Pedagogie;

use App\Enums\Role;
use App\Enums\StatutEvaluation;
use App\Enums\StatutPeriode;
use App\Models\Affectation;
use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\Enseignant;
use App\Models\Evaluation;
use App\Models\Inscription;
use App\Models\JournalActivite;
use App\Models\Matiere;
use App\Models\Note;
use App\Models\Periode;
use App\Models\Publication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EvaluationsEtNotesTest extends TestCase
{
    use RefreshDatabase;

    private Periode $periode;

    private Classe $classe;

    private Enseignant $prof;

    private Affectation $maths;

    /** @var \Illuminate\Support\Collection<int, Inscription> */
    private $inscriptions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initialiserApplication();

        $annee = AnneeScolaire::factory()->active()->create();
        $this->periode = Periode::factory()->create(['annee_scolaire_id' => $annee->id, 'date_debut' => now()->subMonth(), 'date_fin' => now()->addMonth()]);
        $this->classe = Classe::factory()->create(['annee_scolaire_id' => $annee->id, 'nom' => '6e A']);
        $this->prof = Enseignant::factory()->create(['user_id' => $this->utilisateur(Role::Enseignant)->id]);
        $this->maths = Affectation::factory()->create(['classe_id' => $this->classe->id, 'matiere_id' => Matiere::where('code', 'MATH')->value('id'), 'enseignant_id' => $this->prof->id]);
        $this->inscriptions = Inscription::factory()->count(3)->create(['classe_id' => $this->classe->id, 'date_inscription' => now()->subMonths(2)]);
    }

    private function evaluation(array $attributs = []): Evaluation
    {
        return Evaluation::factory()->create(array_merge([
            'affectation_id' => $this->maths->id, 'periode_id' => $this->periode->id, 'bareme' => 20, 'date_evaluation' => now()->toDateString(),
        ], $attributs));
    }

    /**
     * @param  list<array{0: ?string, 1?: bool}>  $saisies  une par inscription, dans l'ordre
     */
    private function saisir(Evaluation $evaluation, array $saisies, bool $valider = false): \Illuminate\Testing\TestResponse
    {
        $notes = [];
        foreach ($this->inscriptions->values() as $i => $inscription) {
            $notes[$inscription->id] = ['valeur' => $saisies[$i][0] ?? '', 'est_absent' => ($saisies[$i][1] ?? false) ? '1' : '0'];
        }

        return $this->put(route('evaluations.notes', $evaluation), ['notes' => $notes] + ($valider ? ['valider' => '1'] : []));
    }

    public function test_l_enseignant_cree_une_evaluation_pour_son_affectation(): void
    {
        $this->actingAs($this->prof->user);

        $this->post('/evaluations', [
            'affectation_id' => $this->maths->id, 'periode_id' => $this->periode->id, 'libelle' => 'Devoir n°1', 'type' => 'devoir',
            'date_evaluation' => now()->toDateString(), 'bareme' => '20', 'poids' => '1',
        ])->assertSessionHasNoErrors();

        $evaluation = Evaluation::firstOrFail();
        $this->assertSame(StatutEvaluation::Brouillon, $evaluation->statut);
        $this->assertSame($this->prof->user_id, $evaluation->created_by);

        // Date hors période.
        $this->post('/evaluations', [
            'affectation_id' => $this->maths->id, 'periode_id' => $this->periode->id, 'libelle' => 'Hors période', 'type' => 'devoir',
            'date_evaluation' => now()->addMonths(3)->toDateString(), 'bareme' => '20', 'poids' => '1',
        ])->assertSessionHasErrors('date_evaluation');
    }

    public function test_saisie_des_notes_avec_absence_et_virgule(): void
    {
        $this->actingAs($this->prof->user);
        $evaluation = $this->evaluation();

        $this->saisir($evaluation, [['12,5'], ['', true], ['18']])->assertSessionHasNoErrors()->assertSessionHas('succes');

        $notes = $evaluation->notes()->get()->keyBy('inscription_id');
        $this->assertSame('12.50', $notes[$this->inscriptions[0]->id]->valeur);
        $this->assertTrue($notes[$this->inscriptions[1]->id]->est_absent);
        $this->assertNull($notes[$this->inscriptions[1]->id]->valeur);

        // Effacer une note la supprime.
        $this->saisir($evaluation, [['12,5'], ['', true], ['']])->assertSessionHasNoErrors();
        $this->assertSame(2, $evaluation->notes()->count());
    }

    public function test_limites_du_bareme(): void
    {
        $this->actingAs($this->prof->user);
        $evaluation = $this->evaluation(['bareme' => 10]);

        foreach (['10,01', '11', '-1', 'abc', '9.999'] as $invalide) {
            $this->saisir($evaluation, [[$invalide], ['5'], ['5']])->assertSessionHasErrors("notes.{$this->inscriptions[0]->id}.valeur");
        }
        $this->assertSame(0, Note::count());

        $this->saisir($evaluation, [['10'], ['0'], ['9,99']])->assertSessionHasNoErrors();
        $this->assertSame(3, Note::count());

        // Le barème ne peut pas descendre sous une note saisie.
        $this->put(route('evaluations.update', $evaluation), [
            'periode_id' => $this->periode->id, 'libelle' => 'X', 'type' => 'devoir', 'date_evaluation' => now()->toDateString(), 'bareme' => '5', 'poids' => '1',
        ])->assertSessionHasErrors('bareme');
    }

    public function test_validation_exige_une_note_ou_une_absence_pour_chaque_eleve(): void
    {
        $this->actingAs($this->prof->user);
        $evaluation = $this->evaluation();

        $this->saisir($evaluation, [['12'], ['14'], ['']], valider: true)->assertSessionHasErrors('evaluation');
        $this->assertSame(StatutEvaluation::Brouillon, $evaluation->fresh()->statut);

        $this->saisir($evaluation, [['12'], ['14'], ['', true]], valider: true)->assertSessionHasNoErrors();
        $this->assertSame(StatutEvaluation::Validee, $evaluation->fresh()->statut);
        $this->assertTrue(JournalActivite::where('action', 'evaluation.validee')->exists());

        // Verrouillée : plus de saisie ni de modification.
        $this->saisir($evaluation, [['20'], ['20'], ['20']])->assertSessionHasErrors('evaluation');
        $this->assertSame('12.00', $evaluation->notes()->where('inscription_id', $this->inscriptions[0]->id)->value('valeur'));
    }

    public function test_deverrouillage_par_la_direction_et_historique_des_modifications(): void
    {
        $evaluation = $this->evaluation();
        foreach ($this->inscriptions as $inscription) {
            Note::factory()->create(['evaluation_id' => $evaluation->id, 'inscription_id' => $inscription->id, 'valeur' => 10]);
        }
        $evaluation->update(['statut' => StatutEvaluation::Validee, 'validee_at' => now()]);

        // L'enseignant ne peut pas déverrouiller.
        $this->actingAs($this->prof->user)->post(route('evaluations.deverrouiller', $evaluation), ['motif' => 'x'])->assertForbidden();

        $this->connecterEn(Role::Directeur);
        $this->post(route('evaluations.deverrouiller', $evaluation), ['motif' => ''])->assertSessionHasErrors('motif');
        $this->post(route('evaluations.deverrouiller', $evaluation), ['motif' => 'Erreur signalée par un parent'])->assertSessionHas('succes');
        $evaluation->refresh();
        $this->assertSame(StatutEvaluation::Brouillon, $evaluation->statut);
        $this->assertSame('Erreur signalée par un parent', JournalActivite::where('action', 'evaluation.deverrouillee')->firstOrFail()->nouvelles_valeurs['motif']);

        // RG-NOT-11 : l'ancienne valeur est conservée dans le journal.
        $this->actingAs($this->prof->user);
        $this->saisir($evaluation, [['15'], ['10'], ['10']])->assertSessionHasNoErrors();
        $journal = JournalActivite::where('action', 'notes.modifiees_apres_deverrouillage')->firstOrFail();
        $nom = $this->inscriptions[0]->eleve->nom_complet;
        $this->assertEquals(10, $journal->anciennes_valeurs[$nom]);
        $this->assertEquals(15, $journal->nouvelles_valeurs[$nom]);
        $this->assertCount(1, $journal->nouvelles_valeurs);
    }

    public function test_un_enseignant_ne_saisit_que_ses_affectations(): void
    {
        $autre = Enseignant::factory()->create(['user_id' => $this->utilisateur(Role::Enseignant)->id]);
        $evaluation = $this->evaluation();

        $this->actingAs($autre->user);
        $this->get(route('evaluations.show', $evaluation))->assertForbidden();
        $this->saisir($evaluation, [['20'], ['20'], ['20']])->assertForbidden();
        $this->get(route('evaluations.create', ['affectation' => $this->maths->id]))->assertForbidden();
        $this->post('/evaluations', ['affectation_id' => $this->maths->id])->assertForbidden();
        $this->get(route('resultats.affectation', $this->maths))->assertForbidden();
        $this->get('/evaluations')->assertOk()->assertDontSee($evaluation->libelle);

        // La direction consulte sans saisir.
        $this->connecterEn(Role::Directeur);
        $this->get(route('evaluations.show', $evaluation))->assertOk()->assertDontSee('Enregistrer les notes');
        $this->saisir($evaluation, [['20'], ['20'], ['20']])->assertForbidden();

        foreach ([Role::Secretaire, Role::Comptable, Role::Parent, Role::Eleve] as $role) {
            $this->connecterEn($role);
            $this->get('/evaluations')->assertForbidden();
            $this->get(route('evaluations.show', $evaluation))->assertForbidden();
        }
        $this->assertSame(0, Note::count());
    }

    public function test_periode_cloturee_ou_resultats_publies_bloquent_la_saisie(): void
    {
        $this->actingAs($this->prof->user);
        $evaluation = $this->evaluation();

        $this->periode->update(['statut' => StatutPeriode::Cloturee]);
        $this->saisir($evaluation, [['10'], ['10'], ['10']])->assertSessionHasErrors('evaluation');

        $this->periode->update(['statut' => StatutPeriode::Ouverte]);
        Publication::create(['classe_id' => $this->classe->id, 'periode_id' => $this->periode->id, 'publiee_par' => User::first()->id, 'publiee_at' => now()]);
        $this->saisir($evaluation, [['10'], ['10'], ['10']])->assertSessionHasErrors('evaluation');
        $this->assertSame(0, Note::count());
    }

    public function test_une_evaluation_notee_ne_peut_pas_etre_supprimee(): void
    {
        $this->actingAs($this->prof->user);
        $vide = $this->evaluation();
        $notee = $this->evaluation();
        Note::factory()->create(['evaluation_id' => $notee->id, 'inscription_id' => $this->inscriptions[0]->id]);

        $this->delete(route('evaluations.destroy', $notee))->assertSessionHasErrors('evaluation');
        $this->delete(route('evaluations.destroy', $vide))->assertRedirect(route('evaluations.index'));
        $this->assertModelMissing($vide);
        $this->assertModelExists($notee);
    }

    public function test_un_eleve_parti_avant_l_evaluation_n_est_pas_a_noter(): void
    {
        $this->actingAs($this->prof->user);
        $this->inscriptions[2]->update(['statut' => 'parti', 'date_sortie' => now()->subWeek(), 'motif_sortie' => 'Départ']);
        $evaluation = $this->evaluation();

        $this->get(route('evaluations.show', $evaluation))->assertOk()->assertDontSee($this->inscriptions[2]->eleve->nom_complet);
        // Validation possible sans lui.
        $this->put(route('evaluations.notes', $evaluation), ['valider' => '1', 'notes' => [
            $this->inscriptions[0]->id => ['valeur' => '10'], $this->inscriptions[1]->id => ['valeur' => '11'],
        ]])->assertSessionHasNoErrors();
        $this->assertTrue($evaluation->fresh()->estValidee());
        // Il ne peut pas être noté.
        $this->assertSame(2, Note::count());
    }

    public function test_les_ecrans_s_affichent(): void
    {
        $evaluation = $this->evaluation();
        Note::factory()->create(['evaluation_id' => $evaluation->id, 'inscription_id' => $this->inscriptions[0]->id, 'valeur' => 14]);
        $this->classe->update(['professeur_principal_id' => $this->prof->id]);

        $this->actingAs($this->prof->user);
        foreach (['/evaluations', route('evaluations.create', ['affectation' => $this->maths->id]), route('evaluations.show', $evaluation),
            route('evaluations.edit', $evaluation), route('resultats.affectation', $this->maths), '/resultats'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/resultats?format=csv')->assertOk();

        $this->connecterEn(Role::Directeur);
        $this->get('/evaluations')->assertOk()->assertSee($evaluation->libelle);
        $this->get('/resultats?classe='.$this->classe->id.'&periode='.$this->periode->id)->assertOk()->assertSee('14,00');

        // Enseignant sans classe principale : pas d'accès aux résultats de classe.
        $this->classe->update(['professeur_principal_id' => null]);
        $this->actingAs($this->prof->user)->get('/resultats')->assertForbidden();
    }
}
