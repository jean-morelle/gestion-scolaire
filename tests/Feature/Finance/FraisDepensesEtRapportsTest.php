<?php

namespace Tests\Feature\Finance;

use App\Enums\Role;
use App\Enums\StatutDepense;
use App\Models\AnneeScolaire;
use App\Models\CategorieDepense;
use App\Models\Classe;
use App\Models\Depense;
use App\Models\Eleve;
use App\Models\FraisEleve;
use App\Models\GrilleFrais;
use App\Models\Inscription;
use App\Models\JournalActivite;
use App\Models\Niveau;
use App\Models\TypeFrais;
use App\Services\InscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FraisDepensesEtRapportsTest extends TestCase
{
    use RefreshDatabase;

    private AnneeScolaire $annee;

    private Niveau $sixieme;

    private TypeFrais $scolarite;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initialiserApplication();
        Storage::fake('local');

        $this->annee = AnneeScolaire::factory()->active()->create(['libelle' => '2026-2027', 'date_debut' => '2026-09-01', 'date_fin' => '2027-07-15']);
        $this->sixieme = Niveau::where('libelle', '6e')->firstOrFail();
        $this->scolarite = TypeFrais::factory()->create(['code' => 'SCOL', 'libelle' => 'Scolarité']);
    }

    private function grille(array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->post(route('grilles-frais.store'), array_merge([
            'annee_scolaire_id' => $this->annee->id, 'niveau_id' => $this->sixieme->id, 'type_frais_id' => $this->scolarite->id,
            'montant' => '90 000', 'tranches' => [
                ['libelle' => '1re tranche', 'montant' => '50000', 'date_limite' => '2026-10-15'],
                ['libelle' => '2e tranche', 'montant' => '40000', 'date_limite' => '2027-01-15'],
            ],
        ], $extra));
    }

    public function test_grille_avec_echeancier_dont_la_somme_doit_etre_juste(): void
    {
        $this->connecterEn(Role::Comptable);

        $this->grille(['montant' => '100000'])->assertSessionHasErrors('tranches');
        $this->grille()->assertSessionHasNoErrors();
        $grille = GrilleFrais::firstOrFail();
        $this->assertSame(90000, $grille->montant);
        $this->assertSame([50000, 40000], $grille->tranches->pluck('montant')->all());
        $this->assertTrue(JournalActivite::where('action', 'grille_frais.creee')->exists());

        // Doublon (même type, niveau, catégorie).
        $this->grille()->assertSessionHasErrors('type_frais_id');
        // Même type pour une catégorie : autorisé.
        $this->grille(['categorie' => 'affecte', 'montant' => '30000', 'tranches' => []])->assertSessionHasNoErrors();
        $this->assertSame(2, GrilleFrais::count());
    }

    public function test_modifier_la_grille_ne_change_pas_les_frais_deja_factures(): void
    {
        $this->connecterEn(Role::Comptable);
        $this->grille();
        $grille = GrilleFrais::firstOrFail();
        $classe = Classe::factory()->create(['annee_scolaire_id' => $this->annee->id, 'niveau_id' => $this->sixieme->id]);
        $inscription = app(InscriptionService::class)->inscrire(Eleve::factory()->create(), $classe, ['date_inscription' => '2026-09-02']);
        $this->assertSame(90000, (int) $inscription->fraisEleves()->sum('montant_du'));

        $this->put(route('grilles-frais.update', $grille), [
            'annee_scolaire_id' => $this->annee->id, 'niveau_id' => $this->sixieme->id, 'type_frais_id' => $this->scolarite->id,
            'montant' => '120000', 'tranches' => [],
        ])->assertSessionHasNoErrors();

        $this->assertSame(90000, (int) $inscription->fraisEleves()->sum('montant_du'));
        $this->assertSame(2, $inscription->fraisEleves()->count());
    }

    public function test_frais_facultatifs_et_generation_pour_les_eleves_deja_inscrits(): void
    {
        $classe = Classe::factory()->create(['annee_scolaire_id' => $this->annee->id, 'niveau_id' => $this->sixieme->id]);
        $this->actingAs($this->utilisateur(Role::Secretaire));
        $inscription = app(InscriptionService::class)->inscrire(Eleve::factory()->create(), $classe, ['date_inscription' => '2026-09-02']);
        $this->assertSame(0, $inscription->fraisEleves()->count());

        $this->connecterEn(Role::Comptable);
        $this->grille()->assertSessionHasNoErrors();
        $cantine = TypeFrais::factory()->create(['code' => 'CANTINE', 'libelle' => 'Cantine', 'est_obligatoire' => false]);
        GrilleFrais::factory()->create(['annee_scolaire_id' => $this->annee->id, 'niveau_id' => $this->sixieme->id, 'type_frais_id' => $cantine->id, 'montant' => 30000]);

        // La génération n'ajoute que les frais obligatoires manquants, et une seule fois.
        $this->post(route('grilles-frais.generer', $this->annee))->assertSessionHas('succes');
        $this->post(route('grilles-frais.generer', $this->annee))->assertSessionHas('succes', 'Tous les élèves inscrits ont déjà leurs frais obligatoires.');
        $this->assertSame(90000, (int) $inscription->fraisEleves()->sum('montant_du'));

        // Frais facultatif attribué individuellement, puis retiré (aucun paiement).
        $grilleCantine = GrilleFrais::where('type_frais_id', $cantine->id)->first();
        $this->post(route('finances.attribuer', $inscription), ['grille_frais_id' => $grilleCantine->id])->assertSessionHas('succes');
        $this->post(route('finances.attribuer', $inscription), ['grille_frais_id' => $grilleCantine->id])->assertSessionHasErrors('grille_frais_id');
        $this->assertSame(120000, (int) $inscription->fraisEleves()->sum('montant_du'));
        $this->delete(route('finances.retirer', $inscription), ['type_frais_id' => $cantine->id])->assertSessionHas('succes');
        $this->assertSame(90000, (int) $inscription->fraisEleves()->sum('montant_du'));

        // Un frais déjà payé ne peut pas être retiré.
        $this->post(route('paiements.store', $inscription), ['montant' => '1000', 'date_paiement' => now()->toDateString(), 'mode' => 'especes'])->assertSessionHasNoErrors();
        $this->delete(route('finances.retirer', $inscription), ['type_frais_id' => $this->scolarite->id])->assertSessionHasErrors('frais');
    }

    public function test_copier_les_grilles_vers_l_annee_suivante(): void
    {
        $this->connecterEn(Role::Comptable);
        $this->grille();
        $suivante = AnneeScolaire::factory()->create(['libelle' => '2027-2028', 'date_debut' => '2027-09-01', 'date_fin' => '2028-07-15', 'statut' => 'preparation']);

        $this->post(route('grilles-frais.copier'), ['source_id' => $this->annee->id, 'cible_id' => $suivante->id])->assertSessionHas('succes');

        $copie = GrilleFrais::where('annee_scolaire_id', $suivante->id)->firstOrFail();
        $this->assertSame(['2027-10-15', '2028-01-15'], $copie->tranches->map(fn ($t) => $t->date_limite->format('Y-m-d'))->all());
    }

    public function test_depenses_numerotees_et_annulation(): void
    {
        $this->connecterEn(Role::Comptable);
        $categorie = CategorieDepense::firstOrFail();

        $this->post(route('depenses.store'), [
            'categorie_depense_id' => $categorie->id, 'libelle' => 'Craies et cahiers', 'montant' => '45 000', 'date_depense' => now()->toDateString(),
            'mode' => 'especes', 'justificatif' => UploadedFile::fake()->create('facture.pdf', 120, 'application/pdf'),
        ])->assertSessionHasNoErrors();
        $this->post(route('depenses.store'), [
            'categorie_depense_id' => $categorie->id, 'libelle' => 'Futur', 'montant' => '1000', 'date_depense' => now()->addDays(3)->toDateString(), 'mode' => 'especes',
        ])->assertSessionHasErrors('date_depense');

        $depense = Depense::firstOrFail();
        $this->assertSame('DEP-'.now()->year.'-00001', $depense->numero);
        $this->assertSame(45000, $depense->montant);
        Storage::disk('local')->assertExists($depense->justificatif_chemin);
        $this->get(route('depenses.justificatif', $depense))->assertOk();

        // Le comptable n'annule pas ; le directeur oui.
        $this->post(route('depenses.annuler', $depense), ['motif_annulation' => 'x'])->assertForbidden();
        $this->connecterEn(Role::Directeur);
        $this->post(route('depenses.annuler', $depense), ['motif_annulation' => 'Doublon'])->assertSessionHas('succes');
        $this->assertSame(StatutDepense::Annulee, $depense->fresh()->statut);
        $this->get('/finances/depenses')->assertOk()->assertSee('Annulée');
    }

    public function test_rapport_financier(): void
    {
        $this->connecterEn(Role::Comptable);
        $this->grille();
        $classe = Classe::factory()->create(['annee_scolaire_id' => $this->annee->id, 'niveau_id' => $this->sixieme->id, 'nom' => '6e A']);
        $inscription = app(InscriptionService::class)->inscrire(Eleve::factory()->create(), $classe, ['date_inscription' => '2026-09-02']);
        $this->post(route('paiements.store', $inscription), ['montant' => '50000', 'date_paiement' => now()->toDateString(), 'mode' => 'especes']);
        $this->post(route('depenses.store'), ['categorie_depense_id' => CategorieDepense::first()->id, 'libelle' => 'Électricité',
            'montant' => '20000', 'date_depense' => now()->toDateString(), 'mode' => 'virement']);

        $du = now()->subMonth()->toDateString();
        $reponse = $this->get('/finances/rapports?du='.$du)->assertOk();
        $reponse->assertSee('Scolarité')->assertSee('6e A');
        $this->assertSame(50000, $reponse->viewData('totalRecettes'));
        $this->assertSame(20000, $reponse->viewData('totalDepenses'));
        $this->assertSame(40000, $reponse->viewData('impayes')->first()['solde']);

        $this->assertStringContainsString('"6e A";1;90000;0;50000;40000', $this->get('/finances/rapports?export=impayes')->streamedContent());
        $this->assertStringContainsString('50000;20000;30000', $this->get('/finances/rapports?du='.$du.'&export=mensuel')->streamedContent());

        foreach ([Role::Secretaire, Role::Enseignant, Role::Parent, Role::Censeur] as $role) {
            $this->connecterEn($role);
            $this->get('/finances/rapports')->assertForbidden();
            $this->get('/finances/depenses')->assertForbidden();
            $this->get('/finances/frais')->assertForbidden();
        }
    }

    public function test_les_ecrans_s_affichent(): void
    {
        $this->connecterEn(Role::Comptable);
        $this->grille();
        foreach (['/finances/frais', '/finances/frais/grilles/create', route('grilles-frais.edit', GrilleFrais::first()), '/finances/depenses',
            '/finances/depenses/create', '/finances/paiements', '/finances/eleves', '/finances/rapports'] as $url) {
            $this->get($url)->assertOk();
        }
    }
}
