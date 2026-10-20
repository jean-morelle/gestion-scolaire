<?php

namespace Tests\Feature\Pedagogie;

use App\Models\Affectation;
use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\Etablissement;
use App\Models\Evaluation;
use App\Models\Inscription;
use App\Models\Matiere;
use App\Models\Note;
use App\Models\Periode;
use App\Services\MoyenneService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Jeu d'essai calculé à la main (RG-NOT-02 à 07, 12).
 *
 * Classe de 4 élèves, deux matières : Maths (coef. 4) et Français (coef. 2).
 * Maths : Devoir sur 20 (poids 1) et Composition sur 40 (poids 2).
 * Français : Interrogation sur 10 (poids 1).
 *
 * Ama   : Maths 12/20 et 30/40 (=15) → (12×1 + 15×2) / 3 = 14 ; Français 8/10 = 16
 *         Générale = (14×4 + 16×2) / 6 = 88/6 = 14,666… → 14,67, « Bien »
 * Kofi  : Maths 10/20 et absent → 10 ; Français absent → aucune moyenne
 *         Générale = 10 (seul le coefficient de Maths compte) → « Passable »
 * Yao   : Maths 16/20 et 26/40 (=13) → (16 + 26) / 3 = 14 ; Français 8/10 = 16 → 14,67, ex æquo avec Ama
 * Afi   : aucune note → non classée
 * Rangs : Ama 1, Yao 1, Kofi 3 (rang 2 sauté), Afi non classée.
 */
class CalculDesMoyennesTest extends TestCase
{
    use RefreshDatabase;

    private MoyenneService $service;

    private Classe $classe;

    private Periode $periode;

    /** @var array<string, Inscription> */
    private array $eleves = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->initialiserApplication();
        $this->service = app(MoyenneService::class);

        $annee = AnneeScolaire::factory()->active()->create();
        $this->periode = Periode::factory()->create(['annee_scolaire_id' => $annee->id, 'date_debut' => now()->subMonth(), 'date_fin' => now()->addMonth()]);
        $this->classe = Classe::factory()->create(['annee_scolaire_id' => $annee->id]);
        $maths = Affectation::factory()->create(['classe_id' => $this->classe->id, 'matiere_id' => Matiere::where('code', 'MATH')->value('id'), 'coefficient' => 4, 'ordre_bulletin' => 1]);
        $francais = Affectation::factory()->create(['classe_id' => $this->classe->id, 'matiere_id' => Matiere::where('code', 'FR')->value('id'), 'coefficient' => 2, 'ordre_bulletin' => 2]);

        foreach (['Ama', 'Kofi', 'Yao', 'Afi'] as $prenom) {
            $this->eleves[$prenom] = Inscription::factory()->create(['classe_id' => $this->classe->id, 'date_inscription' => now()->subMonths(2)]);
            $this->eleves[$prenom]->eleve->update(['prenoms' => $prenom]);
        }

        $devoir = $this->evaluation($maths, 20, 1);
        $compo = $this->evaluation($maths, 40, 2);
        $interro = $this->evaluation($francais, 10, 1);

