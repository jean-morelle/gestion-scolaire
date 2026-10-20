<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Affectation;
use App\Models\Annonce;
use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\Enseignant;
use App\Models\FraisEleve;
use App\Models\Inscription;
use App\Models\Responsable;
use App\Models\TypeFrais;
use App\Models\User;
use App\Notifications\AnnoncePubliee;
use App\Notifications\EcheancePaiement;
use App\Services\RappelEcheanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PilotageEtNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private Classe $sixiemeA;

    private Classe $sixiemeB;

    private User $parentA;

    private User $parentB;

    private User $eleveA;

    private Inscription $inscriptionA;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initialiserApplication();

        $annee = AnneeScolaire::factory()->active()->create(['date_debut' => now()->subMonths(2), 'date_fin' => now()->addMonths(8)]);
        $this->sixiemeA = Classe::factory()->create(['annee_scolaire_id' => $annee->id, 'nom' => '6e A']);
        $this->sixiemeB = Classe::factory()->create(['annee_scolaire_id' => $annee->id, 'nom' => '6e B']);

        $this->inscriptionA = Inscription::factory()->create(['classe_id' => $this->sixiemeA->id]);
        $inscriptionB = Inscription::factory()->create(['classe_id' => $this->sixiemeB->id]);
        $this->parentA = $this->utilisateur(Role::Parent);
        $this->parentB = $this->utilisateur(Role::Parent);
        $this->inscriptionA->eleve->responsables()->attach(Responsable::factory()->create(['user_id' => $this->parentA->id]), ['lien' => 'mere', 'est_payeur' => true]);
        $inscriptionB->eleve->responsables()->attach(Responsable::factory()->create(['user_id' => $this->parentB->id]), ['lien' => 'pere']);
        $this->eleveA = $this->utilisateur(Role::Eleve);
        $this->inscriptionA->eleve->update(['user_id' => $this->eleveA->id]);
    }

    public function test_annonce_ciblee_sur_une_classe(): void
    {
        $directeur = $this->connecterEn(Role::Directeur);
        $secretaire = $this->utilisateur(Role::Secretaire);

        $this->post('/annonces', ['titre' => 'Sortie au musée', 'contenu' => 'Les parents de 6e A…', 'cible' => 'parents', 'classe_id' => $this->sixiemeA->id])
            ->assertSessionHasNoErrors();
        $annonce = Annonce::firstOrFail();
        $this->assertNotNull($annonce->notifiee_at);

        $this->assertSame(1, $this->parentA->notifications()->where('type', AnnoncePubliee::class)->count());
        $this->assertSame(0, $this->parentB->notifications()->count());
        $this->assertSame(0, $this->eleveA->notifications()->count());
        $this->assertSame(0, $secretaire->notifications()->count());
        $this->assertSame(0, $directeur->notifications()->count());

        $this->actingAs($this->parentA)->get('/annonces')->assertOk()->assertSee('Sortie au musée');
        $this->get(route('annonces.show', $annonce))->assertOk();
        $this->actingAs($this->parentB)->get('/annonces')->assertOk()->assertDontSee('Sortie au musée');
        $this->get(route('annonces.show', $annonce))->assertForbidden();
        $this->actingAs($this->eleveA)->get(route('annonces.show', $annonce))->assertForbidden();
    }

    public function test_annonce_a_tous_et_au_personnel(): void
    {
        $this->connecterEn(Role::Directeur);
        $prof = $this->utilisateur(Role::Enseignant);

        $this->post('/annonces', ['titre' => 'Réunion pédagogique', 'contenu' => 'Lundi 16 h', 'cible' => 'personnel', 'classe_id' => $this->sixiemeA->id])->assertSessionHasNoErrors();
        $this->assertNull(Annonce::first()->classe_id);
        $this->assertSame(1, $prof->notifications()->count());
        $this->assertSame(0, $this->parentA->notifications()->count());

        $this->post('/annonces', ['titre' => 'Fermeture', 'contenu' => 'Établissement fermé vendredi', 'cible' => 'tous'])->assertSessionHasNoErrors();
        foreach ([$prof, $this->parentA, $this->parentB, $this->eleveA] as $u) {
            $this->assertSame(1, $u->notifications()->where('data->titre', 'Annonce : Fermeture')->count());
        }
    }

    public function test_annonce_programmee_puis_diffusee(): void
    {
        $this->connecterEn(Role::Directeur);
        $this->post('/annonces', ['titre' => 'Rentrée', 'contenu' => 'Bonne rentrée', 'cible' => 'parents', 'publiee_at' => now()->addDay()->format('Y-m-d\TH:i')])
            ->assertSessionHasNoErrors();

        $this->assertSame(0, $this->parentA->notifications()->count());
        $this->actingAs($this->parentA)->get('/annonces')->assertDontSee('Rentrée');

        $this->travel(2)->days();
        $this->artisan('annonces:diffuser')->assertSuccessful();
        $this->assertSame(1, $this->parentA->notifications()->count());
        $this->artisan('annonces:diffuser')->assertSuccessful();
        $this->assertSame(1, $this->parentA->notifications()->count());
        $this->get('/annonces')->assertSee('Rentrée');
    }

    public function test_seuls_les_gestionnaires_publient(): void
    {
        foreach ([Role::Secretaire, Role::Enseignant, Role::Comptable, Role::Parent, Role::Eleve] as $role) {
            $this->connecterEn($role);
            $this->get('/annonces')->assertOk();
            $this->post('/annonces', ['titre' => 'X', 'contenu' => 'Y', 'cible' => 'tous'])->assertForbidden();
        }
        $this->assertSame(0, Annonce::count());
    }

    public function test_rappels_d_echeance_sans_doublon(): void
    {
        $type = TypeFrais::factory()->create();
        $proche = FraisEleve::create(['inscription_id' => $this->inscriptionA->id, 'type_frais_id' => $type->id, 'libelle' => 'Scolarité — 2e tranche', 'montant_du' => 40000, 'date_limite' => now()->addDays(3)]);
        $depassee = FraisEleve::create(['inscription_id' => $this->inscriptionA->id, 'type_frais_id' => $type->id, 'libelle' => 'Scolarité — 1re tranche', 'montant_du' => 50000, 'date_limite' => now()->subDays(2)]);
        FraisEleve::create(['inscription_id' => $this->inscriptionA->id, 'type_frais_id' => $type->id, 'libelle' => 'Scolarité — 3e tranche', 'montant_du' => 30000, 'date_limite' => now()->addMonths(3)]);

        $this->assertSame(2, app(RappelEcheanceService::class)->envoyer());
        $notifications = $this->parentA->notifications()->where('type', EcheancePaiement::class)->get();
        $this->assertEqualsCanonicalizing(['proche', 'depassee'], $notifications->pluck('data.rappel')->all());
        $this->assertSame(route('famille.enfant', [$this->inscriptionA->eleve, 'finances']), $notifications->first()->data['lien']);
        $this->assertSame(0, $this->parentB->notifications()->count());

        // Une seconde exécution n'envoie rien de plus ; une ligne réglée n'est pas rappelée.
        $this->artisan('finances:rappels')->expectsOutputToContain('0 rappel')->assertSuccessful();
        $this->assertSame(2, $this->parentA->notifications()->count());

        // Quand l'échéance « proche » est dépassée, un rappel « depassee » part une fois.
        $this->travel(5)->days();
        $this->assertSame(1, app(RappelEcheanceService::class)->envoyer());
        $this->assertTrue($this->parentA->notifications()->where('data->frais_eleve_id', $proche->id)->where('data->rappel', 'depassee')->exists());
        $this->assertSame(1, $this->parentA->notifications()->where('data->frais_eleve_id', $depassee->id)->count());
    }

    public function test_tableau_de_bord_de_chaque_role(): void
    {
        $prof = Enseignant::factory()->create(['user_id' => $this->utilisateur(Role::Enseignant)->id]);
        Affectation::factory()->create(['classe_id' => $this->sixiemeA->id, 'enseignant_id' => $prof->id]);

        $attendus = [
            Role::Directeur->value => ['Vue d\'ensemble', 'Élèves inscrits', 'Encaissé ce mois', 'Vie scolaire'],
            Role::Censeur->value => ['Vue d\'ensemble', 'Vie scolaire'],
            Role::Secretaire->value => ['Secrétariat', 'Dossiers incomplets'],
            Role::Comptable->value => ['Comptabilité', 'Derniers reçus'],
            Role::Administrateur->value => ['Administration', 'Comptes actifs'],
        ];
        foreach ($attendus as $role => $textes) {
            $this->connecterEn(Role::from($role));
            $reponse = $this->get('/tableau-de-bord')->assertOk();
            foreach ($textes as $texte) {
                $reponse->assertSee($texte, false);
            }
        }

        // Cloisonnement : l'administrateur technique ne voit pas les finances, le comptable pas le pilotage scolaire.
        $this->connecterEn(Role::Administrateur);
        $this->get('/tableau-de-bord')->assertDontSee('Encaissé')->assertDontSee('Comptabilité');
        $this->connecterEn(Role::Comptable);
        $this->get('/tableau-de-bord')->assertDontSee('Vue d\'ensemble');

        $this->actingAs($prof->user)->get('/tableau-de-bord')->assertOk()->assertSee('Mon enseignement')->assertSee('Copies à corriger')->assertDontSee('Comptabilité');
    }

    public function test_statistiques_scolaires(): void
    {
        $this->connecterEn(Role::Censeur);
        $this->get('/statistiques-scolaires')->assertOk()->assertSee('6e A')->assertSee('Effectifs par classe');
        $this->assertStringContainsString('"6e A"', $this->get('/statistiques-scolaires?export=effectifs')->streamedContent());
        $this->get('/statistiques-scolaires?export=resultats')->assertOk();
        $this->get('/statistiques-scolaires?export=assiduite')->assertOk();

        foreach ([Role::Secretaire, Role::Enseignant, Role::Comptable, Role::Parent] as $role) {
            $this->connecterEn($role);
            $this->get('/statistiques-scolaires')->assertForbidden();
        }
    }

    public function test_ecrans_des_annonces(): void
    {
        $this->connecterEn(Role::Directeur);
        $this->post('/annonces', ['titre' => 'Info', 'contenu' => 'Texte', 'cible' => 'tous']);
        $annonce = Annonce::firstOrFail();
        foreach (['/annonces', '/annonces?vue=gestion', '/annonces/create', route('annonces.edit', $annonce), route('annonces.show', $annonce)] as $url) {
            $this->get($url)->assertOk();
        }
        $this->put(route('annonces.update', $annonce), ['titre' => 'Info modifiée', 'contenu' => 'Texte', 'cible' => 'tous'])->assertSessionHasNoErrors();
        $this->delete(route('annonces.destroy', $annonce))->assertRedirect();
        $this->assertModelMissing($annonce);
    }
}
