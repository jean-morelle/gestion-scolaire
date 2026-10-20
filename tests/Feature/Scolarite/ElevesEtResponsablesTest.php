<?php

namespace Tests\Feature\Scolarite;

use App\Enums\Role;
use App\Enums\StatutEleve;
use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\Inscription;
use App\Models\JournalActivite;
use App\Models\Niveau;
use App\Models\Responsable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ElevesEtResponsablesTest extends TestCase
{
    use RefreshDatabase;

    private AnneeScolaire $annee;

    private Classe $classe;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initialiserApplication();
        Storage::fake('local');

        $this->annee = AnneeScolaire::factory()->active()->create(['libelle' => '2026-2027', 'date_debut' => '2026-09-01', 'date_fin' => '2027-07-15']);
        $this->classe = Classe::factory()->create(['annee_scolaire_id' => $this->annee->id, 'niveau_id' => Niveau::where('libelle', '6e')->value('id'), 'nom' => '6e A']);
    }

    private function donneesEleve(array $surcharges = []): array
    {
        return array_replace_recursive([
            'nom' => 'koffi',
            'prenoms' => 'ama esther',
            'sexe' => 'F',
            'date_naissance' => '2014-03-12',
            'lieu_naissance' => 'Lomé',
            'responsable' => [
                'mode' => 'nouveau', 'nom' => 'Koffi', 'prenoms' => 'Kodjo', 'telephone' => '90 11 22 33', 'lien' => 'pere',
            ],
        ], $surcharges);
    }

    public function test_la_secretaire_cree_un_eleve_avec_responsable_et_inscription(): void
    {
        $this->connecterEn(Role::Secretaire);

        $reponse = $this->post('/eleves', $this->donneesEleve([
            'classe_id' => $this->classe->id, 'date_inscription' => '2026-09-02', // create() plutôt que image() : ne dépend pas de l'extension GD.
            'photo' => UploadedFile::fake()->create('photo.jpg', 120, 'image/jpeg'),
        ]));

        $eleve = Eleve::firstOrFail();
        $reponse->assertSessionHasNoErrors()->assertRedirect(route('eleves.show', $eleve));

        $this->assertSame('KOFFI', $eleve->nom);
        $this->assertSame('Ama Esther', $eleve->prenoms);
        $this->assertSame('2026-00001', $eleve->matricule);
        $this->assertNotNull($eleve->photo_chemin);
        Storage::disk('local')->assertExists($eleve->photo_chemin);

        $responsable = $eleve->responsables()->first();
        $this->assertSame('90112233', $responsable->telephone);
        $this->assertTrue((bool) $responsable->pivot->est_contact_principal);

        $inscription = $eleve->inscriptions()->first();
        $this->assertSame($this->classe->id, $inscription->classe_id);
        $this->assertSame('nouvelle', $inscription->type->value);
        $this->assertTrue(JournalActivite::where('action', 'eleve.cree')->exists());
        $this->assertTrue(JournalActivite::where('action', 'responsable.rattache')->exists());

        $this->get(route('eleves.show', $eleve))->assertOk()->assertSee('KOFFI Ama Esther')->assertSee('6e A');
    }

    public function test_les_matricules_sont_uniques_et_sequentiels(): void
    {
        $this->connecterEn(Role::Secretaire);

        $this->post('/eleves', $this->donneesEleve())->assertSessionHasNoErrors();
        $this->post('/eleves', $this->donneesEleve(['nom' => 'Mensah', 'responsable' => ['telephone' => '90445566']]))->assertSessionHasNoErrors();

        $this->assertSame(['2026-00001', '2026-00002'], Eleve::orderBy('id')->pluck('matricule')->all());
    }

    public function test_un_responsable_avec_telephone_est_obligatoire(): void
    {
        $this->connecterEn(Role::Secretaire);

        $this->post('/eleves', $this->donneesEleve(['responsable' => ['telephone' => '']]))
            ->assertSessionHasErrors('responsable.telephone');
        $this->post('/eleves', $this->donneesEleve(['responsable' => ['mode' => 'existant', 'telephone_existant' => '99999999']]))
            ->assertSessionHasErrors('responsable.telephone_existant');

        $this->assertSame(0, Eleve::count());
    }

    public function test_un_frere_reutilise_le_responsable_existant(): void
    {
        $this->connecterEn(Role::Secretaire);
        $this->post('/eleves', $this->donneesEleve())->assertSessionHasNoErrors();

        $this->post('/eleves', $this->donneesEleve([
            'prenoms' => 'Yao', 'sexe' => 'M',
            'responsable' => ['mode' => 'existant', 'telephone_existant' => '90112233', 'nom' => null, 'prenoms' => null, 'telephone' => null],
        ]))->assertSessionHasNoErrors();

        $this->assertSame(1, Responsable::count());
        $this->assertCount(2, Responsable::first()->eleves);
    }

    public function test_modifier_un_eleve_est_journalise(): void
    {
        $this->connecterEn(Role::Secretaire);
        $eleve = Eleve::factory()->create();
        $eleve->responsables()->attach(Responsable::factory()->create(), ['lien' => 'mere']);

        $this->put(route('eleves.update', $eleve), [
            'nom' => $eleve->nom, 'prenoms' => $eleve->prenoms, 'sexe' => $eleve->sexe->value,
            'date_naissance' => '2013-01-05', 'lieu_naissance' => 'Kara',
        ])->assertSessionHasNoErrors()->assertRedirect(route('eleves.show', $eleve));

        $this->assertSame('Kara', $eleve->fresh()->lieu_naissance);
        $journal = JournalActivite::where('action', 'eleve.modifie')->firstOrFail();
        $this->assertSame('Kara', $journal->nouvelles_valeurs['lieu_naissance']);
    }

    public function test_un_eleve_inscrit_ne_peut_pas_etre_supprime_mais_peut_etre_archive(): void
    {
        $this->connecterEn(Role::Secretaire);
        $inscription = Inscription::factory()->create(['classe_id' => $this->classe->id]);
        $eleve = $inscription->eleve;

        $this->delete(route('eleves.destroy', $eleve))->assertSessionHas('erreur');
        $this->assertModelExists($eleve);

        // Inscrit pour l'année en cours : départ obligatoire avant archivage.
        $this->post(route('eleves.archiver', $eleve))->assertSessionHas('erreur');
        $inscription->update(['statut' => 'parti', 'date_sortie' => now(), 'motif_sortie' => 'Déménagement']);
        $this->post(route('eleves.archiver', $eleve))->assertSessionHas('succes');
        $this->assertSame(StatutEleve::Archive, $eleve->fresh()->statut);

        $sansInscription = Eleve::factory()->create();
        $this->delete(route('eleves.destroy', $sansInscription))->assertRedirect(route('eleves.index'));
        $this->assertModelMissing($sansInscription);
    }

    public function test_recherche_filtres_et_export_de_la_liste(): void
    {
        $this->connecterEn(Role::Secretaire);
        $ama = Eleve::factory()->create(['nom' => 'KOFFI', 'prenoms' => 'Ama', 'sexe' => 'F']);
        Eleve::factory()->create(['nom' => 'MENSAH', 'prenoms' => 'Yao', 'sexe' => 'M']);
        Inscription::factory()->create(['eleve_id' => $ama->id, 'classe_id' => $this->classe->id]);

        $this->get('/eleves?q=koffi+ama')->assertOk()->assertSee('KOFFI Ama')->assertDontSee('MENSAH Yao');
        $this->get('/eleves?classe='.$this->classe->id)->assertSee('KOFFI Ama')->assertDontSee('MENSAH Yao');
        $this->get('/eleves?inscription=non_inscrit')->assertSee('MENSAH Yao')->assertDontSee('KOFFI Ama');

        $csv = $this->get('/eleves?format=csv')->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8')->streamedContent();
        $this->assertStringContainsString('KOFFI;Ama', $csv);
        $this->assertStringContainsString('6e A', $csv);
    }

    public function test_detacher_le_dernier_responsable_est_refuse(): void
    {
        $this->connecterEn(Role::Secretaire);
        $eleve = Eleve::factory()->create();
        $pere = Responsable::factory()->create();
        $eleve->responsables()->attach($pere, ['lien' => 'pere']);

        $this->delete(route('responsables.destroy', [$eleve, $pere]))->assertSessionHas('erreur');
        $this->assertCount(1, $eleve->responsables()->get());

        $this->post(route('responsables.store', $eleve), [
            'mode' => 'nouveau', 'nom' => 'Afi', 'prenoms' => 'Akossiwa', 'telephone' => '91000000', 'lien' => 'mere', 'est_contact_principal' => '1',
        ])->assertSessionHasNoErrors();
        $this->assertCount(2, $eleve->responsables()->get());
        // Un seul contact principal.
        $this->assertSame(1, $eleve->responsables()->wherePivot('est_contact_principal', true)->count());

        $this->delete(route('responsables.destroy', [$eleve, $pere]))->assertSessionHas('succes');
        $this->assertTrue(JournalActivite::where('action', 'responsable.detache')->exists());
    }

    public function test_creer_le_compte_parent_d_un_responsable(): void
    {
        $this->connecterEn(Role::Secretaire);
        $eleve = Eleve::factory()->create();
        $responsable = Responsable::factory()->create(['telephone' => '92000000', 'email' => null]);
        $eleve->responsables()->attach($responsable, ['lien' => 'mere']);

        $this->post(route('responsables.compte', [$eleve, $responsable]))->assertSessionHas('succes');

        $user = $responsable->fresh()->user;
        $this->assertNotNull($user);
        $this->assertSame('92000000', $user->telephone);
        $this->assertTrue($user->aRole(Role::Parent));
        $this->assertTrue($user->doit_changer_mot_de_passe);
        $this->assertTrue(JournalActivite::where('action', 'responsable.compte_cree')->exists());

        // Pas de second compte.
        $this->post(route('responsables.compte', [$eleve, $responsable]))->assertSessionHas('erreur');
        $this->assertSame(1, User::where('telephone', '92000000')->count());
    }

    public function test_un_responsable_d_un_autre_eleve_ne_peut_pas_etre_modifie_par_cette_fiche(): void
    {
        $this->connecterEn(Role::Secretaire);
        $eleve = Eleve::factory()->create();
        $etranger = Responsable::factory()->create();

        $this->get(route('responsables.edit', [$eleve, $etranger]))->assertNotFound();
        $this->post(route('responsables.compte', [$eleve, $etranger]))->assertNotFound();
    }

    public function test_les_pieces_du_dossier_sont_privees(): void
    {
        $secretaire = $this->connecterEn(Role::Secretaire);
        $eleve = Eleve::factory()->create();
        $responsable = Responsable::factory()->create();
        $eleve->responsables()->attach($responsable, ['lien' => 'pere']);

        $this->post(route('documents.store', $eleve), [
            'libelle' => 'Acte de naissance', 'fichier' => UploadedFile::fake()->create('acte.pdf', 200, 'application/pdf'),
        ])->assertSessionHasNoErrors();
        $document = $eleve->documents()->firstOrFail();
        Storage::disk('local')->assertExists($document->fichier_chemin);
        $this->assertStringNotContainsString('public', $document->fichier_chemin);

        $this->post(route('documents.store', $eleve), [
            'libelle' => 'Script', 'fichier' => UploadedFile::fake()->create('script.php', 10, 'application/x-php'),
        ])->assertSessionHasErrors('fichier');

        $this->get(route('documents.show', [$eleve, $document]))->assertOk()->assertDownload('acte.pdf');

        // Un parent (même responsable de l'élève) et un enseignant n'y ont pas accès.
        $parent = $this->utilisateur(Role::Parent);
        $responsable->update(['user_id' => $parent->id]);
        $this->actingAs($parent)->get(route('documents.show', [$eleve, $document]))->assertForbidden();
        $this->actingAs($this->utilisateur(Role::Enseignant))->get(route('documents.show', [$eleve, $document]))->assertForbidden();

        // Document d'un autre élève : la liaison imbriquée est vérifiée.
        $autre = Eleve::factory()->create();
        $this->actingAs($secretaire)->get(route('documents.show', [$autre, $document]))->assertNotFound();
    }

    public function test_la_photo_n_est_visible_que_par_les_personnes_autorisees(): void
    {
        $this->connecterEn(Role::Secretaire);
        $eleve = Eleve::factory()->create();
        Storage::disk('local')->put("eleves/{$eleve->id}/photo.jpg", 'image');
        $eleve->update(['photo_chemin' => "eleves/{$eleve->id}/photo.jpg"]);

        $this->get(route('eleves.photo', $eleve))->assertOk();

        $parent = $this->utilisateur(Role::Parent);
        $responsable = Responsable::factory()->create(['user_id' => $parent->id]);
        $this->actingAs($parent)->get(route('eleves.photo', $eleve))->assertForbidden();
        $eleve->responsables()->attach($responsable, ['lien' => 'pere']);
        $this->actingAs($parent)->get(route('eleves.photo', $eleve))->assertOk();
    }

    public function test_tous_les_ecrans_du_module_s_affichent(): void
    {
        $inscription = Inscription::factory()->create(['classe_id' => $this->classe->id, 'redoublant' => true]);
        $eleve = $inscription->eleve;
        $responsable = Responsable::factory()->create();
        $eleve->responsables()->attach($responsable, ['lien' => 'mere', 'est_contact_principal' => true]);
        $this->classe->update(['capacite' => 1]);
        Classe::factory()->create(['annee_scolaire_id' => $this->annee->id, 'niveau_id' => $this->classe->niveau_id, 'nom' => '6e B']);

        $this->connecterEn(Role::Secretaire, Role::Directeur);
        foreach ([
            '/eleves', '/eleves/create', route('eleves.show', $eleve), route('eleves.edit', $eleve),
            route('responsables.create', $eleve), route('responsables.edit', [$eleve, $responsable]),
            '/classes', '/classes/create', route('classes.show', $this->classe), route('classes.edit', $this->classe),
            '/inscriptions', '/inscriptions/reinscriptions', '/inscriptions/reinscriptions?source='.$this->classe->id,
        ] as $url) {
            $this->get($url)->assertOk();
        }

        $this->get(route('eleves.show', $eleve))->assertSee('Changer de classe', false)->assertSee('Enregistrer un départ')->assertSee('Créer le compte parent');
    }

    public function test_droits_d_acces_au_module_eleves(): void
    {
        $eleve = Eleve::factory()->create();

        // Consultation sans modification : directeur, censeur, comptable.
        foreach ([Role::Directeur, Role::Censeur, Role::Comptable] as $role) {
            $this->connecterEn($role);
            $this->get('/eleves')->assertOk();
            $this->get(route('eleves.show', $eleve))->assertOk();
            $this->get('/eleves/create')->assertForbidden();
            $this->post('/eleves', $this->donneesEleve())->assertForbidden();
        }

        // Aucun accès : enseignant, parent, élève, administrateur technique.
        foreach ([Role::Enseignant, Role::Parent, Role::Eleve, Role::Administrateur] as $role) {
            $this->connecterEn($role);
            $this->get('/eleves')->assertForbidden();
            $this->get(route('eleves.show', $eleve))->assertForbidden();
        }

        $this->assertSame(1, Eleve::count());
    }
}
