<?php

namespace App\Http\Controllers\Pedagogie;

use App\Http\Controllers\Controller;
use App\Models\Affectation;
use App\Models\Classe;
use App\Models\Periode;
use App\Services\MoyenneService;
use App\Support\ExportCsv;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Relevés calculés en direct par MoyenneService (avant publication des bulletins).
 */
class ResultatController extends Controller
{
    public function __construct(private MoyenneService $moyennes) {}

    /**
     * Résultats d'une classe : direction et vie scolaire, ou professeur principal de la classe.
     */
    public function classe(Request $request): View|Response
    {
        $user = $request->user();
        $classes = Classe::query()->deLAnneeActive()
            ->when(! $user->can('notes.consulter'), fn ($q) => $q->where('classes.professeur_principal_id', $user->enseignant?->id ?? 0))
            ->get();
        abort_if($classes->isEmpty() && ! $user->can('notes.consulter'), 403);

        $classe = $classes->firstWhere('id', $request->integer('classe')) ?? $classes->first();
        $periodes = annee_active()?->periodes ?? collect();
        $periode = $periodes->firstWhere('id', $request->integer('periode'))
            ?? annee_active()?->periodePour(now()) ?? $periodes->first();

        $resultats = $classe && $periode ? $this->moyennes->resultatsClasse($classe, $periode) : null;

        if ($resultats && $request->query('format') === 'csv') {
            $matieres = $resultats['matieres'];

            return ExportCsv::telecharger("resultats-{$classe->nom}-{$periode->libelle}.csv",
                array_merge(['Rang', 'Matricule', 'Nom et prénoms'], $matieres->map(fn ($m) => $m['affectation']->matiere->libelle)->values()->all(), ['Moyenne', 'Mention']),
                $resultats['eleves']->map(fn ($e) => array_merge(
                    [$e['rang'], $e['inscription']->eleve->matricule, $e['inscription']->eleve->nom_complet],
                    $matieres->keys()->map(fn ($id) => $this->format($e['matieres'][$id]['moyenne'] ?? null))->all(),
                    [$this->format($e['moyenne']), $e['mention']],
                ))->values());
        }

        return view('pedagogie.resultats.classe', compact('classes', 'classe', 'periodes', 'periode', 'resultats'));
    }

    /**
     * Relevé d'une matière : notes de chaque évaluation, moyenne et rang dans la matière.
     */
    public function affectation(Request $request, Affectation $affectation): View
    {
        $user = $request->user();
        abort_unless($user->can('notes.consulter') || ($user->enseignant && $user->enseignant->id === $affectation->enseignant_id), 403);

        $affectation->load(['classe.anneeScolaire', 'matiere', 'enseignant.user']);
        $periodes = $affectation->classe->anneeScolaire->periodes;
        $periode = $periodes->firstWhere('id', $request->integer('periode'))
            ?? $affectation->classe->anneeScolaire->periodePour(now()) ?? $periodes->first();
        abort_unless($periode, 404, 'Aucune période définie pour cette année.');

        $evaluations = $affectation->evaluations()->where('periode_id', $periode->id)->with('notes')->orderBy('date_evaluation')->get();
        $inscriptions = $this->moyennes->inscriptionsDeLaPeriode($affectation->classe, $periode);
        $lignes = $inscriptions->mapWithKeys(function ($inscription) use ($evaluations) {
            $paires = $evaluations->map(fn ($ev) => [$ev->notes->firstWhere('inscription_id', $inscription->id), $ev])->filter(fn ($p) => $p[0] !== null);

            return [$inscription->id => ['inscription' => $inscription, 'notes' => $paires->mapWithKeys(fn ($p) => [$p[1]->id => $p[0]]), 'moyenne' => $this->moyennes->moyenneMatiere($paires)]];
        });
        $rangs = $this->moyennes->rangs($lignes->map(fn ($l) => $l['moyenne'])->all());

        return view('pedagogie.resultats.affectation', compact('affectation', 'periodes', 'periode', 'evaluations', 'lignes', 'rangs'));
    }

    private function format(?float $valeur): string
    {
        return $valeur === null ? '' : number_format($valeur, $this->moyennes->decimales(), ',', '');
    }
}
