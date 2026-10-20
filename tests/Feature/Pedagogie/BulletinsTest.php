<?php

namespace Tests\Feature\Pedagogie;

use App\Enums\Role;
use App\Enums\StatutEvaluation;
use App\Models\Affectation;
use App\Models\AnneeScolaire;
use App\Models\Appreciation;
use App\Models\Bulletin;
use App\Models\Classe;
use App\Models\Enseignant;
use App\Models\Evaluation;
use App\Models\Inscription;
use App\Models\JournalActivite;
use App\Models\Matiere;
use App\Models\Note;
use App\Models\Periode;
use App\Models\Publication;
use App\Models\Responsable;
use App\Models\User;
use App\Services\BulletinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BulletinsTest extends TestCase
{
    use RefreshDatabase;

    private AnneeScolaire $annee;

    private Periode $t1;

    private Periode $t2;

    private Classe $classe;

    private Affectation $maths;

    private Enseignant $prof;

    /** @var \Illuminate\Support\Collection<int, Inscription> */
    private $inscriptions;

    private Evaluation $devoir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initialiserApplication();

        $this->annee = AnneeScolaire::factory()->active()->create();
        $this->t1 = Periode::factory()->create(['annee_scolaire_id' => $this->annee->id, 'libelle' => '1er trimestre', 'ordre' => 1, 'date_debut' => now()->subMonths(4), 'date_fin' => now()->subMonths(2)]);
        $this->t2 = Periode::factory()->create(['annee_scolaire_id' => $this->annee->id, 'libelle' => '2e trimestre', 'ordre' => 2, 'date_debut' => now()->subMonths(2)->addDay(), 'date_fin' => now()->addMonth()]);
        $this->prof = Enseignant::factory()->create(['user_id' => $this->utilisateur(Role::Enseignant)->id]);
        $this->classe = Classe::factory()->create(['annee_scolaire_id' => $this->annee->id, 'nom' => '6e A', 'professeur_principal_id' => $this->prof->id]);
        $this->maths = Affectation::factory()->create(['classe_id' => $this->classe->id, 'matiere_id' => Matiere::where('code', 'MATH')->value('id'), 'coefficient' => 2, 'enseignant_id' => $this->prof->id]);
        $this->inscriptions = Inscription::factory()->count(2)->create(['classe_id' => $this->classe->id, 'date_inscription' => now()->subMonths(5)]);

        $this->devoir = Evaluation::factory()->validee()->create(['affectation_id' => $this->maths->id, 'periode_id' => $this->t2->id, 'date_evaluation' => now()->subWeek()]);
        Note::factory()->create(['evaluation_id' => $this->devoir->id, 'inscription_id' => $this->inscriptions[0]->id, 'valeur' => 15]);
        Note::factory()->create(['evaluation_id' => $this->devoir->id, 'inscription_id' => $this->inscriptions[1]->id, 'valeur' => 8]);
    }

    private function publier(array $donnees = []): \Illuminate\Testing\TestResponse
    {
        return $this->post(route('bulletins.publier', [$this->classe, $this->t2]), $donnees);
    }

    public function test_publication_fige_les_bulletins_et_notifie_les_familles(): void
    {
        $parent = $this->utilisateur(Role::Parent);
        $this->inscriptions[0]->eleve->responsables()->attach(Responsable::factory()->create(['user_id' => $parent->id]), ['lien' => 'pere']);
        Appreciation::create(['inscription_id' => $this->inscriptions[0]->id, 'periode_id' => $this->t2->id, 'appreciation_generale' => 'Très bon trimestre.', 'mention' => 'Tableau d\'honneur']);

        $this->connecterEn(Role::Directeur);
        $this->publier()->assertSessionHasNoErrors()->assertSessionHas('succes');

        $bulletin = Bulletin::where('inscription_id', $this->inscriptions[0]->id)->firstOrFail();
        $this->assertEqualsWithDelta(15.0, $bulletin->moyenne_generale, 1e-9);
        $this->assertSame([1, 2, 1], [$bulletin->rang, $bulletin->effectif, $bulletin->version]);
        $this->assertSame('Bien', $bulletin->mention);
        $this->assertSame('Tableau d\'honneur', $bulletin->distinction);
        $this->assertSame('Très bon trimestre.', $bulletin->appreciation);
        $this->assertSame('Mathématiques', $bulletin->lignes[0]['matiere']);
        $this->assertEqualsWithDelta(11.5, $bulletin->lignes[0]['moyenne_classe'], 1e-9);
        $this->assertSame(['absences' => 0, 'non_justifiees' => 0, 'retards' => 0, 'demi_journees' => 0], $bulletin->assiduite);

        $this->assertTrue($this->classe->estPublieePour($this->t2));
        $this->assertSame(1, $parent->notifications()->count());
        $this->assertSame(route('bulletins.pdf', $bulletin), $parent->notifications()->first()->data['lien']);
        $this->assertTrue(JournalActivite::where('action', 'bulletins.publies')->exists());

        // Figé : une note modifiée en base ne change pas le bulletin publié.
        Note::where('inscription_id', $this->inscriptions[0]->id)->update(['valeur' => 5]);
        $this->assertEqualsWithDelta(15.0, $bulletin->fresh()->moyenne_generale, 1e-9);

        // Pas de double publication.
        $this->publier()->assertSessionHasErrors('publication');
    }

    public function test_evaluations_en_brouillon_bloquent_sauf_publication_forcee(): void
    {
        $brouillon = Evaluation::factory()->create(['affectation_id' => $this->maths->id, 'periode_id' => $this->t2->id, 'date_evaluation' => now()->subDay()]);
        Note::factory()->create(['evaluation_id' => $brouillon->id, 'inscription_id' => $this->inscriptions[1]->id, 'valeur' => 20]);

        $this->connecterEn(Role::Directeur);
        $this->publier()->assertSessionHasErrors('publication');
        $this->assertSame(0, Bulletin::count());

        $this->publier(['forcer' => '1'])->assertSessionHasNoErrors();
        // Le brouillon (20/20) est exclu : la moyenne reste 8.
        $this->assertEqualsWithDelta(8.0, Bulletin::where('inscription_id', $this->inscriptions[1]->id)->value('moyenne_generale'), 1e-9);
        $this->assertStringContainsString('en forçant', JournalActivite::where('action', 'bulletins.publies')->value('description'));
    }

    public function test_rien_a_publier_sans_note_validee(): void
    {
        $this->devoir->update(['statut' => StatutEvaluation::Brouillon]);
        $this->connecterEn(Role::Directeur);

        $this->publier(['forcer' => '1'])->assertSessionHasErrors('publication');
        $this->assertSame(0, Publication::count());
    }

    public function test_depublier_corriger_et_republier_cree_une_version_historisee(): void
    {
        $parent = $this->utilisateur(Role::Parent);
        $this->inscriptions[1]->eleve->responsables()->attach(Responsable::factory()->create(['user_id' => $parent->id]), ['lien' => 'mere']);

        $this->connecterEn(Role::Directeur);
        $this->publier()->assertSessionHasNoErrors();
        $bulletin = Bulletin::where('inscription_id', $this->inscriptions[1]->id)->firstOrFail();

        $this->post(route('bulletins.depublier', [$this->classe, $this->t2]), ['motif' => ''])->assertSessionHasErrors('motif');
        $this->post(route('bulletins.depublier', [$this->classe, $this->t2]), ['motif' => 'Erreur de saisie en maths'])->assertSessionHas('succes');
        $this->assertFalse($this->classe->estPublieePour($this->t2));
        // Retiré : la famille n'y a plus accès.
        $this->actingAs($parent)->get(route('bulletins.pdf', $bulletin))->assertForbidden();

        // Correction : déverrouillage puis nouvelle note.
        $this->connecterEn(Role::Directeur);
        $this->post(route('evaluations.deverrouiller', $this->devoir), ['motif' => 'Correction'])->assertSessionHas('succes');
        Note::where('inscription_id', $this->inscriptions[1]->id)->update(['valeur' => 18]);
        $this->devoir->refresh()->update(['statut' => StatutEvaluation::Validee]);

        $this->publier()->assertSessionHasNoErrors();
        $bulletin->refresh();
        $this->assertSame(2, $bulletin->version);
        $this->assertEqualsWithDelta(18.0, $bulletin->moyenne_generale, 1e-9);
        $this->assertSame(1, $bulletin->rang);
        $this->assertSame(1, Bulletin::where('inscription_id', $this->inscriptions[1]->id)->count());

        $journal = JournalActivite::where('action', 'bulletins.republies')->firstOrFail();
        $nom = $this->inscriptions[1]->eleve->nom_complet;
        $this->assertEquals(['moyenne' => 8, 'rang' => 2], $journal->anciennes_valeurs[$nom]);
        $this->assertEquals(['moyenne' => 18, 'rang' => 1], $journal->nouvelles_valeurs[$nom]);
        $this->assertSame('Erreur de saisie en maths', JournalActivite::where('action', 'bulletins.depublies')->first()->nouvelles_valeurs['motif']);
        $this->assertSame(2, $parent->notifications()->count());
        // Créées dans la même seconde : on ne se fie pas à l'ordre.
        $this->assertSame(1, $parent->notifications->filter(fn ($n) => str_contains($n->data['titre'], 'corrigé'))->count());
    }

    public function test_moyenne_annuelle_sur_la_derniere_periode(): void
    {
        $this->connecterEn(Role::Directeur);
        // 1er trimestre : 10 et 12 ; 2e (dernier) : 15 et 8.
        $t1 = Evaluation::factory()->validee()->create(['affectation_id' => $this->maths->id, 'periode_id' => $this->t1->id, 'date_evaluation' => now()->subMonths(3)]);
        Note::factory()->create(['evaluation_id' => $t1->id, 'inscription_id' => $this->inscriptions[0]->id, 'valeur' => 10]);
        Note::factory()->create(['evaluation_id' => $t1->id, 'inscription_id' => $this->inscriptions[1]->id, 'valeur' => 12]);
        $this->post(route('bulletins.publier', [$this->classe, $this->t1]))->assertSessionHasNoErrors();
        $this->assertNull(Bulletin::where('periode_id', $this->t1->id)->first()->moyenne_annuelle);

        $this->publier()->assertSessionHasNoErrors();
        $b0 = Bulletin::where('periode_id', $this->t2->id)->where('inscription_id', $this->inscriptions[0]->id)->first();
        $b1 = Bulletin::where('periode_id', $this->t2->id)->where('inscription_id', $this->inscriptions[1]->id)->first();
        $this->assertEqualsWithDelta(12.5, $b0->moyenne_annuelle, 1e-9);
        $this->assertEqualsWithDelta(10.0, $b1->moyenne_annuelle, 1e-9);
        $this->assertSame([1, 2], [$b0->rang_annuel, $b1->rang_annuel]);
    }

    public function test_appreciations_par_le_professeur_principal_avant_publication(): void
    {
        $this->actingAs($this->prof->user);
        $this->get(route('bulletins.appreciations', [$this->classe, $this->t2]))->assertOk();
        $this->put(route('bulletins.appreciations.update', [$this->classe, $this->t2]), ['lignes' => [
            $this->inscriptions[0]->id => ['appreciation_generale' => 'Bon travail', 'mention' => 'Encouragements'],
            $this->inscriptions[1]->id => ['appreciation_generale' => '', 'mention' => ''],
        ]])->assertSessionHasNoErrors();
        $this->assertSame(1, Appreciation::count());

        // Un autre enseignant ne peut pas.
        $autre = Enseignant::factory()->create(['user_id' => $this->utilisateur(Role::Enseignant)->id]);
        $this->actingAs($autre->user)->get(route('bulletins.appreciations', [$this->classe, $this->t2]))->assertForbidden();

        // Après publication : figées.
        $this->connecterEn(Role::Directeur);
        $this->publier();
        $this->actingAs($this->prof->user)->put(route('bulletins.appreciations.update', [$this->classe, $this->t2]), ['lignes' => [
            $this->inscriptions[0]->id => ['appreciation_generale' => 'Modifiée'],
        ]])->assertSessionHasErrors('publication');
        $this->assertSame('Bon travail', Bulletin::where('inscription_id', $this->inscriptions[0]->id)->value('appreciation'));
    }

    public function test_acces_aux_bulletins_et_pdf(): void
    {
        $this->connecterEn(Role::Directeur);
        $this->publier();
        $bulletin = Bulletin::where('inscription_id', $this->inscriptions[0]->id)->firstOrFail();
        $eleve = $this->inscriptions[0]->eleve;

        $pdf = $this->get(route('bulletins.pdf', $bulletin))->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $this->get(route('bulletins.pdf-classe', [$this->classe, $this->t2]))->assertOk()->assertHeader('content-type', 'application/pdf');

        // La secrétaire consulte mais ne publie pas.
        $this->connecterEn(Role::Secretaire);
        $this->get('/bulletins')->assertOk();
        $this->get(route('bulletins.pdf', $bulletin))->assertOk();
        $this->post(route('bulletins.depublier', [$this->classe, $this->t2]), ['motif' => 'x'])->assertForbidden();

        // Parent de l'élève : oui ; autre parent : non ; élève lui-même : oui.
        $parent = $this->utilisateur(Role::Parent);
        $eleve->responsables()->attach(Responsable::factory()->create(['user_id' => $parent->id]), ['lien' => 'pere']);
        $this->actingAs($parent)->get(route('bulletins.pdf', $bulletin))->assertOk();
        $this->actingAs($parent)->get('/bulletins')->assertForbidden();
        $autreParent = $this->utilisateur(Role::Parent);
        Responsable::factory()->create(['user_id' => $autreParent->id]);
        $this->actingAs($autreParent)->get(route('bulletins.pdf', $bulletin))->assertForbidden();
        $compteEleve = $this->utilisateur(Role::Eleve);
        $eleve->update(['user_id' => $compteEleve->id]);
        $this->actingAs($compteEleve)->get(route('bulletins.pdf', $bulletin))->assertOk();
        $this->actingAs($compteEleve)->get(route('bulletins.pdf', Bulletin::where('inscription_id', $this->inscriptions[1]->id)->first()))->assertForbidden();

        // Enseignant non autorisé à publier.
        $this->actingAs($this->prof->user)->post(route('bulletins.publier', [$this->classe, $this->t1]))->assertForbidden();
    }

    public function test_les_ecrans_s_affichent(): void
    {
        $this->connecterEn(Role::Directeur);
        foreach (['/bulletins', route('bulletins.classe', [$this->classe, $this->t2]), route('bulletins.appreciations', [$this->classe, $this->t2])] as $url) {
            $this->get($url)->assertOk();
        }
        $this->publier();
        $this->get(route('bulletins.classe', [$this->classe, $this->t2]))->assertOk()->assertSee('Retirer pour correction');
        $this->get('/bulletins')->assertSee('Publiés le');

        // Période d'une autre année : 404.
        $autre = Periode::factory()->create();
        $this->get(route('bulletins.classe', [$this->classe, $autre]))->assertNotFound();
    }

    public function test_un_eleve_parti_pendant_la_periode_a_son_bulletin(): void
    {
        $this->inscriptions[1]->update(['statut' => 'parti', 'date_sortie' => now()->subDays(3), 'motif_sortie' => 'Départ']);
        $this->actingAs(User::factory()->create());

        $n = app(BulletinService::class)->publier($this->classe, $this->t2, User::first());
        $this->assertSame(2, $n);
    }
}
