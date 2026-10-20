<?php

namespace Tests\Feature\Pedagogie;

use App\Enums\Role;
use App\Models\Affectation;
use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\Cours;
use App\Models\Devoir;
use App\Models\Enseignant;
use App\Models\Inscription;
use App\Models\JournalActivite;
use App\Models\Matiere;
use App\Models\RemiseDevoir;
use App\Models\Responsable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CoursEtDevoirsTest extends TestCase
{
    use RefreshDatabase;

    private Classe $classe;

    private Affectation $maths;

    private Enseignant $prof;

    private User $ama;

    private User $kofi;

    private Inscription $inscriptionAma;

    private Inscription $inscriptionKofi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initialiserApplication();
        Storage::fake('local');

        $annee = AnneeScolaire::factory()->active()->create();
        $this->classe = Classe::factory()->create(['annee_scolaire_id' => $annee->id, 'nom' => '6e A']);
        $this->prof = Enseignant::factory()->create(['user_id' => $this->utilisateur(Role::Enseignant)->id]);
        $this->maths = Affectation::factory()->create(['classe_id' => $this->classe->id, 'matiere_id' => Matiere::where('code', 'MATH')->value('id'), 'enseignant_id' => $this->prof->id]);

        [$this->ama, $this->inscriptionAma] = $this->eleve('Ama');
        [$this->kofi, $this->inscriptionKofi] = $this->eleve('Kofi');
    }

    /**
     * @return array{0: User, 1: Inscription}
     */
    private function eleve(string $prenom, ?Classe $classe = null): array
    {
        $inscription = Inscription::factory()->create(['classe_id' => ($classe ?? $this->classe)->id]);
        $compte = $this->utilisateur(Role::Eleve);
        $inscription->eleve->update(['prenoms' => $prenom, 'user_id' => $compte->id]);

        return [$compte, $inscription];
    }

    private function coursPublie(): Cours
    {
        $this->actingAs($this->prof->user);
        $this->post('/cours', ['affectation_id' => $this->maths->id, 'titre' => 'Les fractions'])->assertSessionHasNoErrors();
        $cours = Cours::firstOrFail();
        $this->post(route('cours.ressources.store', $cours), ['type' => 'document', 'titre' => 'Fiche de cours',
            'fichier' => UploadedFile::fake()->create('fractions.pdf', 300, 'application/pdf')])->assertSessionHasNoErrors();
        $this->post(route('cours.publier', $cours), ['publier' => '1'])->assertSessionHas('succes');

        return $cours->fresh();
    }

    private function devoirPublie(array $extra = []): Devoir
    {
        $this->actingAs($this->prof->user);
        $this->post('/devoirs', array_merge([
            'affectation_id' => $this->maths->id, 'titre' => 'Exercices page 52', 'consignes' => 'Faire les exercices 4 à 7.',
            'date_limite' => now()->addDays(3)->format('Y-m-d\TH:i'), 'bareme' => '20', 'remise_en_ligne' => '1',
        ], $extra))->assertSessionHasNoErrors();
        $devoir = Devoir::latest('id')->firstOrFail();
        $this->post(route('devoirs.publier', $devoir))->assertSessionHas('succes');

        return $devoir->fresh();
    }

    public function test_cours_publie_et_ressources_privees(): void
    {
        $this->actingAs($this->prof->user);
        $this->post('/cours', ['affectation_id' => $this->maths->id, 'titre' => 'Les fractions'])->assertSessionHasNoErrors();
        $cours = Cours::firstOrFail();
        // Pas de publication sans contenu.
        $this->post(route('cours.publier', $cours), ['publier' => '1'])->assertSessionHas('erreur');
        // Fichier exécutable refusé.
        $this->post(route('cours.ressources.store', $cours), ['type' => 'document', 'titre' => 'Script',
            'fichier' => UploadedFile::fake()->create('virus.php', 5, 'application/x-php')])->assertSessionHasErrors('fichier');
        $this->post(route('cours.ressources.store', $cours), ['type' => 'lien', 'titre' => 'Vidéo', 'url' => 'javascript:alert(1)'])->assertSessionHasErrors('url');
        $this->post(route('cours.ressources.store', $cours), ['type' => 'chapitre', 'titre' => 'Introduction', 'contenu' => 'Une fraction est…'])->assertSessionHasNoErrors();
        $this->post(route('cours.ressources.store', $cours), ['type' => 'document', 'titre' => 'Fiche',
            'fichier' => UploadedFile::fake()->create('fiche.pdf', 100, 'application/pdf')])->assertSessionHasNoErrors();
        $ressource = $cours->ressources()->where('type', 'document')->firstOrFail();
        Storage::disk('local')->assertExists($ressource->fichier_chemin);

        // Brouillon : invisible de l'élève.
        $this->actingAs($this->ama)->get(route('eleve.cours.show', $cours))->assertForbidden();
        $this->get(route('cours.ressources.fichier', [$cours, $ressource]))->assertForbidden();
        $this->get('/mon-espace/cours')->assertOk()->assertDontSee('Les fractions');

        $this->actingAs($this->prof->user)->post(route('cours.publier', $cours), ['publier' => '1'])->assertSessionHas('succes');
        $this->actingAs($this->ama)->get('/mon-espace/cours')->assertOk()->assertSee('Les fractions');
        $this->get(route('eleve.cours.show', $cours))->assertOk()->assertSee('Une fraction est');
        $this->get(route('cours.ressources.fichier', [$cours, $ressource]))->assertOk()->assertDownload('fiche.pdf');

        // Élève d'une autre classe : refusé.
        [$autre] = $this->eleve('Yao', Classe::factory()->create(['annee_scolaire_id' => $this->classe->annee_scolaire_id]));
        $this->actingAs($autre)->get(route('eleve.cours.show', $cours))->assertForbidden();
        $this->get(route('cours.ressources.fichier', [$cours, $ressource]))->assertForbidden();
        $this->assertTrue(JournalActivite::where('action', 'cours.publie')->exists());
    }

    public function test_un_enseignant_ne_gere_que_ses_matieres(): void
    {
        $cours = $this->coursPublie();
        $autre = Enseignant::factory()->create(['user_id' => $this->utilisateur(Role::Enseignant)->id]);

        $this->actingAs($autre->user);
        $this->get(route('cours.create', ['affectation' => $this->maths->id]))->assertForbidden();
        $this->post('/cours', ['affectation_id' => $this->maths->id, 'titre' => 'X'])->assertForbidden();
        $this->get(route('cours.show', $cours))->assertForbidden();
        $this->post(route('cours.publier', $cours), ['publier' => '0'])->assertForbidden();
        $this->post('/devoirs', ['affectation_id' => $this->maths->id])->assertForbidden();
        $this->assertTrue($cours->fresh()->est_publie);

        // La direction consulte sans modifier.
        $this->connecterEn(Role::Directeur);
        $this->get('/cours')->assertOk()->assertSee('Les fractions');
        $this->get(route('cours.show', $cours))->assertOk()->assertDontSee('Ajouter une ressource');
        $this->post(route('cours.publier', $cours), ['publier' => '0'])->assertForbidden();

        foreach ([Role::Secretaire, Role::Comptable, Role::Parent] as $role) {
            $this->connecterEn($role);
            $this->get('/cours')->assertForbidden();
        }
    }

    public function test_devoir_publie_notifie_et_remise_en_ligne(): void
    {
        $parent = $this->utilisateur(Role::Parent);
        $this->inscriptionAma->eleve->responsables()->attach(Responsable::factory()->create(['user_id' => $parent->id]), ['lien' => 'mere']);

        $devoir = $this->devoirPublie();
        $this->assertSame(1, $this->ama->notifications()->count());
        $this->assertSame(1, $parent->notifications()->count());
        $this->assertSame(route('eleve.devoirs.show', $devoir), $this->ama->notifications()->first()->data['lien']);

        $this->actingAs($this->ama);
        $this->get('/mon-espace/devoirs')->assertOk()->assertSee('Exercices page 52');
        $this->post(route('eleve.devoirs.remettre', $devoir), [])->assertSessionHasErrors('fichier');
        $this->post(route('eleve.devoirs.remettre', $devoir), ['fichier' => UploadedFile::fake()->create('copie.exe', 10, 'application/octet-stream')])->assertSessionHasErrors('fichier');
        $this->post(route('eleve.devoirs.remettre', $devoir), ['fichier' => UploadedFile::fake()->create('copie-v1.pdf', 50, 'application/pdf')])->assertSessionHasNoErrors();
        $premier = RemiseDevoir::firstOrFail()->fichier_chemin;

        // Remplacement avant la date limite : l'ancien fichier est supprimé, une seule remise.
        $this->post(route('eleve.devoirs.remettre', $devoir), ['fichier' => UploadedFile::fake()->create('copie-v2.pdf', 50, 'application/pdf'), 'commentaire' => 'Version corrigée'])->assertSessionHasNoErrors();
        $remise = RemiseDevoir::firstOrFail();
        $this->assertSame(1, RemiseDevoir::count());
        $this->assertSame('copie-v2.pdf', $remise->fichier_nom);
        $this->assertFalse($remise->en_retard);
        Storage::disk('local')->assertMissing($premier);
        $this->get(route('devoirs.copie', [$devoir, $remise]))->assertOk()->assertDownload('copie-v2.pdf');
    }

    public function test_un_eleve_ne_voit_jamais_la_copie_d_un_autre(): void
    {
        $devoir = $this->devoirPublie();
        $this->actingAs($this->kofi)->post(route('eleve.devoirs.remettre', $devoir), ['fichier' => UploadedFile::fake()->create('kofi.pdf', 50, 'application/pdf')]);
        $copieKofi = RemiseDevoir::firstOrFail();

        $this->actingAs($this->ama);
        $this->get(route('devoirs.copie', [$devoir, $copieKofi]))->assertForbidden();
        $this->get(route('eleve.devoirs.show', $devoir))->assertOk()->assertDontSee('kofi.pdf');
        $this->get(route('devoirs.show', $devoir))->assertForbidden();

        // Copie d'un autre devoir via ce devoir : refusé (liaison vérifiée).
        $autreDevoir = $this->devoirPublie(['titre' => 'Autre']);
        $this->actingAs($this->kofi)->get(route('devoirs.copie', [$autreDevoir, $copieKofi]))->assertForbidden();

        // L'enseignant et la direction y ont accès.
        $this->actingAs($this->prof->user)->get(route('devoirs.copie', [$devoir, $copieKofi]))->assertOk();
        $this->connecterEn(Role::Censeur);
        $this->get(route('devoirs.copie', [$devoir, $copieKofi]))->assertOk();
    }

    public function test_date_limite_retard_et_correction(): void
    {
        $devoir = $this->devoirPublie();
        $devoir->update(['date_limite' => now()->subHour()]);

        // Retard non accepté : dépôt refusé.
        $this->actingAs($this->ama)->post(route('eleve.devoirs.remettre', $devoir), ['commentaire' => 'Ma réponse'])->assertSessionHasErrors('fichier');
        $devoir->update(['accepte_retard' => true]);
        $this->post(route('eleve.devoirs.remettre', $devoir), ['commentaire' => 'Ma réponse'])->assertSessionHasNoErrors();
        $remise = RemiseDevoir::firstOrFail();
        $this->assertTrue($remise->en_retard);

        // Correction par l'enseignant.
        $this->actingAs($this->prof->user);
        $this->post(route('devoirs.corriger', [$devoir, $this->inscriptionAma]), ['note' => '21'])->assertSessionHasErrors('note');
        $this->post(route('devoirs.corriger', [$devoir, $this->inscriptionAma]), ['note' => '15,5', 'correction' => 'Bon raisonnement'])->assertSessionHasNoErrors();
        $remise->refresh();
        $this->assertSame('15.50', $remise->note);
        $this->assertNotNull($remise->corrige_at);
        // Modifier une note de devoir est journalisé.
        $this->post(route('devoirs.corriger', [$devoir, $this->inscriptionAma]), ['note' => '16'])->assertSessionHasNoErrors();
        $this->assertTrue(JournalActivite::where('action', 'devoir.note_modifiee')->exists());

        // Corrigé : l'élève ne peut plus remplacer son travail, et voit sa note.
        $this->actingAs($this->ama)->post(route('eleve.devoirs.remettre', $devoir), ['commentaire' => 'Nouvelle version'])->assertSessionHasErrors('fichier');
        $this->get(route('eleve.devoirs.show', $devoir))->assertOk()->assertSee('16,00');
        // Un devoir avec des copies ne peut plus être supprimé.
        $this->actingAs($this->prof->user)->delete(route('devoirs.destroy', $devoir))->assertSessionHasErrors('devoir');
    }

    public function test_devoir_rendu_en_classe_et_parametre_de_l_etablissement(): void
    {
        $devoir = $this->devoirPublie(['remise_en_ligne' => '0']);
        $this->actingAs($this->ama)->post(route('eleve.devoirs.remettre', $devoir), ['commentaire' => 'x'])->assertSessionHasErrors('fichier');

        // Notation directe d'un devoir rendu en classe.
        $this->actingAs($this->prof->user)->post(route('devoirs.corriger', [$devoir, $this->inscriptionKofi]), ['note' => '12'])->assertSessionHasNoErrors();
        $this->assertSame('12.00', RemiseDevoir::firstOrFail()->note);

        // Remise en ligne désactivée par l'établissement : refusée même si le devoir l'autorise.
        $etab = etablissement();
        $etab->update(['parametres' => array_merge($etab->parametres, ['remise_devoirs_en_ligne' => false])]);
        $enLigne = $this->devoirPublie(['titre' => 'En ligne']);
        $this->actingAs($this->ama)->post(route('eleve.devoirs.remettre', $enLigne), ['commentaire' => 'x'])->assertSessionHasErrors('fichier');
    }

    public function test_devoir_non_publie_invisible(): void
    {
        $this->actingAs($this->prof->user)->post('/devoirs', [
            'affectation_id' => $this->maths->id, 'titre' => 'Brouillon', 'consignes' => 'x', 'date_limite' => now()->addDay()->format('Y-m-d\TH:i'), 'bareme' => '20',
        ])->assertSessionHasNoErrors();
        $devoir = Devoir::firstOrFail();

        $this->actingAs($this->ama);
        $this->get('/mon-espace/devoirs')->assertDontSee('Brouillon');
        $this->get(route('eleve.devoirs.show', $devoir))->assertForbidden();
        $this->post(route('eleve.devoirs.remettre', $devoir), ['commentaire' => 'x'])->assertForbidden();
        $this->assertSame(0, $this->ama->notifications()->count());
    }

    public function test_les_ecrans_s_affichent(): void
    {
        $cours = $this->coursPublie();
        $devoir = $this->devoirPublie(['cours_id' => $cours->id, 'sujet' => UploadedFile::fake()->create('sujet.pdf', 40, 'application/pdf')]);

        $this->actingAs($this->prof->user);
        foreach (['/cours', route('cours.create', ['affectation' => $this->maths->id]), route('cours.show', $cours), route('cours.edit', $cours),
            route('devoirs.create', ['affectation' => $this->maths->id]), route('devoirs.show', $devoir), route('devoirs.edit', $devoir), route('devoirs.sujet', $devoir)] as $url) {
            $this->get($url)->assertOk();
        }
        $this->actingAs($this->ama);
        foreach (['/mon-espace/cours', route('eleve.cours.show', $cours), '/mon-espace/devoirs', route('eleve.devoirs.show', $devoir), route('devoirs.sujet', $devoir)] as $url) {
            $this->get($url)->assertOk();
        }
    }
}
