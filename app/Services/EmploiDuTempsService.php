<?php

namespace App\Services;

use App\Enums\Jour;
use App\Models\Affectation;
use App\Models\EmploiDuTemps;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Créneaux hebdomadaires. Un créneau est refusé s'il chevauche, le même jour et dans la même année,
 * un autre créneau de la classe, de l'enseignant ou de la salle.
 */
class EmploiDuTempsService
{
    public function __construct(private JournalService $journal) {}

    public function ajouter(Affectation $affectation, int $jour, string $debut, string $fin, ?string $salle): EmploiDuTemps
    {
        $affectation->loadMissing(['classe.anneeScolaire', 'matiere', 'enseignant.user']);
        if ($affectation->classe->anneeScolaire->estCloturee()) {
            throw ValidationException::withMessages(['affectation_id' => 'Cette année scolaire est clôturée.']);
        }

        $debut = self::heure($debut);
        $fin = self::heure($fin);
        if ($fin <= $debut) {
            throw ValidationException::withMessages(['heure_fin' => 'L\'heure de fin doit être postérieure à l\'heure de début.']);
        }
        $salle = $salle !== null && trim($salle) !== '' ? trim($salle) : null;

        $conflits = $this->conflits($affectation, $jour, $debut, $fin, $salle);
        if ($conflits !== []) {
            throw ValidationException::withMessages(['heure_debut' => array_values(array_map(
                fn ($type, $detail) => ['classe' => 'La classe a déjà cours', 'enseignant' => 'L\'enseignant est déjà pris', 'salle' => 'La salle est occupée'][$type].' : '.$detail.'.',
                array_keys($conflits), $conflits,
            ))]);
        }

        $creneau = EmploiDuTemps::create([
            'affectation_id' => $affectation->id,
            'jour' => $jour,
            'heure_debut' => $debut,
            'heure_fin' => $fin,
            'salle' => $salle,
        ]);
        $this->journal->enregistrer('emploi_du_temps.creneau_ajoute', $affectation,
            "{$affectation->libelle()} : {$creneau->jour->libelle()} {$creneau->horaire()}");

        return $creneau;
    }

    public function supprimer(EmploiDuTemps $creneau): void
    {
        $affectation = $creneau->affectation;
        if ($affectation->classe->anneeScolaire->estCloturee()) {
            throw ValidationException::withMessages(['creneau' => 'Cette année scolaire est clôturée.']);
        }
        $creneau->delete();
        $this->journal->enregistrer('emploi_du_temps.creneau_supprime', $affectation,
            "{$affectation->libelle()} : {$creneau->jour->libelle()} {$creneau->horaire()} supprimé");
    }

    /**
     * @return array<string, string> type de conflit (classe, enseignant, salle) => description du créneau en conflit
     */
    public function conflits(Affectation $affectation, int $jour, string $debut, string $fin, ?string $salle = null, ?int $exclure = null): array
    {
        $debut = self::heure($debut);
        $fin = self::heure($fin);
        $affectation->loadMissing('classe');

        $chevauchants = EmploiDuTemps::query()
            ->with(['affectation.classe', 'affectation.matiere', 'affectation.enseignant.user'])
            ->where('jour', $jour)
            ->where('heure_debut', '<', $fin)
            ->where('heure_fin', '>', $debut)
            ->when($exclure, fn (Builder $q) => $q->whereKeyNot($exclure))
            ->whereHas('affectation.classe', fn (Builder $q) => $q->where('annee_scolaire_id', $affectation->classe->annee_scolaire_id))
            ->get();

        $decrire = fn (EmploiDuTemps $c) => "{$c->affectation->libelle()} de ".substr($c->heure_debut, 0, 5).' à '.substr($c->heure_fin, 0, 5);
        $conflits = [];

        if ($c = $chevauchants->first(fn ($c) => $c->affectation->classe_id === $affectation->classe_id)) {
            $conflits['classe'] = $decrire($c);
        }
        if ($affectation->enseignant_id && ($c = $chevauchants->first(fn ($c) => $c->affectation->enseignant_id === $affectation->enseignant_id))) {
            $conflits['enseignant'] = $decrire($c);
        }
        if ($salle && ($c = $chevauchants->first(fn ($c) => $c->salle && mb_strtolower($c->salle) === mb_strtolower($salle)))) {
            $conflits['salle'] = $decrire($c);
        }

        return $conflits;
    }

    /**
     * Créneaux regroupés par jour (1 = lundi … 6 = samedi), triés par heure.
     *
     * @param  Collection<int, EmploiDuTemps>  $creneaux
     * @return array<int, Collection<int, EmploiDuTemps>>
     */
    public static function parJour(Collection $creneaux): array
    {
        $jours = [];
        foreach (Jour::cases() as $jour) {
            $jours[$jour->value] = $creneaux->filter(fn ($c) => $c->jour === $jour)->sortBy('heure_debut')->values();
        }

        return $jours;
    }

    /**
     * Total hebdomadaire en heures (décimal).
     *
     * @param  Collection<int, EmploiDuTemps>  $creneaux
     */
    public static function volumeHoraire(Collection $creneaux): float
    {
        return round($creneaux->sum(fn ($c) => (strtotime($c->heure_fin) - strtotime($c->heure_debut)) / 3600), 2);
    }

    /**
     * Normalise « 8:00 », « 08:00 » ou « 08:00:00 » en « 08:00:00 » : les comparaisons de chaînes
     * restent justes en SQLite comme en MySQL.
     */
    public static function heure(string $heure): string
    {
        [$h, $m] = array_pad(explode(':', $heure), 2, '0');

        return sprintf('%02d:%02d:00', (int) $h, (int) $m);
    }
}
