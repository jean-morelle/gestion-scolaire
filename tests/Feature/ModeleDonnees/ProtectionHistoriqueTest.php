<?php

namespace Tests\Feature\ModeleDonnees;

use App\Enums\StatutPaiement;
use App\Models\FraisEleve;
use App\Models\JournalActivite;
use App\Models\Paiement;
use App\Models\Remise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

/**
 * Les paiements, remises et le journal forment un historique qui ne doit jamais être réécrit.
 */
class ProtectionHistoriqueTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_paiement_ne_peut_pas_etre_supprime(): void
    {
        $paiement = Paiement::factory()->create();

        $this->expectException(LogicException::class);

        $paiement->delete();
    }

    public function test_le_montant_d_un_paiement_ne_peut_pas_etre_modifie(): void
    {
        $paiement = Paiement::factory()->create(['montant' => 25000]);

        try {
            $paiement->update(['montant' => 1000]);
            $this->fail('La modification du montant aurait dû être refusée.');
        } catch (LogicException) {
            $this->assertSame(25000, $paiement->fresh()->montant);
        }
    }

    public function test_un_paiement_peut_etre_annule(): void
    {
        $paiement = Paiement::factory()->create();
        $gestionnaire = User::factory()->create();

        $paiement->update([
            'statut' => StatutPaiement::Annule,
            'annule_par' => $gestionnaire->id,
            'annule_at' => now(),
            'motif_annulation' => 'Erreur de saisie',
        ]);

        $paiement->refresh();
        $this->assertTrue($paiement->estAnnule());
        $this->assertSame(0, Paiement::valides()->count());
        $this->assertSame(1, Paiement::count());
    }

    public function test_une_remise_ne_peut_pas_etre_supprimee(): void
    {
        $remise = Remise::create([
            'frais_eleve_id' => FraisEleve::factory()->create()->id,
            'montant' => 10000,
            'motif' => 'Fratrie',
        ]);

        $this->expectException(LogicException::class);

        $remise->delete();
    }

    public function test_le_journal_d_activite_est_en_ajout_seul(): void
    {
        $entree = JournalActivite::create(['action' => 'test.action', 'description' => 'Entrée de test']);

        try {
            $entree->update(['description' => 'Falsifiée']);
            $this->fail('La modification du journal aurait dû être refusée.');
        } catch (LogicException) {
        }

        try {
            $entree->delete();
            $this->fail('La suppression du journal aurait dû être refusée.');
        } catch (LogicException) {
        }

        $this->assertSame('Entrée de test', $entree->fresh()->description);
    }
}
