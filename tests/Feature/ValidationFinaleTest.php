<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\StatutPaiement;
use App\Models\Bulletin;
use App\Models\Eleve;
use App\Models\Imputation;
use App\Models\Inscription;
use App\Models\Paiement;
use App\Models\User;
use App\Services\MoyenneService;
use App\Services\SoldeService;
use App\Support\Navigation;
use Database\Seeders\DemonstrationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 5 : les données de démonstration (chargées par les services métier) sont cohérentes, et chaque
 * compte peut ouvrir chaque lien de son menu sans erreur.
 */
class ValidationFinaleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DemonstrationSeeder::class);
    }

    public function test_les_donnees_de_demonstration_sont_coherentes(): void
    {
        $this->assertSame(56, Inscription::count());
        $this->assertSame(14, Bulletin::count());

        // Soldes : jamais négatifs, et aucune ligne de frais ne reçoit plus que son montant.
        $soldes = app(SoldeService::class)->soldes(Inscription::pluck('id'));
        $this->assertTrue($soldes->every(fn ($s) => $s['solde'] >= 0 && $s['retard'] >= 0 && $s['retard'] <= $s['solde']));
        $surImputees = Imputation::query()->join('paiements', 'paiements.id', '=', 'imputations.paiement_id')
            ->where('paiements.statut', StatutPaiement::Valide)
            ->join('frais_eleves', 'frais_eleves.id', '=', 'imputations.frais_eleve_id')
            ->groupBy('imputations.frais_eleve_id', 'frais_eleves.montant_du')
            ->havingRaw('SUM(imputations.montant) > frais_eleves.montant_du')->count();
        $this->assertSame(0, $surImputees);
        // Chaque paiement est entièrement imputé, reçus uniques et séquentiels.
        foreach (Paiement::with('imputations')->get() as $p) {
            $this->assertSame($p->montant, (int) $p->imputations->sum('montant'));
        }
        $numeros = Paiement::orderBy('id')->pluck('numero_recu');
        $this->assertSame($numeros->unique()->count(), $numeros->count());

        // Les bulletins figés correspondent au calcul en direct.
        $bulletin = Bulletin::with(['inscription.classe', 'periode'])->first();
        $direct = app(MoyenneService::class)->resultatsClasse($bulletin->inscription->classe, $bulletin->periode, seulementValidees: true);
        $this->assertEqualsWithDelta($direct['eleves'][$bulletin->inscription_id]['moyenne'], $bulletin->moyenne_generale, 0.001);
        $this->assertSame($direct['eleves'][$bulletin->inscription_id]['rang'], $bulletin->rang);
    }

    public function test_chaque_compte_ouvre_chaque_lien_de_son_menu(): void
    {
        $comptes = User::query()->with('roles')->get()
            ->filter(fn (User $u) => $u->roles->isNotEmpty())
            ->unique(fn (User $u) => $u->roles->pluck('name')->sort()->join(','));
        $this->assertGreaterThanOrEqual(8, $comptes->count(), 'Un compte par rôle est attendu dans la démonstration.');

        foreach ($comptes as $user) {
            $this->actingAs($user);
            $liens = collect(Navigation::pour($user))->flatMap(fn ($s) => $s['liens']);
            $this->assertNotEmpty($liens, "Menu vide pour {$user->name}.");
            foreach ($liens as $lien) {
                $reponse = $this->get(route($lien['route']));
                $this->assertContains($reponse->getStatusCode(), [200, 302], "{$user->name} → {$lien['route']} : HTTP {$reponse->getStatusCode()}");
                if ($reponse->isRedirect()) {
                    $this->get($reponse->headers->get('Location'))->assertOk();
                }
            }
        }
    }

    public function test_les_pages_de_detail_et_documents_de_la_demonstration(): void
    {
        $directeur = User::where('email', 'direction@demo.test')->firstOrFail();
        $this->actingAs($directeur);
        $inscription = Inscription::with('eleve')->first();
        $bulletin = Bulletin::first();
        $paiement = Paiement::first();

        foreach ([route('eleves.show', $inscription->eleve), route('classes.show', $inscription->classe_id), route('finances.show', $inscription),
            route('bulletins.classe', [$bulletin->inscription->classe_id, $bulletin->periode_id]), route('resultats.classe')] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get(route('bulletins.pdf', $bulletin))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->get(route('paiements.recu', $paiement))->assertOk()->assertHeader('content-type', 'application/pdf');

        // Parent de démonstration : ses enfants seulement.
        $parent = User::role(Role::Parent->value)->firstOrFail();
        $this->actingAs($parent)->get('/famille')->assertOk();
        $sonEnfant = $parent->responsable->eleves()->first();
        $autre = Eleve::whereNotIn('id', $parent->responsable->eleves()->pluck('eleves.id'))->first();
        $this->get(route('famille.enfant', [$sonEnfant, 'resultats']))->assertOk();
        $this->get(route('famille.enfant', [$autre, 'resultats']))->assertForbidden();

        // Élève de démonstration : connexion par matricule.
        $eleve = User::role(Role::Eleve->value)->firstOrFail();
        auth()->logout();
        $this->post('/connexion', ['identifiant' => $eleve->eleve->matricule, 'password' => DemonstrationSeeder::MOT_DE_PASSE])->assertRedirect();
        $this->assertAuthenticatedAs($eleve);
        $this->get('/mon-espace/devoirs')->assertOk()->assertSee('Exercices sur les fractions');
    }
}
