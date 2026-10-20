<?php

namespace Tests\Feature\Pedagogie;

use App\Enums\Role;
use App\Enums\StatutAnnee;
use App\Models\Affectation;
use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\Enseignant;
use App\Models\Evaluation;
use App\Models\Inscription;
use App\Models\JournalActivite;
use App\Models\Matiere;
use App\Models\Niveau;
use App\Models\Responsable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnseignantsEtAffectationsTest extends TestCase
{
    use RefreshDatabase;

    private AnneeScolaire $annee;

    private Classe $classe;

    private Matiere $maths;

    private Matiere $francais;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initialiserApplication();

        $this->annee = AnneeScolaire::factory()->active()->create(['libelle' => '2026-2027', 'date_debut' => '2026-09-01', 'date_fin' => '2027-07-15']);
        $this->classe = Classe::factory()->create(['annee_scolaire_id' => $this->annee->id, 'niveau_id' => Niveau::where('libelle', '6e')->value('id'), 'nom' => '6e A']);
        $this->maths = Matiere::where('code', 'MATH')->firstOrFail();
        $this->francais = Matiere::where('code', 'FR')->firstOrFail();
    }

    private function enseignant(string $nom = 'Mme Adjo', array $matieres = []): Enseignant
    {
        $user = $this->utilisateur(Role::Enseignant);
        $user->update(['name' => $nom]);
        $enseignant = Enseignant::factory()->create(['user_id' => $user->id]);
        $enseignant->matieres()->sync(array_map(fn (Matiere $m) => $m->id, $matieres));

        return $enseignant;
    }

    public function test_le_directeur_cree_un_enseignant_avec_son_compte(): void
    {
        $this->connecterEn(Role::Directeur);

        $this->post('/enseignants', [
            'name' => 'Kossi Amegan', 'telephone' => '90 12 34 56', 'matricule' => 'ens-001', 'specialite' => 'Mathématiques',
            'diplome' => 'CAPES', 'date_embauche' => '2020-09-01', 'matieres' => [$this->maths->id], 'est_actif' => '1',
        ])->assertSessionHasNoErrors()->assertSessionHas('succes');

        $user = User::where('telephone', '90123456')->firstOrFail();
        $this->assertTrue($user->aRole(Role::Enseignant));
        $this->assertTrue($user->doit_changer_mot_de_passe);
        $enseignant = $user->enseignant;
        $this->assertSame('ENS-001', $enseignant->matricule);
        $this->assertSame([$this->maths->id], $enseignant->matieres->pluck('id')->all());
        $this->assertTrue(JournalActivite::where('action', 'enseignant.cree')->exists());

        $this->get(route('enseignants.show', $enseignant))->assertOk()->assertSee('Kossi Amegan');
        $this->get('/enseignants?q=amegan')->assertOk()->assertSee('Kossi Amegan');
    }

    public function test_modifier_un_enseignant(): void
    {
        $this->connecterEn(Role::Directeur);
        $enseignant = $this->enseignant('Ancien Nom');

        $this->put(route('enseignants.update', $enseignant), [
            'name' => 'Nouveau Nom', 'email' => 'prof@ecole.tg', 'matieres' => [$this->francais->id], 'est_actif' => '0',
        ])->assertSessionHasNoErrors();

        $enseignant->refresh();
        $this->assertSame('Nouveau Nom', $enseignant->user->name);
        $this->assertFalse($enseignant->est_actif);
        // Le compte de connexion reste actif : seule l'administration le désactive.
        $this->assertTrue($enseignant->user->est_actif);
        $this->assertSame(['Français'], $enseignant->matieres->pluck('libelle')->all());
        $this->assertTrue(JournalActivite::where('action', 'enseignant.modifie')->exists());
    }

    public function test_affecter_une_matiere_un_enseignant_et_un_coefficient(): void
    {
        $this->connecterEn(Role::Directeur);
        $adjo = $this->enseignant('Mme Adjo', [$this->francais]);

        $this->post(route('affectations.store', $this->classe), ['matiere_id' => $this->maths->id, 'enseignant_id' => $adjo->id, 'coefficient' => '4'])
            ->assertSessionHasNoErrors()->assertSessionHas('succes')
            // Mme Adjo n'a pas déclaré les mathématiques : avertissement non bloquant.
            ->assertSessionHas('avertissement');

        $affectation = Affectation::firstOrFail();
        $this->assertSame([$adjo->id, '4.00', 1], [$affectation->enseignant_id, $affectation->coefficient, $affectation->ordre_bulletin]);

        // RG-ENS-01 : une seule ligne par matière dans la classe.
        $this->post(route('affectations.store', $this->classe), ['matiere_id' => $this->maths->id, 'coefficient' => '2'])
            ->assertSessionHasErrors('matiere_id');
        $this->assertSame(1, Affectation::count());

        $this->post(route('affectations.store', $this->classe), ['matiere_id' => $this->francais->id, 'coefficient' => '0'])
            ->assertSessionHasErrors('coefficient');
    }

    public function test_modification_groupee_journalisee(): void
    {
        $this->connecterEn(Role::Directeur);
        $adjo = $this->enseignant('Mme Adjo');
        $kodjo = $this->enseignant('M. Kodjo');
        $affectation = Affectation::factory()->create(['classe_id' => $this->classe->id, 'matiere_id' => $this->maths->id, 'enseignant_id' => $adjo->id, 'coefficient' => 3]);

        $this->put(route('affectations.update', $this->classe), [
            'lignes' => [$affectation->id => ['enseignant_id' => $kodjo->id, 'coefficient' => '4.5', 'ordre_bulletin' => 2]],
        ])->assertSessionHasNoErrors()->assertSessionHas('succes', '1 ligne(s) mise(s) à jour.');

        $affectation->refresh();
        $this->assertSame([$kodjo->id, '4.50'], [$affectation->enseignant_id, $affectation->coefficient]);
        $journal = JournalActivite::where('action', 'affectation.modifiee')->firstOrFail();
        $this->assertEquals(['Mme Adjo', 3], [$journal->anciennes_valeurs['enseignant'], $journal->anciennes_valeurs['coefficient']]);
        $this->assertEquals(['M. Kodjo', 4.5], [$journal->nouvelles_valeurs['enseignant'], $journal->nouvelles_valeurs['coefficient']]);

        // Un enseignant inactif ne peut pas recevoir une nouvelle affectation.
        $adjo->update(['est_actif' => false]);
        $this->put(route('affectations.update', $this->classe), [
            'lignes' => [$affectation->id => ['enseignant_id' => $adjo->id, 'coefficient' => '4.5', 'ordre_bulletin' => 2]],
        ])->assertSessionHasErrors("lignes.{$affectation->id}.enseignant_id");
    }

    public function test_une_matiere_evaluee_ne_peut_pas_etre_retiree(): void
    {
        $this->connecterEn(Role::Directeur);
        $evaluee = Affectation::factory()->create(['classe_id' => $this->classe->id, 'matiere_id' => $this->maths->id]);
        Evaluation::factory()->create(['affectation_id' => $evaluee->id, 'periode_id' => \App\Models\Periode::factory()->create(['annee_scolaire_id' => $this->annee->id])->id]);
        $libre = Affectation::factory()->create(['classe_id' => $this->classe->id, 'matiere_id' => $this->francais->id]);

        $this->delete(route('affectations.destroy', [$this->classe, $evaluee]))->assertSessionHasErrors('affectation');
        $this->assertModelExists($evaluee);
        $this->delete(route('affectations.destroy', [$this->classe, $libre]))->assertSessionHas('succes');
        $this->assertModelMissing($libre);

        // Affectation d'une autre classe : liaison imbriquée refusée.
        $autre = Affectation::factory()->create(['matiere_id' => $this->francais->id]);
        $this->delete(route('affectations.destroy', [$this->classe, $autre]))->assertNotFound();
    }

    public function test_reprendre_les_matieres_d_une_autre_classe(): void
    {
        $this->connecterEn(Role::Directeur);
        $precedente = AnneeScolaire::factory()->create(['statut' => StatutAnnee::Cloturee]);
        $modele = Classe::factory()->create(['annee_scolaire_id' => $precedente->id, 'niveau_id' => $this->classe->niveau_id, 'nom' => '6e A']);
        $actif = $this->enseignant('M. Actif');
        $inactif = $this->enseignant('M. Parti');
        $inactif->update(['est_actif' => false]);
        Affectation::factory()->create(['classe_id' => $modele->id, 'matiere_id' => $this->maths->id, 'enseignant_id' => $actif->id, 'coefficient' => 4, 'ordre_bulletin' => 1]);
        Affectation::factory()->create(['classe_id' => $modele->id, 'matiere_id' => $this->francais->id, 'enseignant_id' => $inactif->id, 'coefficient' => 3, 'ordre_bulletin' => 2]);

        $this->post(route('affectations.copier', $this->classe), ['source_id' => $modele->id, 'avec_enseignants' => '1'])
            ->assertSessionHas('succes', '2 matière(s) reprise(s) de 6e A.');

        $lignes = $this->classe->affectations()->get()->keyBy('matiere_id');
        $this->assertSame([$actif->id, '4.00'], [$lignes[$this->maths->id]->enseignant_id, $lignes[$this->maths->id]->coefficient]);
        $this->assertNull($lignes[$this->francais->id]->enseignant_id);

        // L'année clôturée du modèle reste en lecture seule.
        $this->post(route('affectations.copier', $modele), ['source_id' => $this->classe->id])->assertSessionHasErrors('classe');
    }

    public function test_l_enseignant_ne_voit_que_ses_classes_sans_les_coordonnees_des_familles(): void
    {
        $adjo = $this->enseignant('Mme Adjo');
        Affectation::factory()->create(['classe_id' => $this->classe->id, 'matiere_id' => $this->maths->id, 'enseignant_id' => $adjo->id]);
        $autreClasse = Classe::factory()->create(['annee_scolaire_id' => $this->annee->id, 'nom' => '6e B']);
        $inscription = Inscription::factory()->create(['classe_id' => $this->classe->id]);
        $inscription->eleve->responsables()->attach(Responsable::factory()->create(['telephone' => '93939393']), ['lien' => 'pere']);

        $this->actingAs($adjo->user);
        $this->get('/mes-classes')->assertOk()->assertSee('6e A')->assertDontSee('6e B');
        $this->get(route('classes.show', $this->classe))->assertOk()->assertSee($inscription->eleve->nom_complet)->assertDontSee('93939393');
        $this->assertStringNotContainsString('93939393', $this->get(route('classes.show', [$this->classe, 'format' => 'csv']))->streamedContent());
        $this->get(route('classes.show', $autreClasse))->assertForbidden();
        $this->get('/classes')->assertForbidden();
        $this->get(route('eleves.show', $inscription->eleve))->assertForbidden();

        // Professeur principal sans y enseigner : accès à la liste.
        $autreClasse->update(['professeur_principal_id' => $adjo->id]);
        $this->get(route('classes.show', $autreClasse))->assertOk();
    }

    public function test_droits_d_acces(): void
    {
        $enseignant = $this->enseignant();

        foreach ([Role::Secretaire, Role::Censeur, Role::Comptable, Role::Enseignant, Role::Parent, Role::Administrateur] as $role) {
            $this->connecterEn($role);
            $this->get('/enseignants')->assertForbidden();
            $this->get('/affectations')->assertForbidden();
            $this->post(route('affectations.store', $this->classe), ['matiere_id' => $this->maths->id, 'coefficient' => 1])->assertForbidden();
            $this->put(route('enseignants.update', $enseignant), ['name' => 'Piratage', 'email' => 'x@x.tg'])->assertForbidden();
        }
        foreach ([Role::Secretaire, Role::Parent, Role::Eleve] as $role) {
            $this->connecterEn($role);
            $this->get('/mes-classes')->assertForbidden();
        }

        $this->assertSame(0, Affectation::count());
        $this->assertNotSame('Piratage', $enseignant->user->fresh()->name);
    }

    public function test_les_ecrans_s_affichent(): void
    {
        $enseignant = $this->enseignant('Mme Adjo', [$this->maths]);
        Affectation::factory()->create(['classe_id' => $this->classe->id, 'matiere_id' => $this->maths->id, 'enseignant_id' => $enseignant->id]);
        $this->classe->update(['professeur_principal_id' => $enseignant->id]);

        $this->connecterEn(Role::Directeur);
        foreach (['/enseignants', '/enseignants/create', route('enseignants.show', $enseignant), route('enseignants.edit', $enseignant),
            '/affectations', route('affectations.classe', $this->classe), '/enseignants?format=csv'] as $url) {
            $this->get($url)->assertOk();
        }
    }
}
