<?php

namespace App\Services;

use App\Enums\Creneau;
use App\Enums\Sexe;
use App\Enums\StatutDepense;
use App\Enums\StatutEleve;
use App\Enums\StatutEvaluation;
use App\Enums\StatutInscription;
use App\Enums\StatutPaiement;
use App\Enums\StatutPresence;
use App\Models\Annonce;
use App\Models\Appel;
use App\Models\Classe;
use App\Models\Depense;
use App\Models\Devoir;
use App\Models\Eleve;
use App\Models\EmploiDuTemps;
use App\Models\Evaluation;
use App\Models\Inscription;
use App\Models\JournalActivite;
use App\Models\Paiement;
use App\Models\Presence;
use App\Models\Publication;
use App\Models\RemiseDevoir;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Blocs du tableau de bord, chacun conditionné par un droit : un utilisateur qui cumule plusieurs rôles voit
 * tous les blocs correspondants. Toutes les valeurs sont calculées à partir de la base (aucune donnée fictive).
 */
class TableauDeBordService
{
    public function __construct(private SoldeService $soldes) {}

    /**
     * @return array<string, array<string, mixed>>
     */
    public function pour(User $user): array
    {
        $annee = annee_active();
        $blocs = [];

        // Pilotage : direction et censeur (statistiques scolaires) ; la partie financière exige en plus un droit financier.
        if ($annee && $user->can('rapports.scolaires')) {
            $blocs['direction'] = $this->direction($user, $annee);
        }
        if ($user->can('presences.saisir') || $user->can('presences.justifier')) {
            $blocs['vie_scolaire'] = $this->vieScolaire($annee);
        }
        if ($user->can('eleves.gerer')) {
            $blocs['secretariat'] = $this->secretariat($annee);
        }
        if ($user->enseignant && $user->can('notes.saisir')) {
            $blocs['enseignant'] = $this->enseignant($user, $annee);
        }
        // Bloc caisse : pour qui encaisse ; la direction a déjà les indicateurs financiers dans sa vue d'ensemble.
        if ($user->can('paiements.enregistrer') || ($user->can('finances.consulter') && ! isset($blocs['direction']))) {
            $blocs['comptabilite'] = $this->comptabilite($annee);
        }
        if ($user->can('utilisateurs.gerer')) {
            $blocs['administration'] = $this->administration($user);
        }
        $blocs['annonces'] = ['liste' => Annonce::query()->enCours()->visiblesPar($user)->latest('publiee_at')->limit(3)->get()];

        return $blocs;
    }

    private function direction(User $user, $annee): array
    {
        $inscrits = Inscription::query()->where('inscriptions.annee_scolaire_id', $annee->id)->where('inscriptions.statut', StatutInscription::Inscrit);
        $effectif = (clone $inscrits)->count();
        $periode = $annee->periodePour(now());

        $bloc = [
            'effectif' => $effectif,
            'filles' => (clone $inscrits)->whereHas('eleve', fn (Builder $q) => $q->where('sexe', Sexe::Feminin))->count(),
            'classes' => Classe::query()->where('annee_scolaire_id', $annee->id)->count(),
            'absents_jour' => Presence::query()->whereIn('statut', [StatutPresence::Absent, StatutPresence::AbsentExcuse])
                ->whereHas('appel', fn (Builder $q) => $q->whereDate('date_appel', today()))->distinct('inscription_id')->count('inscription_id'),
            'periode' => $periode,
            'brouillons' => $periode ? Evaluation::query()->where('periode_id', $periode->id)->where('statut', StatutEvaluation::Brouillon)->count() : 0,
            'classes_publiees' => $periode ? Publication::query()->where('periode_id', $periode->id)->whereNull('depubliee_at')->count() : 0,
        ];

        $bloc['evolution'] = $this->evolutionEffectifs($annee);
        $bloc['par_niveau'] = (clone $inscrits)->join('classes', 'classes.id', '=', 'inscriptions.classe_id')
            ->join('niveaux', 'niveaux.id', '=', 'classes.niveau_id')
            ->selectRaw('niveaux.libelle, niveaux.cycle, niveaux.ordre, COUNT(*) as total')
            ->groupBy('niveaux.libelle', 'niveaux.cycle', 'niveaux.ordre')->orderBy('niveaux.ordre')->get();
        $bloc['dernieres_inscriptions'] = Inscription::query()->with(['eleve', 'classe'])->where('annee_scolaire_id', $annee->id)
            ->latest('date_inscription')->latest('id')->limit(4)->get();

        if ($user->can('rapports.financiers') || $user->can('finances.consulter')) {
            $bloc['paiements_recents'] = Paiement::query()->with('inscription.eleve')->where('statut', StatutPaiement::Valide)
                ->latest('date_paiement')->latest('id')->limit(4)->get();
            $soldes = $this->soldes->soldes((clone $inscrits)->pluck('id'));
            $bloc['finances'] = [
                'encaisse_mois' => (int) Paiement::query()->where('statut', StatutPaiement::Valide)->whereDate('date_paiement', '>=', now()->startOfMonth())->sum('montant'),
                'reste' => $soldes->sum('solde'),
                'retard' => $soldes->sum('retard'),
                'eleves_en_retard' => $soldes->where('retard', '>', 0)->count(),
                'taux' => ($net = $soldes->sum('du') - $soldes->sum('remises')) > 0 ? $soldes->sum('paye') / $net * 100 : null,
            ];
        }

        return $bloc;
    }