        $this->note($devoir, 'Ama', 12);
        $this->note($compo, 'Ama', 30);
        $this->note($interro, 'Ama', 8);
        $this->note($devoir, 'Kofi', 10);
        $this->note($compo, 'Kofi', null, true);
        $this->note($interro, 'Kofi', null, true);
        $this->note($devoir, 'Yao', 16);
        $this->note($compo, 'Yao', 26);
        $this->note($interro, 'Yao', 8);
    }

    private function evaluation(Affectation $affectation, float $bareme, float $poids): Evaluation
    {
        return Evaluation::factory()->validee()->create([
            'affectation_id' => $affectation->id, 'periode_id' => $this->periode->id, 'bareme' => $bareme, 'poids' => $poids,
        ]);
    }

    private function note(Evaluation $evaluation, string $eleve, ?float $valeur, bool $absent = false): void
    {
        Note::factory()->create(['evaluation_id' => $evaluation->id, 'inscription_id' => $this->eleves[$eleve]->id, 'valeur' => $valeur, 'est_absent' => $absent]);
    }

    public function test_moyennes_rangs_et_mentions_du_jeu_d_essai(): void
    {
        $r = $this->service->resultatsClasse($this->classe, $this->periode);
        $e = fn (string $prenom) => $r['eleves'][$this->eleves[$prenom]->id];
        $maths = Matiere::where('code', 'MATH')->value('id');
        $francais = Matiere::where('code', 'FR')->value('id');

        $this->assertEqualsWithDelta(14.0, $e('Ama')['matieres'][$maths]['moyenne'], 1e-9);
        $this->assertEqualsWithDelta(16.0, $e('Ama')['matieres'][$francais]['moyenne'], 1e-9);
        $this->assertEqualsWithDelta(88 / 6, $e('Ama')['moyenne'], 1e-9);
        $this->assertSame('Bien', $e('Ama')['mention']);

        $this->assertEqualsWithDelta(10.0, $e('Kofi')['matieres'][$maths]['moyenne'], 1e-9);
        $this->assertNull($e('Kofi')['matieres'][$francais]['moyenne']);
        $this->assertEqualsWithDelta(10.0, $e('Kofi')['moyenne'], 1e-9);
        $this->assertSame('Passable', $e('Kofi')['mention']);

        $this->assertNull($e('Afi')['moyenne']);

        $this->assertSame([1, 1, 3, null], [$e('Ama')['rang'], $e('Yao')['rang'], $e('Kofi')['rang'], $e('Afi')['rang']]);
        $this->assertSame(4, $r['effectif']);
        $this->assertSame(3, $r['classes']);
        $this->assertEqualsWithDelta((88 / 6 * 2 + 10) / 3, $r['moyenne'], 1e-9);
        $this->assertEqualsWithDelta(100.0, $r['taux_reussite'], 1e-9);
        // Moyenne de classe en maths : (14 + 10 + 14) / 3.
        $this->assertEqualsWithDelta(38 / 3, $r['matieres'][$maths]['moyenne'], 1e-9);
        $this->assertSame(2, $r['matieres'][$maths]['nb_evaluations']);
        // Rang dans la matière : Ama et Yao 1ers en maths, Kofi 3e.
        $this->assertSame(3, $e('Kofi')['matieres'][$maths]['rang']);
    }

    public function test_absence_comptee_zero_selon_le_parametre(): void
    {
        $etablissement = Etablissement::courant();
        $etablissement->update(['parametres' => array_merge($etablissement->parametres, ['absence_evaluation' => 'zero'])]);
        app()->forgetInstance('etablissement.courant');

        $r = $this->service->resultatsClasse($this->classe, $this->periode);
        $kofi = $r['eleves'][$this->eleves['Kofi']->id];
        $maths = Matiere::where('code', 'MATH')->value('id');
        $francais = Matiere::where('code', 'FR')->value('id');

        // Maths : (10×1 + 0×2) / 3 ; Français : 0.
        $this->assertEqualsWithDelta(10 / 3, $kofi['matieres'][$maths]['moyenne'], 1e-9);
        $this->assertEqualsWithDelta(0.0, $kofi['matieres'][$francais]['moyenne'], 1e-9);
        $this->assertSame('Insuffisant', $kofi['mention']);
    }

    public function test_rang_sur_la_moyenne_arrondie(): void
    {
        // 14,666 et 14,671 s'arrondissent à 14,67 : ex æquo à 2 décimales.
        $this->assertSame(['a' => 1, 'b' => 1, 'c' => 3, 'd' => null], $this->service->rangs(['a' => 14.666, 'b' => 14.671, 'c' => 9.5, 'd' => null]));
    }

    public function test_seules_les_evaluations_validees_si_demande(): void
    {
        $maths = Affectation::where('coefficient', 4)->firstOrFail();
        $brouillon = Evaluation::factory()->create(['affectation_id' => $maths->id, 'periode_id' => $this->periode->id, 'bareme' => 20, 'poids' => 1]);
        $this->note($brouillon, 'Afi', 18);

        $this->assertNotNull($this->service->resultatsClasse($this->classe, $this->periode)['eleves'][$this->eleves['Afi']->id]['moyenne']);
        $validees = $this->service->resultatsClasse($this->classe, $this->periode, seulementValidees: true);
        $this->assertNull($validees['eleves'][$this->eleves['Afi']->id]['moyenne']);
        $this->assertSame(1, $validees['evaluations_non_validees']);
    }

    public function test_les_notes_d_une_ancienne_classe_sont_conservees(): void
    {
        // Ama change de classe : sa nouvelle classe a aussi des maths ; ses notes restent comptées (RG-ELV-04).
        $nouvelle = Classe::factory()->create(['annee_scolaire_id' => $this->classe->annee_scolaire_id]);
        Affectation::factory()->create(['classe_id' => $nouvelle->id, 'matiere_id' => Matiere::where('code', 'MATH')->value('id'), 'coefficient' => 3]);
        $this->eleves['Ama']->update(['classe_id' => $nouvelle->id]);

        $r = $this->service->resultatsClasse($nouvelle, $this->periode);
        $ama = $r['eleves'][$this->eleves['Ama']->id];
        $this->assertEqualsWithDelta(14.0, $ama['matieres'][Matiere::where('code', 'MATH')->value('id')]['moyenne'], 1e-9);
        // Le français, suivi seulement dans l'ancienne classe, garde son coefficient (2) : (14×3 + 16×2) / 5.
        $this->assertEqualsWithDelta(74 / 5, $ama['moyenne'], 1e-9);
    }
}
