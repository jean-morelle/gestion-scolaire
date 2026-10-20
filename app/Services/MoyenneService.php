<?php

namespace App\Services;

use App\Enums\StatutEvaluation;
use App\Enums\StatutInscription;
use App\Models\Classe;
use App\Models\Evaluation;
use App\Models\Inscription;
use App\Models\Note;
use App\Models\Periode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Calcul des moyennes, rangs et mentions. Les règles sont celles de docs/regles-metier.md (RG-NOT) ;
 * les choix propres à l'établissement (absence à une évaluation, décimales, mentions) viennent de ses paramètres.
 * Aucun arrondi intermédiaire : seul l'affichage et le classement arrondissent (RG-NOT-07).
 */
class MoyenneService
{
    /**
     * RG-NOT-02 : note ramenée sur 20 (null si absent ou non noté).
     */
    public function surVingt(Note $note, Evaluation $evaluation): ?float
    {
        if ($note->est_absent || $note->valeur === null) {
            return $note->est_absent && $this->absenceCompteZero() ? 0.0 : null;
        }

        return (float) $note->valeur / (float) $evaluation->bareme * 20;
    }

    /**
     * RG-NOT-04 : Σ(note sur 20 × poids) / Σ(poids) sur les évaluations où l'élève a une note.
     *
     * @param  iterable<array{0: Note, 1: Evaluation}>  $notes
     */
    public function moyenneMatiere(iterable $notes): ?float
    {
        $points = 0.0;
        $poids = 0.0;
        foreach ($notes as [$note, $evaluation]) {
            $valeur = $this->surVingt($note, $evaluation);
            if ($valeur === null) {
                continue;
            }
            $points += $valeur * (float) $evaluation->poids;
            $poids += (float) $evaluation->poids;
        }

        return $poids > 0 ? $points / $poids : null;
    }

    /**
     * RG-NOT-05 : Σ(moyenne × coefficient) / Σ(coefficients des matières où l'élève a une moyenne).
     *
     * @param  iterable<array{moyenne: ?float, coefficient: float}>  $matieres
     */
    public function moyenneGenerale(iterable $matieres): ?float
    {
        $points = 0.0;
        $coefficients = 0.0;
        foreach ($matieres as $ligne) {
            if ($ligne['moyenne'] === null) {
                continue;
            }
            $points += $ligne['moyenne'] * $ligne['coefficient'];
            $coefficients += $ligne['coefficient'];
        }

        return $coefficients > 0 ? $points / $coefficients : null;
    }

    /**
     * RG-NOT-06 : classement décroissant sur la moyenne arrondie ; ex æquo → même rang, rang suivant sauté.
     *
     * @param  array<int|string, ?float>  $moyennes  clé => moyenne (null = non classé)
     * @return array<int|string, ?int>
     */
    public function rangs(array $moyennes): array
    {
        $decimales = $this->decimales();
        $classes = array_filter($moyennes, fn ($m) => $m !== null);
        $arrondies = array_map(fn ($m) => round($m, $decimales), $classes);
        arsort($arrondies);

        $rangs = array_fill_keys(array_keys($moyennes), null);
        $position = 0;
        $precedente = null;
        $rang = 0;
        foreach ($arrondies as $cle => $valeur) {
            $position++;
            if ($valeur !== $precedente) {
                $rang = $position;
                $precedente = $valeur;
            }
            $rangs[$cle] = $rang;
        }

        return $rangs;
    }

    /**
     * RG-NOT-12 : mention selon les seuils paramétrés.
     */
    public function mention(?float $moyenne): ?string
    {
        if ($moyenne === null) {
            return null;
        }
        $mentions = collect(etablissement()->parametre('mentions', []))->sortByDesc('min');
        $arrondie = round($moyenne, $this->decimales());

        return $mentions->first(fn ($m) => $arrondie >= (float) $m['min'])['libelle'] ?? null;
    }

