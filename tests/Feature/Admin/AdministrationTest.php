<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Enums\StatutPeriode;
use App\Models\AnneeScolaire;
use App\Models\Enseignant;
use App\Models\JournalActivite;
use App\Models\Periode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdministrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initialiserApplication();
    }

    public function test_les_pages_d_administration_s_affichent_pour_l_administrateur(): void
    {
        $this->connecterEn(Role::Administrateur);

        foreach (['/tableau-de-bord', '/administration/etablissement', '/administration/annees', '/administration/annees/create',
            '/administration/referentiel', '/administration/utilisateurs', '/administration/utilisateurs/create', '/administration/journal'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_les_autres_roles_n_accedent_pas_a_l_administration(): void
    {
        foreach ([Role::Secretaire, Role::Enseignant, Role::Comptable, Role::Parent, Role::Eleve, Role::Censeur] as $role) {
            $this->connecterEn($role);
            $this->get('/administration/etablissement')->assertForbidden();
            $this->get('/administration/utilisateurs')->assertForbidden();
            $this->post('/administration/utilisateurs', [])->assertForbidden();
        }
    }

    public function test_modifier_les_parametres_de_l_etablissement(): void
    {
        $this->connecterEn(Role::Administrateur);
        $parametres = etablissement()->parametres;

        $this->put('/administration/etablissement', [
            'nom' => 'Lycée Moderne de Lomé',
            'devise' => 'FCFA',
            'parametres' => array_merge($parametres, ['decimales' => 1, 'notifier_absences' => '0', 'remise_devoirs_en_ligne' => '1']),
        ])->assertSessionHasNoErrors()->assertRedirect();

        $etab = \App\Models\Etablissement::courant();
        $this->assertSame('Lycée Moderne de Lomé', $etab->nom);
        $this->assertSame(1, (int) $etab->parametre('decimales'));
        $this->assertFalse($etab->parametre('notifier_absences'));
        $this->assertTrue(JournalActivite::where('action', 'etablissement.modifie')->exists());
    }

    public function test_creer_une_annee_avec_trimestres_et_l_activer(): void
    {
        $this->connecterEn(Role::Administrateur);

        $this->post('/administration/annees', [
            'libelle' => '2026-2027', 'date_debut' => '2026-09-01', 'date_fin' => '2027-07-15', 'decoupage' => 'trimestres',
        ])->assertSessionHasNoErrors();

        $annee = AnneeScolaire::where('libelle', '2026-2027')->first();
        $this->assertCount(3, $annee->periodes);
        $this->assertSame('2027-07-15', $annee->periodes->last()->date_fin->format('Y-m-d'));

        $autre = AnneeScolaire::factory()->create(['libelle' => '2019-2020', 'est_active' => true]);
        $this->post("/administration/annees/{$annee->id}/activer")->assertRedirect();

        $this->assertTrue($annee->fresh()->est_active);
        $this->assertFalse($autre->fresh()->est_active);
        $this->assertSame(1, AnneeScolaire::where('est_active', true)->count());
    }

    public function test_les_periodes_ne_se_chevauchent_pas(): void
    {
        $this->connecterEn(Role::Administrateur);
        $annee = AnneeScolaire::factory()->create(['date_debut' => '2026-09-01', 'date_fin' => '2027-07-15']);
        Periode::factory()->create(['annee_scolaire_id' => $annee->id, 'ordre' => 1, 'date_debut' => '2026-09-01', 'date_fin' => '2026-12-20']);

        $this->post("/administration/annees/{$annee->id}/periodes", [
            'libelle' => '2e trimestre', 'ordre' => 2, 'date_debut' => '2026-12-01', 'date_fin' => '2027-03-31',
        ])->assertSessionHasErrors('date_debut');

        $this->post("/administration/annees/{$annee->id}/periodes", [
            'libelle' => '2e trimestre', 'ordre' => 2, 'date_debut' => '2027-01-05', 'date_fin' => '2027-03-31',
        ])->assertSessionHasNoErrors();
    }

    public function test_cloturer_une_annee_cloture_ses_periodes(): void
    {
        $this->connecterEn(Role::Administrateur);
        $annee = AnneeScolaire::factory()->create();
        Periode::factory()->create(['annee_scolaire_id' => $annee->id, 'ordre' => 1]);

        $this->post("/administration/annees/{$annee->id}/cloturer")->assertRedirect();

        $this->assertTrue($annee->fresh()->estCloturee());
        $this->assertSame(StatutPeriode::Cloturee, $annee->periodes()->first()->statut);
        $this->put("/administration/annees/{$annee->id}", ['libelle' => $annee->libelle, 'date_debut' => '2026-09-01', 'date_fin' => '2027-07-01'])->assertForbidden();
    }

    public function test_creer_un_compte_enseignant_avec_mot_de_passe_temporaire(): void
    {
        $this->connecterEn(Role::Administrateur);

        $reponse = $this->post('/administration/utilisateurs', [
            'name' => 'Kossi Mensah', 'email' => 'kossi@ecole.test', 'roles' => ['enseignant', 'censeur'],
            'est_actif' => '1', 'specialite' => 'Mathématiques',
        ])->assertSessionHasNoErrors();

        $user = User::where('email', 'kossi@ecole.test')->first();
        $this->assertTrue($user->hasAllRoles(['enseignant', 'censeur']));
        $this->assertTrue($user->doit_changer_mot_de_passe);
        $this->assertSame('Mathématiques', Enseignant::where('user_id', $user->id)->first()->specialite);
        $reponse->assertSessionHas('succes', fn ($m) => str_contains($m, 'Mot de passe temporaire'));
        $this->assertTrue(JournalActivite::where('action', 'compte.cree')->exists());
    }

    public function test_un_administrateur_ne_peut_pas_attribuer_le_role_parent_depuis_les_comptes(): void
    {
        $this->connecterEn(Role::Administrateur);

        $this->post('/administration/utilisateurs', [
            'name' => 'X', 'email' => 'x@ecole.test', 'roles' => ['parent'], 'est_actif' => '1',
        ])->assertSessionHasErrors('roles.0');
    }

    public function test_un_administrateur_ne_peut_pas_se_desactiver_lui_meme(): void
    {
        $admin = $this->connecterEn(Role::Administrateur);

        $this->put("/administration/utilisateurs/{$admin->id}", [
            'name' => $admin->name, 'email' => $admin->email, 'roles' => ['administrateur'], 'est_actif' => '0',
        ])->assertSessionHas('erreur');

        $this->assertTrue($admin->fresh()->est_actif);
    }

    public function test_desactiver_et_reinitialiser_un_compte(): void
    {
        $this->connecterEn(Role::Administrateur);
        $cible = $this->utilisateur(Role::Comptable);

        $this->put("/administration/utilisateurs/{$cible->id}", [
            'name' => $cible->name, 'email' => $cible->email, 'roles' => ['comptable'], 'est_actif' => '0',
        ])->assertSessionHasNoErrors();
        $this->assertFalse($cible->fresh()->est_actif);

        $this->post("/administration/utilisateurs/{$cible->id}/reinitialiser")->assertSessionHas('succes');
        $this->assertTrue($cible->fresh()->doit_changer_mot_de_passe);
    }

    public function test_un_niveau_utilise_ne_peut_pas_etre_supprime(): void
    {
        $this->connecterEn(Role::Administrateur);
        $classe = \App\Models\Classe::factory()->create();

        $this->delete("/administration/niveaux/{$classe->niveau_id}")->assertSessionHas('erreur');
        $this->assertModelExists($classe->niveau);
    }

    public function test_le_journal_ne_peut_pas_etre_modifie(): void
    {
        $entree = JournalActivite::create(['action' => 'test']);

        $this->expectException(\LogicException::class);
        $entree->update(['action' => 'autre']);
    }
}
