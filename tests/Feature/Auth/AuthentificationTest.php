<?php

namespace Tests\Feature\Auth;

use App\Enums\Role;
use App\Models\JournalActivite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthentificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initialiserApplication();
    }

    public function test_la_page_de_connexion_s_affiche(): void
    {
        $this->get('/connexion')->assertOk()->assertSee('E-mail, téléphone ou matricule');
    }

    public function test_connexion_par_email(): void
    {
        $user = $this->utilisateur(Role::Secretaire);

        $this->post('/connexion', ['identifiant' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('tableau-de-bord'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->derniere_connexion_at);
        $this->assertTrue(JournalActivite::where('action', 'connexion')->where('user_id', $user->id)->exists());
    }

    public function test_connexion_par_telephone(): void
    {
        $user = User::factory()->create(['email' => null, 'telephone' => '90112233']);
        $user->assignRole(Role::Parent->value);

        $this->post('/connexion', ['identifiant' => '90112233', 'password' => 'password'])
            ->assertRedirect(route('tableau-de-bord'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_mauvais_mot_de_passe_refuse(): void
    {
        $user = $this->utilisateur(Role::Secretaire);

        $this->from('/connexion')->post('/connexion', ['identifiant' => $user->email, 'password' => 'faux'])
            ->assertRedirect('/connexion')
            ->assertSessionHasErrors('identifiant');

        $this->assertGuest();
    }

    public function test_compte_desactive_refuse_a_la_connexion(): void
    {
        $user = User::factory()->create(['est_actif' => false]);

        $this->post('/connexion', ['identifiant' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('identifiant');

        $this->assertGuest();
    }

    public function test_compte_desactive_en_cours_de_session_est_deconnecte(): void
    {
        $user = $this->connecterEn(Role::Secretaire);
        $user->update(['est_actif' => false]);

        $this->get('/tableau-de-bord')->assertRedirect(route('connexion'));
        $this->assertGuest();
    }

    public function test_limitation_des_tentatives_de_connexion(): void
    {
        $user = $this->utilisateur(Role::Secretaire);
        RateLimiter::clear(mb_strtolower($user->email).'|127.0.0.1');

        for ($i = 0; $i < 5; $i++) {
            $this->post('/connexion', ['identifiant' => $user->email, 'password' => 'faux']);
        }

        // Même avec le bon mot de passe, la 6e tentative est bloquée.
        $this->post('/connexion', ['identifiant' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('identifiant');
        $this->assertStringContainsString('Trop de tentatives', session('errors')->first('identifiant'));
        $this->assertGuest();
        $this->assertTrue(JournalActivite::where('action', 'connexion.echecs_repetes')->exists());
    }

    public function test_deconnexion(): void
    {
        $this->connecterEn(Role::Secretaire);

        $this->post('/deconnexion')->assertRedirect(route('connexion'));
        $this->assertGuest();
    }

    public function test_les_routes_internes_exigent_une_connexion(): void
    {
        foreach (['/tableau-de-bord', '/notifications', '/administration/utilisateurs', '/administration/journal'] as $url) {
            $this->get($url)->assertRedirect(route('connexion'));
        }
    }

    public function test_mot_de_passe_temporaire_doit_etre_change(): void
    {
        $user = User::factory()->create(['doit_changer_mot_de_passe' => true]);
        $user->assignRole(Role::Parent->value);
        $this->actingAs($user);

        $this->get('/tableau-de-bord')->assertRedirect(route('mot-de-passe.edit'));

        $this->put('/mot-de-passe', [
            'mot_de_passe_actuel' => 'password',
            'password' => 'Nouveau2026',
            'password_confirmation' => 'Nouveau2026',
        ])->assertRedirect(route('tableau-de-bord'));

        $this->assertFalse($user->fresh()->doit_changer_mot_de_passe);
        // Un parent est ensuite dirigé vers son espace famille.
        $this->get('/tableau-de-bord')->assertRedirect(route('famille.enfants'));
    }

    public function test_commande_de_creation_de_l_administrateur(): void
    {
        $this->artisan('ecole:creer-admin', ['--nom' => 'Admin', '--email' => 'admin@ecole.test', '--password' => 'Secret2026'])
            ->assertSuccessful();

        $admin = User::where('email', 'admin@ecole.test')->first();
        $this->assertTrue($admin->hasRole('administrateur'));
        $this->assertFalse($admin->can('notes.saisir'));
        $this->assertFalse($admin->can('paiements.enregistrer'));
    }
}
