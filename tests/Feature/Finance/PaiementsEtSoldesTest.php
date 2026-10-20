<?php

namespace Tests\Feature\Finance;

use App\Enums\Role;
use App\Enums\StatutPaiement;
use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\FraisEleve;
use App\Models\Imputation;
use App\Models\Inscription;
use App\Models\JournalActivite;
use App\Models\Paiement;
use App\Models\Remise;
use App\Models\Responsable;
use App\Models\Sequence;
use App\Models\TypeFrais;
use App\Services\SoldeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Élève de référence : inscription 25 000 (échue), 1re tranche 50 000 (échue), 2e tranche 40 000 (à venir).
 * Total dû 115 000, dont 75 000 échus.
 */
class PaiementsEtSoldesTest extends TestCase
{
    use RefreshDatabase;

    private Inscription $inscription;

    private FraisEleve $fraisInscription;

    private FraisEleve $tranche1;

    private FraisEleve $tranche2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->initialiserApplication();

        $annee = AnneeScolaire::factory()->active()->create(['date_debut' => now()->subMonths(2), 'date_fin' => now()->addMonths(8)]);
        $this->inscription = Inscription::factory()->create(['classe_id' => Classe::factory()->create(['annee_scolaire_id' => $annee->id])->id]);
        $inscription = TypeFrais::where('code', 'INSCRIPTION')->first() ?? TypeFrais::factory()->create();
        $scolarite = TypeFrais::factory()->create(['libelle' => 'Scolarité']);
        $this->fraisInscription = $this->frais($inscription, 'Inscription', 25000, now()->subMonth());
        $this->tranche1 = $this->frais($scolarite, 'Scolarité — 1re tranche', 50000, now()->subDays(5));
        $this->tranche2 = $this->frais($scolarite, 'Scolarité — 2e tranche', 40000, now()->addMonths(2));
    }

    private function frais(TypeFrais $type, string $libelle, int $montant, $date): FraisEleve
    {
        return FraisEleve::create(['inscription_id' => $this->inscription->id, 'type_frais_id' => $type->id, 'libelle' => $libelle, 'montant_du' => $montant, 'date_limite' => $date]);
    }

    private function encaisser(int $montant, array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->post(route('paiements.store', $this->inscription), array_merge([
            'montant' => (string) $montant, 'date_paiement' => now()->toDateString(), 'mode' => 'especes', 'payeur_nom' => 'M. Koffi',
        ], $extra));
    }

    private function situation(): array
    {
        return app(SoldeService::class)->situation($this->inscription);
    }

    public function test_solde_et_retard_sans_paiement(): void
    {
        $s = $this->situation();
        $this->assertSame([115000, 0, 0, 115000, 75000], [$s['du'], $s['remises'], $s['paye'], $s['solde'], $s['retard']]);
    }

    public function test_paiement_partiel_impute_sur_les_echeances_les_plus_anciennes(): void
    {
        $this->connecterEn(Role::Comptable);

        $this->encaisser(60000)->assertSessionHasNoErrors()->assertSessionHas('succes');

        $paiement = Paiement::firstOrFail();
        $this->assertSame('REC-'.now()->year.'-000001', $paiement->numero_recu);
        $imputations = Imputation::orderBy('id')->get();
        $this->assertSame([[$this->fraisInscription->id, 25000], [$this->tranche1->id, 35000]],
            $imputations->map(fn ($i) => [$i->frais_eleve_id, $i->montant])->all());

        $s = $this->situation();
        $this->assertSame([60000, 55000, 15000], [$s['paye'], $s['solde'], $s['retard']]);
        $this->assertTrue(JournalActivite::where('action', 'paiement.enregistre')->exists());
    }

    public function test_pas_de_paiement_superieur_au_reste_du(): void
    {
        $this->connecterEn(Role::Comptable);

        $this->encaisser(115001)->assertSessionHasErrors('montant');
        $this->encaisser(0)->assertSessionHasErrors('montant');
        $this->encaisser(115000)->assertSessionHasNoErrors();
        $this->assertSame(0, $this->situation()['solde']);
        $this->encaisser(1)->assertSessionHasErrors('montant');
        $this->assertSame(1, Paiement::count());
    }

    public function test_les_numeros_de_recu_sont_sequentiels_sans_trou(): void
    {
        $this->connecterEn(Role::Comptable);

        $this->encaisser(10000)->assertSessionHasNoErrors();
        // Un encaissement refusé ne consomme pas de numéro (transaction annulée).
        $this->encaisser(999999)->assertSessionHasErrors('montant');
        $this->encaisser(5000, ['mode' => 'mobile_money', 'reference_externe' => ''])->assertSessionHasErrors('reference_externe');
        $this->encaisser(10000)->assertSessionHasNoErrors();
        $this->encaisser(10000, ['mode' => 'mobile_money', 'reference_externe' => 'MM-123'])->assertSessionHasNoErrors();

        $annee = now()->year;
        $this->assertSame(["REC-{$annee}-000001", "REC-{$annee}-000002", "REC-{$annee}-000003"], Paiement::orderBy('id')->pluck('numero_recu')->all());
        $this->assertSame(3, Sequence::where('cle', "recu-{$annee}")->value('valeur'));
    }

    public function test_imputation_manuelle(): void
    {
        $this->connecterEn(Role::Comptable);

        // Payer d'avance la 2e tranche uniquement.
        $this->encaisser(40000, ['repartition' => 'manuelle', 'imputations' => [$this->tranche2->id => 40000]])->assertSessionHasNoErrors();
        $this->assertSame($this->tranche2->id, Imputation::first()->frais_eleve_id);
        // Le retard reste entier : 75 000 échus − 40 000 payés.
        $this->assertSame(35000, $this->situation()['retard']);

        // Répartition incohérente.
        $this->encaisser(10000, ['repartition' => 'manuelle', 'imputations' => [$this->tranche1->id => 5000]])->assertSessionHasErrors('imputations');
        $this->encaisser(10000, ['repartition' => 'manuelle', 'imputations' => [$this->tranche2->id => 10000]])->assertSessionHasErrors('imputations');
        $this->assertSame(1, Paiement::count());
    }

    public function test_annulation_d_un_paiement(): void
    {
        $this->connecterEn(Role::Comptable);
        $this->encaisser(60000);
        $paiement = Paiement::firstOrFail();

        // Le comptable ne peut pas annuler ; le directeur oui, avec motif.
        $this->post(route('paiements.annuler', $paiement), ['motif_annulation' => 'Erreur'])->assertForbidden();
        $this->connecterEn(Role::Directeur);
        $this->post(route('paiements.annuler', $paiement), ['motif_annulation' => ''])->assertSessionHasErrors('motif_annulation');
        $this->post(route('paiements.annuler', $paiement), ['motif_annulation' => 'Chèque sans provision'])->assertSessionHas('succes');

        $paiement->refresh();
        $this->assertSame(StatutPaiement::Annule, $paiement->statut);
        $this->assertSame(60000, $paiement->montant);
        $this->assertSame(115000, $this->situation()['solde']);
        $this->assertTrue(JournalActivite::where('action', 'paiement.annule')->exists());
        $this->post(route('paiements.annuler', $paiement), ['motif_annulation' => 'Encore'])->assertSessionHasErrors('motif_annulation');

        // Le reçu reste consultable, avec la mention ANNULÉ.
        $pdf = $this->get(route('paiements.recu', $paiement))->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
    }

    public function test_remise_et_annulation_de_remise(): void
    {
        $this->connecterEn(Role::Directeur);

        $this->post(route('remises.store', $this->tranche1), ['montant' => '60000', 'motif' => 'Trop'])->assertSessionHasErrors('montant');
        $this->post(route('remises.store', $this->tranche1), ['montant' => '10000', 'motif' => 'Fratrie'])->assertSessionHasNoErrors();

        $s = $this->situation();
        $this->assertSame([115000, 10000, 105000, 65000], [$s['du'], $s['remises'], $s['solde'], $s['retard']]);
        $this->assertSame(50000, $this->tranche1->fresh()->montant_du);

        // Tranche réglée après remise (40 000), puis remise annulée : les 10 000 redeviennent dus.
        $this->connecterEn(Role::Comptable);
        $this->encaisser(40000, ['repartition' => 'manuelle', 'imputations' => [$this->tranche1->id => 40000]])->assertSessionHasNoErrors();
        $remise = Remise::firstOrFail();
        $this->connecterEn(Role::Directeur);
        $this->post(route('remises.annuler', $remise), ['motif_annulation' => ''])->assertSessionHasErrors('motif_annulation');
        $this->post(route('remises.annuler', $remise), ['motif_annulation' => 'Bourse non confirmée'])->assertSessionHas('succes');

        $this->assertNotNull($remise->fresh()->annulee_at);
        $ligne = $this->situation()['lignes']->first(fn ($l) => $l['frais']->id === $this->tranche1->id);
        $this->assertSame([50000, 0, 40000, 10000], [$ligne['du'], $ligne['remises'], $ligne['paye'], $ligne['reste']]);
        $this->assertSame(75000, $this->situation()['solde']);
        $this->assertTrue(JournalActivite::where('action', 'remise.annulee')->exists());
        // Jamais supprimée.
        $this->assertSame(1, Remise::count());
        $this->post(route('remises.annuler', $remise), ['motif_annulation' => 'Encore'])->assertSessionHasErrors('motif_annulation');
    }

    public function test_droits_d_acces_et_recu_des_familles(): void
    {
        $this->connecterEn(Role::Comptable);
        $this->encaisser(10000);
        $paiement = Paiement::firstOrFail();

        foreach ([Role::Secretaire, Role::Enseignant, Role::Censeur, Role::Parent] as $role) {
            $this->connecterEn($role);
            $this->encaisser(1000)->assertForbidden();
            $this->get('/finances/eleves')->assertForbidden();
            $this->post(route('remises.store', $this->tranche1), ['montant' => '100', 'motif' => 'x'])->assertForbidden();
        }
        $this->connecterEn(Role::Comptable);
        $this->post(route('remises.store', $this->tranche1), ['montant' => '100', 'motif' => 'x'])->assertForbidden();

        // Reçu : le parent de l'élève oui, un autre parent non.
        $parent = $this->utilisateur(Role::Parent);
        $this->inscription->eleve->responsables()->attach(Responsable::factory()->create(['user_id' => $parent->id]), ['lien' => 'pere']);
        $this->actingAs($parent)->get(route('paiements.recu', $paiement))->assertOk();
        $autre = $this->utilisateur(Role::Parent);
        Responsable::factory()->create(['user_id' => $autre->id]);
        $this->actingAs($autre)->get(route('paiements.recu', $paiement))->assertForbidden();

        $this->assertSame(1, Paiement::count());
        $this->assertSame(0, Remise::count());
    }

    public function test_les_ecrans_s_affichent_et_exports(): void
    {
        $this->connecterEn(Role::Comptable);
        $this->encaisser(30000);

        $this->get('/finances/eleves')->assertOk()->assertSee($this->inscription->eleve->nom_complet);
        $this->get('/finances/eleves?situation=retard')->assertOk()->assertSee($this->inscription->eleve->nom_complet);
        $this->get('/finances/eleves?situation=regle')->assertOk()->assertDontSee($this->inscription->eleve->nom_complet);
        $this->get(route('finances.show', $this->inscription))->assertOk()->assertSee('Encaisser un paiement');
        $this->get('/finances/paiements')->assertOk()->assertSee('REC-');
        $this->assertStringContainsString('30000', $this->get('/finances/eleves?format=csv')->streamedContent());
        $this->assertStringContainsString('REC-', $this->get('/finances/paiements?format=csv')->streamedContent());

        $this->connecterEn(Role::Directeur);
        $this->get(route('finances.show', $this->inscription))->assertOk()->assertDontSee('Encaisser un paiement')->assertSee('Remise');
    }
}
