<?php

namespace Tests\Feature\Scolarite;

use App\Enums\Role;
use App\Enums\StatutAnnee;
use App\Enums\StatutInscription;
use App\Enums\TypeInscription;
use App\Models\AnneeScolaire;
use App\Models\ChangementClasse;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\GrilleFrais;
use App\Models\Inscription;
use App\Models\JournalActivite;
use App\Models\Niveau;
use App\Models\Tranche;
use App\Models\TypeFrais;
use App\Services\InscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InscriptionsEtClassesTest extends TestCase
{
    use RefreshDatabase;

    private AnneeScolaire $precedente;

    private AnneeScolaire $annee;

    private Niveau $sixieme;

    private Niveau $cinquieme;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initialiserApplication();

        $this->precedente = AnneeScolaire::factory()->create(['libelle' => '2025-2026', 'date_debut' => '2025-09-01', 'date_fin' => '2026-07-15', 'statut' => StatutAnnee::Cloturee]);
        $this->annee = AnneeScolaire::factory()->active()->create(['libelle' => '2026-2027', 'date_debut' => '2026-09-01', 'date_fin' => '2027-07-15']);
        $this->sixieme = Niveau::where('libelle', '6e')->firstOrFail();
        $this->cinquieme = Niveau::where('libelle', '5e')->firstOrFail();
    }

    private function classe(string $nom, ?AnneeScolaire $annee = null, ?Niveau $niveau = null, ?int $capacite = 50): Classe
    {
        return Classe::factory()->create([
            'annee_scolaire_id' => ($annee ?? $this->annee)->id, 'niveau_id' => ($niveau ?? $this->sixieme)->id, 'nom' => $nom, 'capacite' => $capacite,
        ]);
    }

    public function test_inscription_puis_refus_d_une_seconde_inscription_la_meme_annee(): void
    {
        $this->connecterEn(Role::Secretaire);
        $a = $this->classe('6e A');
        $b = $this->classe('6e B');
        $eleve = Eleve::factory()->create();

        $this->post(route('inscriptions.store', $eleve), ['classe_id' => $a->id, 'date_inscription' => '2026-09-02'])
            ->assertSessionHasNoErrors()->assertSessionHas('succes');
        $this->post(route('inscriptions.store', $eleve), ['classe_id' => $b->id, 'date_inscription' => '2026-09-03'])
            ->assertSessionHasErrors('classe_id');

        $this->assertSame(1, Inscription::where('eleve_id', $eleve->id)->count());
        $this->assertSame(TypeInscription::Nouvelle, $eleve->inscriptions()->first()->type);
    }

    public function test_reinscription_detectee_et_annee_cloturee_refusee(): void
    {
        $this->connecterEn(Role::Secretaire);
        $ancienne = $this->classe('6e A', $this->precedente);
        $nouvelle = $this->classe('5e A', niveau: $this->cinquieme);
        $eleve = Eleve::factory()->create();
        Inscription::factory()->create(['eleve_id' => $eleve->id, 'classe_id' => $ancienne->id]);

        // Année clôturée : lecture seule.
        $autre = Eleve::factory()->create();
        $this->post(route('inscriptions.store', $autre), ['classe_id' => $ancienne->id, 'date_inscription' => '2026-01-10'])
            ->assertSessionHasErrors('classe_id');

        $this->post(route('inscriptions.store', $eleve), ['classe_id' => $nouvelle->id, 'date_inscription' => '2026-09-01'])
            ->assertSessionHasNoErrors();
        $this->assertSame(TypeInscription::Reinscription, $eleve->inscriptionPour($this->annee)->type);
    }

    public function test_reinscription_en_lot(): void
    {
        $this->connecterEn(Role::Secretaire);
        $source = $this->classe('6e A', $this->precedente);
        $cible = $this->classe('5e A', niveau: $this->cinquieme);
        $redoublement = $this->classe('6e B');
        $inscriptions = Inscription::factory()->count(3)->create(['classe_id' => $source->id]);
        $dejaInscrit = $inscriptions[2]->eleve;
        Inscription::factory()->create(['eleve_id' => $dejaInscrit->id, 'classe_id' => $redoublement->id]);

        $this->get(route('inscriptions.reinscriptions', ['source' => $source->id]))->assertOk()->assertSee('Déjà inscrit');

        $this->post(route('inscriptions.reinscriptions.store'), [
            'classe_id' => $cible->id, 'date_inscription' => '2026-09-01',
            'eleves' => $inscriptions->pluck('eleve_id')->all(), 'redoublants' => [$inscriptions[0]->eleve_id],
        ])->assertSessionHas('succes', '2 élève(s) inscrit(s) en 5e A.')->assertSessionHas('avertissement');

        $this->assertSame(2, $cible->inscriptions()->count());
        $this->assertTrue($inscriptions[0]->eleve->inscriptionPour($this->annee)->redoublant);
        $this->assertSame($redoublement->id, $dejaInscrit->inscriptionPour($this->annee)->classe_id);
    }

    public function test_changement_de_classe_conserve_l_inscription_et_trace_l_historique(): void
    {
        $this->connecterEn(Role::Secretaire);
        $a = $this->classe('6e A');
        $b = $this->classe('6e B');
        $inscription = Inscription::factory()->create(['classe_id' => $a->id]);

        $this->post(route('inscriptions.changer-classe', $inscription), [
            'nouvelle_classe_id' => $b->id, 'date_effet' => '2026-10-01', 'motif' => 'Équilibrage des effectifs',
        ])->assertSessionHasNoErrors()->assertSessionHas('succes');

        $inscription->refresh();
        $this->assertSame($b->id, $inscription->classe_id);
        $this->assertSame(1, Inscription::count());
        $changement = ChangementClasse::firstOrFail();
        $this->assertSame([$a->id, $b->id], [$changement->ancienne_classe_id, $changement->nouvelle_classe_id]);
        $this->assertTrue(JournalActivite::where('action', 'inscription.classe_changee')->exists());

        // Classe d'une autre année refusée.
        $autreAnnee = $this->classe('6e C', AnneeScolaire::factory()->create());
        $this->post(route('inscriptions.changer-classe', $inscription), ['nouvelle_classe_id' => $autreAnnee->id, 'date_effet' => '2026-10-02'])
            ->assertSessionHasErrors('classe_id');
    }

    public function test_depart_puis_reintegration(): void
    {
        $this->connecterEn(Role::Secretaire);
        $classe = $this->classe('6e A');
        $inscription = Inscription::factory()->create(['classe_id' => $classe->id, 'date_inscription' => '2026-09-01']);

        $this->post(route('inscriptions.depart', $inscription), ['date_sortie' => '2026-08-01', 'motif_sortie' => 'Erreur'])
            ->assertSessionHasErrors('date_sortie');
        $this->post(route('inscriptions.depart', $inscription), ['date_sortie' => now()->toDateString(), 'motif_sortie' => 'Déménagement'])
            ->assertSessionHasNoErrors();

        $inscription->refresh();
        $this->assertSame(StatutInscription::Parti, $inscription->statut);
        $this->assertSame(0, $classe->inscriptionsActives()->count());
        $this->assertSame(1, $classe->inscriptions()->count());

        $this->post(route('inscriptions.annuler-depart', $inscription))->assertSessionHas('succes');
        $this->assertSame(StatutInscription::Inscrit, $inscription->fresh()->statut);
        $this->assertNull($inscription->fresh()->date_sortie);
    }

    public function test_avertissement_non_bloquant_si_la_capacite_est_depassee(): void
    {
        $this->connecterEn(Role::Secretaire);
        $classe = $this->classe('6e A', capacite: 1);
        Inscription::factory()->create(['classe_id' => $classe->id]);

        $this->post(route('inscriptions.store', Eleve::factory()->create()), ['classe_id' => $classe->id, 'date_inscription' => '2026-09-02'])
            ->assertSessionHas('succes')->assertSessionHas('avertissement');
        $this->assertSame(2, $classe->inscriptionsActives()->count());
    }

    public function test_les_frais_dus_sont_generes_a_partir_de_la_grille(): void
    {
        $scolarite = TypeFrais::factory()->create(['libelle' => 'Scolarité']);
        $tenue = TypeFrais::factory()->create(['libelle' => 'Tenue']);
        $grille = GrilleFrais::factory()->create(['annee_scolaire_id' => $this->annee->id, 'niveau_id' => $this->sixieme->id, 'type_frais_id' => $scolarite->id, 'montant' => 90000]);
        Tranche::factory()->create(['grille_frais_id' => $grille->id, 'libelle' => '1re tranche', 'montant' => 50000, 'date_limite' => '2026-10-15', 'ordre' => 1]);
        Tranche::factory()->create(['grille_frais_id' => $grille->id, 'libelle' => '2e tranche', 'montant' => 40000, 'date_limite' => '2027-01-15', 'ordre' => 2]);
        // Grille propre à la catégorie « affecte » : remplace la grille générale du même type.
        GrilleFrais::factory()->create(['annee_scolaire_id' => $this->annee->id, 'niveau_id' => $this->sixieme->id, 'type_frais_id' => $tenue->id, 'montant' => 15000]);
        GrilleFrais::factory()->create(['annee_scolaire_id' => $this->annee->id, 'niveau_id' => $this->sixieme->id, 'type_frais_id' => $tenue->id, 'categorie' => 'affecte', 'montant' => 5000]);
        // Autre niveau : ignoré.
        GrilleFrais::factory()->create(['annee_scolaire_id' => $this->annee->id, 'niveau_id' => $this->cinquieme->id, 'type_frais_id' => $scolarite->id]);

        $this->actingAs($this->utilisateur(Role::Secretaire));
        $service = app(InscriptionService::class);
        $classe = $this->classe('6e A');

        $normal = $service->inscrire(Eleve::factory()->create(), $classe, ['date_inscription' => '2026-09-02']);
        // Triés par échéance : la tenue (sans tranche) est due à l'inscription.
        $this->assertSame([15000, 50000, 40000], $normal->fraisEleves()->pluck('montant_du')->all());
        $this->assertSame(['Tenue', 'Scolarité — 1re tranche', 'Scolarité — 2e tranche'], $normal->fraisEleves()->pluck('libelle')->all());
        $this->assertSame('2026-09-02', $normal->fraisEleves()->first()->date_limite->format('Y-m-d'));

        $affecte = $service->inscrire(Eleve::factory()->create(), $classe, ['date_inscription' => '2026-09-02', 'categorie_frais' => 'affecte']);
        $this->assertSame(95000, (int) $affecte->fraisEleves()->sum('montant_du'));

        // Idempotent.
        $this->assertSame(0, $service->genererFrais($normal));
        $this->assertSame(3, $normal->fraisEleves()->count());
    }

    public function test_un_service_refuse_un_eleve_archive(): void
    {
        $this->actingAs($this->utilisateur(Role::Secretaire));
        $eleve = Eleve::factory()->create(['statut' => 'archive']);

        $this->expectException(ValidationException::class);
        app(InscriptionService::class)->inscrire($eleve, $this->classe('6e A'));
    }

    public function test_gestion_des_classes(): void
    {
        $this->connecterEn(Role::Directeur);

        $this->post('/classes', ['annee_scolaire_id' => $this->annee->id, 'niveau_id' => $this->sixieme->id, 'nom' => '6e A', 'capacite' => 45])
            ->assertSessionHasNoErrors();
        $this->post('/classes', ['annee_scolaire_id' => $this->annee->id, 'niveau_id' => $this->sixieme->id, 'nom' => '6e A'])
            ->assertSessionHasErrors('nom');
        $this->post('/classes', ['annee_scolaire_id' => $this->precedente->id, 'niveau_id' => $this->sixieme->id, 'nom' => '6e Z'])
            ->assertSessionHasErrors('annee_scolaire_id');

        $classe = Classe::where('nom', '6e A')->firstOrFail();
        $this->get('/classes')->assertOk()->assertSee('6e A');
        $this->get(route('classes.show', $classe))->assertOk();
        $this->get(route('classes.show', [$classe, 'format' => 'csv']))->assertOk()->assertDownload('classe-6e A.csv');

        $this->put(route('classes.update', $classe), ['annee_scolaire_id' => $this->annee->id, 'niveau_id' => $this->sixieme->id, 'nom' => '6e A1', 'capacite' => 40])
            ->assertSessionHasNoErrors();
        $this->assertSame('6e A1', $classe->fresh()->nom);

        Inscription::factory()->create(['classe_id' => $classe->id]);
        $this->delete(route('classes.destroy', $classe))->assertSessionHas('erreur');
        $this->assertModelExists($classe);

        $vide = $this->classe('6e B');
        $this->delete(route('classes.destroy', $vide))->assertSessionHas('succes');
        $this->assertModelMissing($vide);
    }

    public function test_dupliquer_les_classes_d_une_annee(): void
    {
        $this->connecterEn(Role::Administrateur);
        $this->classe('6e A', $this->precedente);
        $this->classe('5e A', $this->precedente, $this->cinquieme);
        $this->classe('6e A');

        $this->post(route('classes.dupliquer'), ['source_id' => $this->precedente->id, 'cible_id' => $this->annee->id])
            ->assertSessionHas('succes');

        $this->assertEqualsCanonicalizing(['6e A', '5e A'], $this->annee->classes()->pluck('nom')->all());
    }

    public function test_droits_d_acces_aux_classes_et_inscriptions(): void
    {
        $classe = $this->classe('6e A');
        $inscription = Inscription::factory()->create(['classe_id' => $classe->id]);

        // La secrétaire consulte les classes mais ne les crée pas.
        $this->connecterEn(Role::Secretaire);
        $this->get('/classes')->assertOk();
        $this->get('/classes/create')->assertForbidden();
        $this->get('/inscriptions')->assertOk();

        // Le directeur gère les classes mais n'inscrit pas.
        $this->connecterEn(Role::Directeur);
        $this->get('/inscriptions')->assertForbidden();
        $this->post(route('inscriptions.depart', $inscription), ['date_sortie' => now()->toDateString(), 'motif_sortie' => 'x'])->assertForbidden();

        foreach ([Role::Enseignant, Role::Parent, Role::Eleve, Role::Comptable] as $role) {
            $this->connecterEn($role);
            $this->post(route('inscriptions.store', $inscription->eleve), ['classe_id' => $classe->id, 'date_inscription' => '2026-09-01'])->assertForbidden();
            $this->get('/classes/create')->assertForbidden();
        }
        foreach ([Role::Enseignant, Role::Parent, Role::Eleve] as $role) {
            $this->connecterEn($role);
            $this->get('/classes')->assertForbidden();
        }

        $this->assertSame(StatutInscription::Inscrit, $inscription->fresh()->statut);
    }

    public function test_liste_des_inscriptions_filtres_et_export(): void
    {
        $this->connecterEn(Role::Secretaire);
        $a = $this->classe('6e A');
        $b = $this->classe('6e B');
        $ama = Eleve::factory()->create(['nom' => 'KOFFI', 'prenoms' => 'Ama']);
        $yao = Eleve::factory()->create(['nom' => 'MENSAH', 'prenoms' => 'Yao']);
        Inscription::factory()->create(['eleve_id' => $ama->id, 'classe_id' => $a->id]);
        Inscription::factory()->create(['eleve_id' => $yao->id, 'classe_id' => $b->id]);

        $this->get('/inscriptions')->assertOk()->assertSee('KOFFI Ama')->assertSee('MENSAH Yao');
        $this->get('/inscriptions?classe='.$a->id)->assertSee('KOFFI Ama')->assertDontSee('MENSAH Yao');
        $this->get('/inscriptions?q=mensah')->assertSee('MENSAH Yao')->assertDontSee('KOFFI Ama');
        $this->assertStringContainsString('MENSAH;Yao', $this->get('/inscriptions?format=csv')->streamedContent());
    }
}