    /**
     * Effectif présent à la fin de chaque mois écoulé de l'année (inscrits à cette date, hors départs antérieurs),
     * avec les arrivées et départs du mois.
     *
     * @return list<array{mois: CarbonImmutable, effectif: int, arrivees: int, departs: int}>
     */
    private function evolutionEffectifs($annee): array
    {
        $inscriptions = Inscription::query()->where('annee_scolaire_id', $annee->id)->get(['date_inscription', 'statut', 'date_sortie']);
        $fin = CarbonImmutable::parse(min($annee->date_fin, now()))->endOfMonth();
        $mois = CarbonImmutable::parse($annee->date_debut)->startOfMonth();
        $serie = [];

        while ($mois->lte($fin) && count($serie) < 12) {
            $finMois = $mois->endOfMonth();
            $serie[] = [
                'mois' => $mois,
                // Les inscriptions anticipées (avant la rentrée) comptent dans le premier mois.
                'effectif' => $inscriptions->filter(fn ($i) => $i->date_inscription->lte($finMois)
                    && ($i->date_sortie === null || $i->date_sortie->gt($finMois)))->count(),
                'arrivees' => $inscriptions->filter(fn ($i) => $i->date_inscription->lte($finMois)
                    && (count($serie) === 0 || $i->date_inscription->gte($mois)))->count(),
                'departs' => $inscriptions->filter(fn ($i) => $i->date_sortie && $i->date_sortie->between($mois, $finMois))->count(),
            ];
            $mois = $mois->addMonth();
        }

        return $serie;
    }

    private function vieScolaire($annee): array
    {
        $creneau = now()->hour < 13 ? Creneau::Matin : Creneau::ApresMidi;
        $classes = $annee ? Classe::query()->where('annee_scolaire_id', $annee->id)->count() : 0;
        $faits = $annee ? Appel::query()->whereDate('date_appel', today())->where('creneau', $creneau)
            ->whereHas('classe', fn (Builder $q) => $q->where('annee_scolaire_id', $annee->id))->count() : 0;

        return [
            'creneau' => $creneau,
            'appels_faits' => $faits,
            'appels_attendus' => now()->isSunday() ? 0 : $classes,
            'non_justifiees' => $annee ? Presence::query()->where('statut', StatutPresence::Absent)
                ->whereHas('inscription', fn (Builder $q) => $q->where('annee_scolaire_id', $annee->id))->count() : 0,
            'retards_semaine' => Presence::query()->where('statut', StatutPresence::Retard)
                ->whereHas('appel', fn (Builder $q) => $q->whereDate('date_appel', '>=', now()->startOfWeek()))->count(),
        ];
    }