    /**
     * Résultats complets d'une classe pour une période : moyennes par matière, moyenne générale, rangs, mentions,
     * statistiques de classe. Les notes obtenues dans une autre classe de la même période (changement de classe,
     * RG-ELV-04) sont prises en compte dans la matière correspondante.
     *
     * @return array{
     *     matieres: Collection<int, array{affectation: \App\Models\Affectation, moyenne: ?float, min: ?float, max: ?float, nb_evaluations: int}>,
     *     eleves: Collection<int, array{inscription: Inscription, matieres: array<int, array{moyenne: ?float, coefficient: float, rang: ?int}>, moyenne: ?float, rang: ?int, mention: ?string}>,
     *     effectif: int, classes: int, moyenne: ?float, min: ?float, max: ?float, taux_reussite: ?float, evaluations_non_validees: int
     * }
     */
    public function resultatsClasse(Classe $classe, Periode $periode, bool $seulementValidees = false): array
    {
        $affectations = $classe->affectations()->with('matiere')->get()->keyBy('matiere_id');
        $inscriptions = $this->inscriptionsDeLaPeriode($classe, $periode);

        $evaluations = Evaluation::query()
            ->with('affectation')
            ->where('periode_id', $periode->id)
            ->where(fn (Builder $q) => $q->whereIn('affectation_id', $affectations->pluck('id'))
                ->orWhereHas('notes', fn (Builder $n) => $n->whereIn('inscription_id', $inscriptions->pluck('id'))))
            ->when($seulementValidees, fn (Builder $q) => $q->where('statut', StatutEvaluation::Validee))
            ->get()
            ->keyBy('id');
        $notes = Note::query()->whereIn('evaluation_id', $evaluations->keys())->whereIn('inscription_id', $inscriptions->pluck('id'))
            ->get()->groupBy('inscription_id');

        $eleves = collect();
        foreach ($inscriptions as $inscription) {
            $parMatiere = collect($notes->get($inscription->id, []))
                ->map(fn (Note $n) => [$n, $evaluations[$n->evaluation_id]])
                ->groupBy(fn ($paire) => $paire[1]->affectation->matiere_id);

            $lignes = [];
            foreach ($affectations as $matiereId => $affectation) {
                $lignes[$matiereId] = ['moyenne' => $this->moyenneMatiere($parMatiere->get($matiereId, [])), 'coefficient' => (float) $affectation->coefficient, 'rang' => null];
            }
            // Matière suivie seulement dans l'ancienne classe : coefficient de cette classe-là.
            foreach ($parMatiere as $matiereId => $paires) {
                if (! isset($lignes[$matiereId])) {
                    $lignes[$matiereId] = ['moyenne' => $this->moyenneMatiere($paires), 'coefficient' => (float) $paires[0][1]->affectation->coefficient, 'rang' => null];
                }
            }

            $moyenne = $this->moyenneGenerale($lignes);
            $eleves[$inscription->id] = ['inscription' => $inscription, 'matieres' => $lignes, 'moyenne' => $moyenne, 'rang' => null, 'mention' => $this->mention($moyenne)];
        }

        foreach ($this->rangs($eleves->map(fn ($e) => $e['moyenne'])->all()) as $id => $rang) {
            $eleves[$id] = array_merge($eleves[$id], ['rang' => $rang]);
        }

        $matieres = collect();
        foreach ($affectations as $matiereId => $affectation) {
            $moyennes = $eleves->map(fn ($e) => $e['matieres'][$matiereId]['moyenne'] ?? null)->all();
            foreach ($this->rangs($moyennes) as $id => $rang) {
                $ligne = $eleves[$id];
                $ligne['matieres'][$matiereId]['rang'] = $rang;
                $eleves[$id] = $ligne;
            }
            $valeurs = collect($moyennes)->filter(fn ($m) => $m !== null);
            $matieres[$matiereId] = [
                'affectation' => $affectation,
                'moyenne' => $valeurs->isEmpty() ? null : $valeurs->avg(),
                'min' => $valeurs->min(),
                'max' => $valeurs->max(),
                'nb_evaluations' => $evaluations->where('affectation_id', $affectation->id)->count(),
            ];
        }

        $generales = $eleves->pluck('moyenne')->filter(fn ($m) => $m !== null);

        return [
            'matieres' => $matieres,
            'eleves' => $eleves->sortBy(fn ($e) => $e['rang'] ?? PHP_INT_MAX),
            'effectif' => $eleves->count(),
            'classes' => $generales->count(),
            'moyenne' => $generales->isEmpty() ? null : $generales->avg(),
            'min' => $generales->min(),
            'max' => $generales->max(),
            'taux_reussite' => $generales->isEmpty() ? null : $generales->filter(fn ($m) => round($m, $this->decimales()) >= 10)->count() / $generales->count() * 100,
            'evaluations_non_validees' => Evaluation::query()->where('periode_id', $periode->id)
                ->whereIn('affectation_id', $affectations->pluck('id'))->where('statut', StatutEvaluation::Brouillon)->count(),
        ];
    }

    /**
     * Élèves de la classe pendant la période : inscrits, ou partis après le début de la période (RG-ELV-03).
     *
     * @return Collection<int, Inscription>
     */
    public function inscriptionsDeLaPeriode(Classe $classe, Periode $periode): Collection
    {
        return $classe->inscriptions()
            ->with('eleve')
            ->whereDate('inscriptions.date_inscription', '<=', $periode->date_fin)
            ->where(fn (Builder $q) => $q->where('inscriptions.statut', StatutInscription::Inscrit)->orWhereDate('inscriptions.date_sortie', '>=', $periode->date_debut))
            ->join('eleves', 'eleves.id', '=', 'inscriptions.eleve_id')
            ->orderBy('eleves.nom')->orderBy('eleves.prenoms')
            ->select('inscriptions.*')
            ->get();
    }

    public function decimales(): int
    {
        return (int) etablissement()->parametre('decimales', 2);
    }

    private function absenceCompteZero(): bool
    {
        return etablissement()->parametre('absence_evaluation', 'ignoree') === 'zero';
    }
}
