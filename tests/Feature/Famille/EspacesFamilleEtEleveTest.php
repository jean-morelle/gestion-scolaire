<?php

namespace Tests\Feature\Famille;

use App\Enums\Creneau;
use App\Enums\Role;
use App\Enums\StatutPresence;
use App\Models\Affectation;
use App\Models\AnneeScolaire;
use App\Models\Appel;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\EmploiDuTemps;
use App\Models\Enseignant;
use App\Models\Evaluation;
use App\Models\FraisEleve;
use App\Models\Inscription;
use App\Models\Matiere;
use App\Models\Note;
use App\Models\Periode;
use App\Models\Presence;
use App\Models\Responsable;
use App\Models\TypeFrais;
use App\Models\User;
use App\Services\BulletinService;
use App\Services\EleveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EspacesFamilleEtEleveTest extends TestCase
{
    use RefreshDatabase;

    private Periode $periode;

    private Classe $classe;

    private Inscription $ama;

    private Inscription $kofi;

    private User $parentAma;

    private Evaluation $devoir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initialiserApplication();

        $annee = AnneeScolaire::factory()->active()->create(['date_debut' => now()->subMonths(3), 'date_fin' => now()->addMonths(7)]);
        $this->periode = Periode::factory()->create(['annee_scolaire_id' => $annee->id, 'date_debut' => now()->subMonths(3), 'date_fin' => now()->addMonth()]);
        $this->classe = Classe::factory()->create(['annee_scolaire_id' => $annee->id, 'nom' => '6e A']);
        $this->ama = Inscription::factory()->create(['classe_id' => $this->classe->id, 'date_inscription' => now()->subMonths(3)]);
        $this->ama->eleve->update(['nom' => 'KOFFI', 'prenoms' => 'Ama']);
        $this->kofi = Inscription::factory()->create(['classe_id' => $this->classe->id, 'date_inscription' => now()->subMonths(3)]);
        $this->kofi->eleve->update(['nom' => 'MENSAH', 'prenoms' => 'Kofi']);

        $this->parentAma = $this->utilisateur(Role::Parent);
        $this->ama->eleve->responsables()->attach(Responsable::factory()->create(['user_id' => $this->parentAma->id]), ['lien' => 'mere']);
        $parentKofi = $this->utilisateur(Role::Parent);
        $this->kofi->eleve->responsables()->attach(Responsable::factory()->create(['user_id' => $parentKofi->id]), ['lien' => 'pere']);

        $prof = Enseignant::factory()->create(['user_id' => $this->utilisateur(Role::Enseignant)->id]);
        $maths = Affectation::factory()->create(['classe_id' => $this->classe->id, 'matiere_id' => Matiere::where('code', 'MATH')->value('id'), 'enseignant_id' => $prof->id]);
        $this->devoir = Evaluation::factory()->validee()->create(['affectation_id' => $maths->id, 'periode_id' => $this->periode->id, 'libelle' => 'Devoir de géométrie', 'date_evaluation' => now()->subWeek()]);
        Note::factory()->create(['evaluation_id' => $this->devoir->id, 'inscription_id' => $this->ama->id, 'valeur' => 14.5]);
        Note::factory()->create(['evaluation_id' => $this->devoir->id, 'inscription_id' => $this->kofi->id, 'valeur' => 9]);
        EmploiDuTemps::create(['affectation_id' => $maths->id, 'jour' => 1, 'heure_debut' => '08:00:00', 'heure_fin' => '10:00:00', 'salle' => 'B12']);

        $appel = Appel::create(['classe_id' => $this->classe->id, 'date_appel' => now()->subDays(2), 'creneau' => Creneau::Matin]);
        Presence::create(['appel_id' => $appel->id, 'inscription_id' => $this->ama->id, 'statut' => StatutPresence::Absent]);
        Presence::create(['appel_id' => $appel->id, 'inscription_id' => $this->kofi->id, 'statut' => StatutPresence::Present]);

        FraisEleve::create(['inscription_id' => $this->ama->id, 'type_frais_id' => TypeFrais::factory()->create(['libelle' => 'Scolarité'])->id,
            'libelle' => 'Scolarité — 1re tranche', 'montant_du' => 50000, 'date_limite' => now()->subDay()]);
    }

    private function publier(): void
    {
        app(BulletinService::class)->publier($this->classe, $this->periode, User::first());
    }

    public function test_le_parent_ne_voit_que_ses_enfants(): void
    {
        $this->actingAs($this->parentAma);
        $this->get('/tableau-de-bord')->assertRedirect(route('famille.enfants'));
        $this->get('/famille')->assertOk()->assertSee('Ama')->assertDontSee('MENSAH');

        foreach (['resume', 'resultats', 'assiduite', 'finances', 'emploi-du-temps'] as $section) {
            $this->get(route('famille.enfant', [$this->ama->eleve, $section]))->assertOk();
            $this->get(route('famille.enfant', [$this->kofi->eleve, $section]))->assertForbidden();
        }
        $this->get(route('famille.enfant', [$this->ama->eleve, 'inconnue']))->assertNotFound();
        // Ni les écrans du personnel ni le dossier administratif.
        $this->get(route('eleves.show', $this->ama->eleve))->assertForbidden();
        $this->get(route('finances.show', $this->ama))->assertForbidden();
        $this->get('/evaluations')->assertForbidden();
    }

    public function test_rien_n_est_visible_avant_publication(): void
    {
        $this->actingAs($this->parentAma);
        $this->get(route('famille.enfant', [$this->ama->eleve, 'resultats']))->assertOk()
            ->assertSee('Aucun bulletin')->assertDontSee('Devoir de géométrie')->assertDontSee('14,50');

        $this->publier();
        $bulletin = \App\Models\Bulletin::where('inscription_id', $this->ama->id)->firstOrFail();
        $this->get(route('famille.enfant', [$this->ama->eleve, 'resultats']))->assertOk()
            ->assertSee('Devoir de géométrie')->assertSee('14,50')->assertSee(route('bulletins.pdf', $bulletin), false);
        $this->get(route('bulletins.pdf', $bulletin))->assertOk();
        // Pas le bulletin d'un autre enfant.
        $this->get(route('bulletins.pdf', \App\Models\Bulletin::where('inscription_id', $this->kofi->id)->first()))->assertForbidden();

        // Retiré pour correction : de nouveau invisible.
        app(BulletinService::class)->depublier($this->classe, $this->periode, 'Correction');
        $this->get(route('famille.enfant', [$this->ama->eleve, 'resultats']))->assertDontSee('14,50');
        $this->get(route('bulletins.pdf', $bulletin))->assertForbidden();
    }

    public function test_absences_finances_et_emploi_du_temps(): void
    {
        $this->actingAs($this->parentAma);

        $this->get(route('famille.enfant', [$this->ama->eleve, 'assiduite']))->assertOk()->assertSee('Absent');
        $this->get(route('famille.enfant', [$this->ama->eleve, 'finances']))->assertOk()->assertSee('Scolarité — 1re tranche')->assertSee('en retard');
        $this->get(route('famille.enfant', [$this->ama->eleve, 'emploi-du-temps']))->assertOk()->assertSee('08:00 - 10:00')->assertSee('B12');
        $this->get('/famille')->assertSee('1 non justifiée(s)');
    }

    public function test_compte_eleve_connexion_par_matricule_et_espace_personnel(): void
    {
        $this->connecterEn(Role::Secretaire);
        $eleve = $this->ama->eleve;
        $this->post(route('eleves.compte', $eleve))->assertSessionHas('succes');
        $this->post(route('eleves.compte', $eleve))->assertSessionHas('erreur');
        $compte = $eleve->fresh()->user;
        $this->assertTrue($compte->aRole(Role::Eleve));
        $this->assertSame($eleve->matricule, $compte->identifiant());

        // Connexion avec le matricule.
        $compte->forceFill(['password' => 'secret-1234', 'doit_changer_mot_de_passe' => false])->save();
        auth()->logout();
        $this->post('/connexion', ['identifiant' => $eleve->matricule, 'password' => 'secret-1234'])->assertRedirect();
        $this->assertAuthenticatedAs($compte);

        $this->get('/tableau-de-bord')->assertRedirect(route('eleve.accueil'));
        foreach (['/mon-espace', '/mon-espace/resultats', '/mon-espace/assiduite', '/mon-espace/emploi-du-temps'] as $url) {
            $this->get($url)->assertOk()->assertSee('Ama');
        }
        // L'élève ne voit pas les finances, ni l'espace famille, ni un camarade.
        $this->get('/mon-espace')->assertDontSee('Frais et paiements');
        $this->get('/famille')->assertForbidden();
        $this->get(route('famille.enfant', [$eleve, 'finances']))->assertForbidden();
        $this->publier();
        $this->get(route('bulletins.pdf', \App\Models\Bulletin::where('inscription_id', $this->ama->id)->first()))->assertOk();
        $this->get(route('bulletins.pdf', \App\Models\Bulletin::where('inscription_id', $this->kofi->id)->first()))->assertForbidden();
    }

    public function test_un_parent_avec_plusieurs_enfants(): void
    {
        $frere = Inscription::factory()->create(['classe_id' => $this->classe->id]);
        $frere->eleve->update(['prenoms' => 'Yao']);
        $this->parentAma->responsable->eleves()->attach($frere->eleve_id, ['lien' => 'mere']);

        $this->actingAs($this->parentAma)->get('/famille')->assertOk()->assertSee('Ama')->assertSee('Yao');
        $this->get(route('famille.enfant', [$frere->eleve, 'resume']))->assertOk();
    }

    public function test_notification_d_absence_mene_a_la_page_d_assiduite(): void
    {
        $this->connecterEn(Role::Censeur);
        $jour = now()->subDay()->isSunday() ? now()->subDays(2) : now()->subDay();
        $this->post(route('appels.store', $this->classe), ['date' => $jour->toDateString(), 'creneau' => 'apres_midi', 'lignes' => [
            $this->ama->id => ['statut' => 'absent'], $this->kofi->id => ['statut' => 'present'],
        ]])->assertSessionHasNoErrors();

        $notification = $this->parentAma->notifications()->firstOrFail();
        $this->actingAs($this->parentAma)->get(route('notifications.ouvrir', $notification->id))
            ->assertRedirect(route('famille.enfant', [$this->ama->eleve, 'assiduite']));
    }

    public function test_un_compte_parent_sans_fiche_responsable(): void
    {
        $this->actingAs($this->utilisateur(Role::Parent))->get('/famille')->assertForbidden();
        $this->actingAs($this->utilisateur(Role::Eleve))->get('/mon-espace')->assertForbidden();
        $this->actingAs($this->utilisateur(Role::Secretaire))->get('/famille')->assertForbidden();
    }
}