    private function secretariat($annee): array
    {
        $recentes = Inscription::query()->with(['eleve', 'classe'])->when($annee, fn ($q) => $q->where('annee_scolaire_id', $annee->id))
            ->latest('date_inscription')->latest('id')->limit(5)->get();
        // Dossiers incomplets parmi les élèves inscrits cette année.
        $incomplets = Eleve::query()->where('statut', StatutEleve::Actif)
            ->whereHas('inscriptions', fn ($q) => $q->where('annee_scolaire_id', $annee?->id)->where('statut', StatutInscription::Inscrit))
            ->with(['responsables', 'documents'])->get()
            ->map(fn (Eleve $e) => ['eleve' => $e, 'manquants' => $e->elementsManquants()])
            ->filter(fn ($l) => $l['manquants'] !== []);

        return [
            'recentes' => $recentes,
            'incomplets' => $incomplets->take(6),
            'nb_incomplets' => $incomplets->count(),
            'non_inscrits' => $annee ? Eleve::query()->where('statut', StatutEleve::Actif)
                ->whereDoesntHave('inscriptions', fn ($q) => $q->where('annee_scolaire_id', $annee->id))->count() : 0,
        ];
    }

    private function enseignant(User $user, $annee): array
    {
        $enseignant = $user->enseignant;
        $affectations = $enseignant->affectationsActives()->with(['classe', 'matiere'])->get();
        $ids = $affectations->pluck('id');
        $jour = CarbonImmutable::today();

        return [
            'classes' => $affectations->pluck('classe')->unique('id')->count(),
            'cours_du_jour' => $jour->dayOfWeekIso <= 6 ? EmploiDuTemps::query()->with(['affectation.classe', 'affectation.matiere'])
                ->whereIn('affectation_id', $ids)->where('jour', $jour->dayOfWeekIso)->orderBy('heure_debut')->get() : collect(),
            'evaluations_brouillon' => Evaluation::query()->with(['affectation.classe', 'affectation.matiere'])->whereIn('affectation_id', $ids)
                ->where('statut', StatutEvaluation::Brouillon)->orderBy('date_evaluation')->limit(5)->get(),
            'copies' => RemiseDevoir::query()->whereNull('corrige_at')->whereIn('devoir_id', Devoir::query()->whereIn('affectation_id', $ids)->select('id'))->count(),
            'devoirs_a_venir' => Devoir::query()->with(['affectation.classe'])->whereIn('affectation_id', $ids)->where('est_publie', true)
                ->where('date_limite', '>=', now())->orderBy('date_limite')->limit(4)->get(),
        ];
    }

    private function comptabilite($annee): array
    {
        $valides = Paiement::query()->where('statut', StatutPaiement::Valide);
        $inscrits = $annee ? Inscription::query()->with(['eleve', 'classe'])->where('annee_scolaire_id', $annee->id)->where('statut', StatutInscription::Inscrit)->get() : collect();
        $soldes = $this->soldes->soldes($inscrits->pluck('id'));

        return [
            'encaisse_jour' => (int) (clone $valides)->whereDate('date_paiement', today())->sum('montant'),
            'encaisse_mois' => (int) (clone $valides)->whereDate('date_paiement', '>=', now()->startOfMonth())->sum('montant'),
            'depenses_mois' => (int) Depense::query()->where('statut', StatutDepense::Valide)->whereDate('date_depense', '>=', now()->startOfMonth())->sum('montant'),
            'recus' => Paiement::query()->with(['inscription.eleve'])->latest('id')->limit(5)->get(),
            'retards' => $inscrits->map(fn ($i) => ['inscription' => $i, 'retard' => $soldes[$i->id]['retard'] ?? 0])
                ->where('retard', '>', 0)->sortByDesc('retard')->take(5)->values(),
            'total_retard' => $soldes->sum('retard'),
        ];
    }

    private function administration(User $user): array
    {
        return [
            'comptes_actifs' => User::query()->where('est_actif', true)->count(),
            'jamais_connectes' => User::query()->where('est_actif', true)->whereNull('derniere_connexion_at')->count(),
            'journal' => $user->can('journal.consulter') ? JournalActivite::query()->with('user')->latest('created_at')->limit(6)->get() : collect(),
        ];
    }
}
