<?php

namespace App\Services;

use App\Enums\StatutEvaluation;
use App\Enums\StatutPresence;
use App\Models\Bulletin;
use App\Models\Eleve;
use App\Models\EmploiDuTemps;
use App\Models\Inscription;
use App\Models\Note;
use App\Models\Presence;
use App\Models\Publication;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Données présentées aux familles et aux élèves. Règle : uniquement ce qui est publié (bulletins en vigueur,
 * notes des évaluations validées dont la classe est publiée pour la période, RG-NOT-10).
 */
class SuiviEleveService
{
    public function __construct(private AppelService $appels, private SoldeService $soldes) {}

    /**
     * Inscription de l'année active, ou à défaut la plus récente.
     */
    public function inscriptionCourante(Eleve $eleve): ?Inscription
    {
        return $eleve->inscriptions()->with(['classe.niveau', 'classe.professeurPrincipal.user', 'anneeScolaire.periodes'])
            ->join('annees_scolaires', 'annees_scolaires.id', '=', 'inscriptions.annee_scolaire_id')
            ->orderByDesc('annees_scolaires.est_active')->orderByDesc('annees_scolaires.date_debut')
            ->select('inscriptions.*')->first();
    }

    /**
     * @return Collection<int, Bulletin> bulletins visibles, du plus récent au plus ancien
     */
    public function bulletinsPublies(Eleve $eleve): Collection
    {
        return Bulletin::query()
            ->with(['periode.anneeScolaire', 'publication', 'inscription.classe'])
            ->whereIn('inscription_id', $eleve->inscriptions()->select('id'))
            ->whereHas('publication', fn (Builder $q) => $q->whereNull('depubliee_at'))
            ->get()
            ->sortByDesc(fn (Bulletin $b) => [$b->periode->anneeScolaire->date_debut->timestamp, $b->periode->ordre])
            ->values();
    }

    /**
     * Notes détaillées des périodes publiées : période → matière → notes.
     *
     * @return Collection<int, array{periode: \App\Models\Periode, matieres: Collection<string, Collection<int, Note>>}>
     */
    public function notesPubliees(Inscription $inscription): Collection
    {
        $notes = Note::query()
            ->with(['evaluation.affectation.matiere', 'evaluation.periode'])
            ->where('inscription_id', $inscription->id)
            ->whereHas('evaluation', fn (Builder $q) => $q->where('statut', StatutEvaluation::Validee))
            ->get();

        $publiees = Publication::query()->whereNull('depubliee_at')
            ->whereIn('classe_id', $notes->pluck('evaluation.affectation.classe_id')->unique())
            ->get()->map(fn ($p) => $p->classe_id.'-'.$p->periode_id)->all();

        return $notes
            ->filter(fn (Note $n) => in_array($n->evaluation->affectation->classe_id.'-'.$n->evaluation->periode_id, $publiees, true))
            ->sortBy(fn (Note $n) => $n->evaluation->date_evaluation->timestamp)
            ->groupBy('evaluation.periode_id')
            ->map(fn ($groupe) => [
                'periode' => $groupe->first()->evaluation->periode,
                'matieres' => $groupe->groupBy(fn (Note $n) => $n->evaluation->affectation->matiere->libelle)->sortKeys(),
            ])
            ->sortByDesc(fn ($p) => $p['periode']->ordre)
            ->values();
    }

    /**
     * @return array{evenements: Collection<int, Presence>, periodes: Collection<int, array<string, mixed>>}
     */
    public function assiduite(Inscription $inscription): array
    {
        return [
            'evenements' => Presence::query()
                ->with(['appel', 'justificatifs'])
                ->where('inscription_id', $inscription->id)
                ->where('statut', '!=', StatutPresence::Present)
                ->join('appels', 'appels.id', '=', 'presences.appel_id')
                ->orderByDesc('appels.date_appel')->orderByDesc('appels.creneau')
                ->select('presences.*')
                ->get(),
            'periodes' => $inscription->anneeScolaire->periodes->map(fn ($p) => ['periode' => $p] + $this->appels->totaux($inscription, $p)),
        ];
    }

    /**
     * @return array<int, Collection<int, EmploiDuTemps>>
     */
    public function emploiDuTemps(Inscription $inscription): array
    {
        $affectations = $inscription->classe->affectations()->with(['matiere', 'enseignant.user', 'creneaux'])->get();

        return EmploiDuTempsService::parJour($affectations->flatMap(fn ($a) => $a->creneaux->each->setRelation('affectation', $a)));
    }

    /**
     * @return array<string, mixed>
     */
    public function finances(Inscription $inscription): array
    {
        return [
            'situation' => $this->soldes->situation($inscription),
            'paiements' => $inscription->paiements()->latest('date_paiement')->latest('id')->get(),
        ];
    }

    /**
     * Synthèse pour les cartes « mes enfants » et l'accueil de l'élève.
     *
     * @return array<string, mixed>
     */
    public function resume(Eleve $eleve, bool $avecFinances): array
    {
        $inscription = $this->inscriptionCourante($eleve);
        $periode = $inscription?->anneeScolaire->periodePour(now()) ?? $inscription?->anneeScolaire->periodes->last();

        return [
            'eleve' => $eleve,
            'inscription' => $inscription,
            'dernierBulletin' => $this->bulletinsPublies($eleve)->first(),
            'periode' => $periode,
            'assiduite' => $inscription && $periode ? $this->appels->totaux($inscription, $periode) : null,
            'finances' => $avecFinances && $inscription ? $this->soldes->soldes([$inscription->id])[$inscription->id] : null,
            'coursDuJour' => $inscription && now()->dayOfWeekIso <= 6 ? ($this->emploiDuTemps($inscription)[now()->dayOfWeekIso] ?? collect()) : collect(),
        ];
    }
}
