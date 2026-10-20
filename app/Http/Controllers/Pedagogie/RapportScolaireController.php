<?php

namespace App\Http\Controllers\Pedagogie;

use App\Enums\Sexe;
use App\Enums\StatutInscription;
use App\Enums\StatutPresence;
use App\Http\Controllers\Controller;
use App\Models\AnneeScolaire;
use App\Models\Bulletin;
use App\Models\Classe;
use App\Models\Presence;
use App\Support\ExportCsv;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Statistiques scolaires : effectifs, résultats (bulletins publiés uniquement) et assiduité par classe.
 */
class RapportScolaireController extends Controller
{
    public function __invoke(Request $request): View|Response
    {
        $annees = AnneeScolaire::query()->orderByDesc('date_debut')->get();
        $annee = $annees->firstWhere('id', $request->integer('annee')) ?? $annees->firstWhere('est_active', true) ?? $annees->first();
        $periodes = $annee?->periodes ?? collect();

        $classes = $annee ? Classe::query()->where('annee_scolaire_id', $annee->id)->with(['niveau', 'inscriptions.eleve'])
            ->join('niveaux', 'niveaux.id', '=', 'classes.niveau_id')->orderBy('niveaux.ordre')->orderBy('classes.nom')->select('classes.*')->get() : collect();

        $effectifs = $classes->map(function (Classe $c) {
            $actifs = $c->inscriptions->where('statut', StatutInscription::Inscrit);

            return [
                'classe' => $c->nom, 'niveau' => $c->niveau->libelle, 'capacite' => $c->capacite,
                'effectif' => $actifs->count(),
                'filles' => $actifs->filter(fn ($i) => $i->eleve->sexe === Sexe::Feminin)->count(),
                'redoublants' => $actifs->where('redoublant', true)->count(),
                'partis' => $c->inscriptions->where('statut', StatutInscription::Parti)->count(),
            ];
        });

        $resultats = $this->resultats($classes, $periodes);
        $assiduite = $this->assiduite($classes);

        if ($export = $request->query('export')) {
            return match ($export) {
                'effectifs' => ExportCsv::telecharger("effectifs-{$annee?->libelle}.csv", ['Classe', 'Niveau', 'Effectif', 'Filles', 'Garçons', 'Redoublants', 'Départs', 'Capacité'],
                    $effectifs->map(fn ($e) => [$e['classe'], $e['niveau'], $e['effectif'], $e['filles'], $e['effectif'] - $e['filles'], $e['redoublants'], $e['partis'], $e['capacite']])),
                'resultats' => ExportCsv::telecharger("resultats-{$annee?->libelle}.csv", ['Classe', 'Période', 'Élèves classés', 'Moyenne de classe', 'Plus forte', 'Plus faible', 'Taux de réussite (%)'],
                    $resultats->flatMap(fn ($r) => collect($r['periodes'])->filter()->map(fn ($p, $libelle) => [$r['classe'], $libelle, $p['classes'],
                        number_format($p['moyenne'], 2, ',', ''), number_format($p['max'], 2, ',', ''), number_format($p['min'], 2, ',', ''), number_format($p['reussite'], 0, ',', '')]))->values()),
                'assiduite' => ExportCsv::telecharger("assiduite-{$annee?->libelle}.csv", ['Classe', 'Demi-journées relevées', 'Absences', 'Non justifiées', 'Retards', 'Taux d\'absence (%)'],
                    $assiduite->map(fn ($a) => [$a['classe'], $a['releves'], $a['absences'], $a['non_justifiees'], $a['retards'], $a['taux'] === null ? '' : number_format($a['taux'], 1, ',', '')])),
                default => abort(404),
            };
        }

        return view('pedagogie.rapports.scolaires', compact('annees', 'annee', 'periodes', 'effectifs', 'resultats', 'assiduite'));
    }

    /**
     * Résultats tirés des bulletins publiés (les chiffres officiels, figés).
     *
     * @return Collection<int, array{classe: string, periodes: array<string, ?array<string, float|int>>}>
     */
    private function resultats(Collection $classes, Collection $periodes): Collection
    {
        $bulletins = Bulletin::query()->whereNotNull('moyenne_generale')
            ->whereHas('publication', fn ($q) => $q->whereNull('depubliee_at')->whereIn('classe_id', $classes->pluck('id')))
            ->with('publication')->get()->groupBy(fn ($b) => $b->publication->classe_id.'-'.$b->periode_id);

        return $classes->map(fn (Classe $c) => [
            'classe' => $c->nom,
            'periodes' => $periodes->mapWithKeys(function ($p) use ($bulletins, $c) {
                $moyennes = collect($bulletins->get($c->id.'-'.$p->id, []))->pluck('moyenne_generale');

                return [$p->libelle => $moyennes->isEmpty() ? null : [
                    'classes' => $moyennes->count(),
                    'moyenne' => $moyennes->avg(),
                    'max' => $moyennes->max(),
                    'min' => $moyennes->min(),
                    'reussite' => $moyennes->filter(fn ($m) => $m >= 10)->count() / $moyennes->count() * 100,
                ]];
            })->all(),
        ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function assiduite(Collection $classes): Collection
    {
        $stats = Presence::query()->join('appels', 'appels.id', '=', 'presences.appel_id')
            ->whereIn('appels.classe_id', $classes->pluck('id'))
            ->selectRaw('appels.classe_id, presences.statut, COUNT(*) as total')
            ->groupBy('appels.classe_id', 'presences.statut')->get()->groupBy('classe_id');

        return $classes->map(function (Classe $c) use ($stats) {
            $s = collect($stats->get($c->id, []))->mapWithKeys(fn ($l) => [$l->statut instanceof \BackedEnum ? $l->statut->value : $l->statut => (int) $l->total]);
            $releves = $s->sum();
            $absences = ($s[StatutPresence::Absent->value] ?? 0) + ($s[StatutPresence::AbsentExcuse->value] ?? 0);

            return [
                'classe' => $c->nom,
                'releves' => $releves,
                'absences' => $absences,
                'non_justifiees' => $s[StatutPresence::Absent->value] ?? 0,
                'retards' => $s[StatutPresence::Retard->value] ?? 0,
                'taux' => $releves ? $absences / $releves * 100 : null,
            ];
        });
    }
}
