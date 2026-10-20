<?php

namespace Tests\Feature\VieScolaire;

use App\Enums\Role;
use App\Enums\StatutPeriode;
use App\Enums\StatutPresence;
use App\Models\Affectation;
use App\Models\AnneeScolaire;
use App\Models\Appel;
use App\Models\Classe;
use App\Models\Enseignant;
use App\Models\Inscription;
use App\Models\JournalActivite;
use App\Models\Justificatif;
use App\Models\Periode;
use App\Models\Presence;
use App\Models\Responsable;
use App\Services\AppelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AppelEtJustificatifsTest extends TestCase
{
    use RefreshDatabase;

    private Periode $periode;

    private Classe $classe;

    /** @var \Illuminate\Support\Collection<int, Inscription> */
    private $inscriptions;

    private string $jour;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initialiserApplication();
        Storage::fake('local');

        $annee = AnneeScolaire::factory()->active()->create();
        $this->periode = Periode::factory()->create(['annee_scolaire_id' => $annee->id, 'date_debut' => now()->subMonths(2), 'date_fin' => now()->addMonth()]);
        $this->classe = Classe::factory()->create(['annee_scolaire_id' => $annee->id, 'nom' => '6e A']);
        $this->inscriptions = Inscription::factory()->count(3)->create(['classe_id' => $this->classe->id, 'date_inscription' => now()->subMonths(2)]);
        // Dernier jour ouvré passé (pas un dimanche).
        $jour = now()->subDay();
        $this->jour = ($jour->isSunday() ? $jour->subDay() : $jour)->toDateString();
    }

    /**
     * @param  list<string>  $statuts  un par inscription
     */
    private function faireAppel(array $statuts, ?string $date = null, string $creneau = 'matin', array $extra = []): \Illuminate\Testing\TestResponse
    {
        $lignes = [];
        foreach ($this->inscriptions->values() as $i => $inscription) {
            $lignes[$inscription->id] = ['statut' => $statuts[$i]] + ($extra[$i] ?? []);
        }

        return $this->post(route('appels.store', $this->classe), ['date' => $date ?? $this->jour, 'creneau' => $creneau, 'lignes' => $lignes]);
    }

    public function test_appel_par_le_censeur_et_refaire_l_appel_le_modifie(): void
    {
        $this->connecterEn(Role::Censeur);

        $this->faireAppel(['present', 'absent', 'retard'], extra: [2 => ['minutes_retard' => 15]])
            ->assertSessionHasNoErrors()->assertSessionHas('succes');
        $appel = Appel::firstOrFail();
        $this->assertSame(3, $appel->presences()->count());
        $this->assertSame(15, $appel->presences()->where('inscription_id', $this->inscriptions[2]->id)->value('minutes_retard'));

        // RG-PRE-01 : refaire l'appel modifie le même appel, journalisé.
        $this->faireAppel(['present', 'present', 'retard'])->assertSessionHasNoErrors();
        $this->assertSame(1, Appel::count());
        $this->assertSame(3, Presence::count());
        $this->assertSame(StatutPresence::Present, Presence::where('inscription_id', $this->inscriptions[1]->id)->first()->statut);
        $this->assertTrue(JournalActivite::where('action', 'appel.modifie')->exists());

        // L'après-midi est un autre appel.
        $this->faireAppel(['present', 'present', 'present'], creneau: 'apres_midi')->assertSessionHasNoErrors();
        $this->assertSame(2, Appel::count());
    }

    public function test_chaque_eleve_doit_avoir_une_reponse(): void
    {
        $this->connecterEn(Role::Censeur);

        $this->post(route('appels.store', $this->classe), ['date' => $this->jour, 'creneau' => 'matin', 'lignes' => [
            $this->inscriptions[0]->id => ['statut' => 'present'],
        ]])->assertSessionHasErrors('lignes');
        $this->assertSame(0, Appel::count());
    }

    public function test_dates_interdites(): void
    {
        $this->connecterEn(Role::Censeur);

        $this->faireAppel(['present', 'present', 'present'], now()->addDays(2)->toDateString())->assertSessionHasErrors('date_appel');
        $dimanche = now()->previous(\Carbon\Carbon::SUNDAY)->toDateString();
        $this->faireAppel(['present', 'present', 'present'], $dimanche)->assertSessionHasErrors('date_appel');
        $this->faireAppel(['present', 'present', 'present'], now()->subYears(2)->toDateString())->assertSessionHasErrors('date_appel');

        $this->periode->update(['statut' => StatutPeriode::Cloturee]);
        $this->faireAppel(['present', 'present', 'present'])->assertSessionHasErrors('date_appel');
        $this->assertSame(0, Appel::count());
    }

    public function test_l_enseignant_fait_l_appel_de_ses_classes_seulement_et_dans_le_delai(): void
    {
        $prof = Enseignant::factory()->create(['user_id' => $this->utilisateur(Role::Enseignant)->id]);
        Affectation::factory()->create(['classe_id' => $this->classe->id, 'enseignant_id' => $prof->id]);
        $autreClasse = Classe::factory()->create(['annee_scolaire_id' => $this->classe->annee_scolaire_id]);

        $this->actingAs($prof->user);
        $this->get('/appels')->assertOk()->assertSee('6e A')->assertDontSee($autreClasse->nom);
        $this->get(route('appels.saisie', [$autreClasse, 'date' => $this->jour]))->assertForbidden();
        $this->post(route('appels.store', $autreClasse), ['date' => $this->jour, 'creneau' => 'matin', 'lignes' => []])->assertForbidden();

        $this->faireAppel(['present', 'absent', 'present'])->assertSessionHasNoErrors();

        // RG-PRE-07 : au-delà du délai (48 h), seul la vie scolaire peut modifier.
        $this->travel(49)->hours();
        $this->faireAppel(['present', 'present', 'present'])->assertSessionHasErrors('lignes');
        $this->assertSame(StatutPresence::Absent, Presence::where('inscription_id', $this->inscriptions[1]->id)->first()->statut);

        $this->connecterEn(Role::Censeur);
        $this->faireAppel(['present', 'present', 'present'])->assertSessionHasNoErrors();
        $this->assertSame(StatutPresence::Present, Presence::where('inscription_id', $this->inscriptions[1]->id)->first()->statut);
    }

    public function test_les_parents_sont_notifies_des_absences_selon_le_parametre(): void
    {
        $parent = $this->utilisateur(Role::Parent);
        $this->inscriptions[1]->eleve->responsables()->attach(Responsable::factory()->create(['user_id' => $parent->id]), ['lien' => 'mere']);

        $this->connecterEn(Role::Censeur);
        $this->faireAppel(['present', 'absent', 'present'])->assertSessionHasNoErrors();
        $this->assertSame(1, $parent->notifications()->count());
        $this->assertStringContainsString('absent', $parent->notifications()->first()->data['message']);

        // Refaire le même appel ne renvoie pas de notification.
        $this->faireAppel(['present', 'absent', 'present'])->assertSessionHasNoErrors();
        $this->assertSame(1, $parent->notifications()->count());

        $etab = etablissement();
        $etab->update(['parametres' => array_merge($etab->parametres, ['notifier_absences' => false])]);
        $this->faireAppel(['present', 'absent', 'present'], creneau: 'apres_midi')->assertSessionHasNoErrors();
        $this->assertSame(1, $parent->notifications()->count());
    }

    public function test_un_eleve_parti_ne_figure_plus_a_l_appel(): void
    {
        $this->inscriptions[2]->update(['statut' => 'parti', 'date_sortie' => now()->subMonth(), 'motif_sortie' => 'Départ']);

        $this->assertCount(2, app(AppelService::class)->eleves($this->classe, $this->jour));
        // Avant son départ, il était attendu.
        $this->assertCount(3, app(AppelService::class)->eleves($this->classe, now()->subMonth()->subDays(3)->toDateString()));
    }

    public function test_justificatif_excuse_les_absences_passees_et_futures(): void
    {
        $this->connecterEn(Role::Censeur);
        $this->faireAppel(['present', 'absent', 'present'])->assertSessionHasNoErrors();

        $this->post('/justificatifs', [
            'inscription_id' => $this->inscriptions[1]->id, 'date_debut' => $this->jour, 'date_fin' => now()->toDateString(),
            'motif' => 'Maladie', 'fichier' => UploadedFile::fake()->create('certificat.pdf', 100, 'application/pdf'),
        ])->assertSessionHasNoErrors()->assertSessionHas('succes');

        $presence = Presence::where('inscription_id', $this->inscriptions[1]->id)->first();
        $this->assertSame(StatutPresence::AbsentExcuse, $presence->statut);
        $justificatif = Justificatif::firstOrFail();
        Storage::disk('local')->assertExists($justificatif->fichier_chemin);

        // Absence saisie ensuite dans la plage : excusée automatiquement.
        $this->faireAppel(['present', 'absent', 'present'], creneau: 'apres_midi')->assertSessionHasNoErrors();
        $this->assertSame(2, Presence::where('statut', StatutPresence::AbsentExcuse)->count());

        $totaux = app(AppelService::class)->totaux($this->inscriptions[1], $this->periode);
        $this->assertSame(['absences' => 2, 'non_justifiees' => 0, 'retards' => 0, 'demi_journees' => 2], $totaux);

        $this->get(route('justificatifs.fichier', $justificatif))->assertOk();
        $this->delete(route('justificatifs.destroy', $justificatif))->assertSessionHas('succes');
        $this->assertSame(2, Presence::where('statut', StatutPresence::Absent)->count());
        Storage::disk('local')->assertMissing($justificatif->fichier_chemin);
    }

    public function test_droits_d_acces(): void
    {
        foreach ([Role::Secretaire, Role::Directeur] as $role) {
            $this->connecterEn($role);
            $this->get('/appels')->assertOk();
            $this->faireAppel(['present', 'present', 'present'])->assertForbidden();
        }
        foreach ([Role::Comptable, Role::Parent, Role::Eleve] as $role) {
            $this->connecterEn($role);
            $this->get('/appels')->assertForbidden();
            $this->get('/justificatifs')->assertForbidden();
        }
        $this->connecterEn(Role::Secretaire);
        $this->get('/justificatifs')->assertForbidden();
        // Enseignant sans classe : appel impossible.
        $this->connecterEn(Role::Enseignant);
        $this->faireAppel(['present', 'present', 'present'])->assertForbidden();

        $this->assertSame(0, Appel::count());
    }

    public function test_les_ecrans_s_affichent(): void
    {
        $this->connecterEn(Role::Censeur);
        $this->faireAppel(['present', 'absent', 'retard']);
        $eleve = $this->inscriptions[1]->eleve;

        foreach (['/appels', route('appels.saisie', [$this->classe, 'date' => $this->jour, 'creneau' => 'matin']),
            route('appels.saisie', [$this->classe, 'date' => $this->jour, 'creneau' => 'apres_midi']),
            '/justificatifs', '/justificatifs/create', '/justificatifs/create?classe='.$this->classe->id, route('eleves.show', $eleve)] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/justificatifs')->assertSee($eleve->nom_complet);
        $this->get(route('eleves.show', $eleve))->assertSee('Assiduité');
    }
}
