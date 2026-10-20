<?php

namespace App\Services;

use App\Models\Affectation;
use App\Models\Classe;
use App\Models\Enseignant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Matières d'une classe : enseignant titulaire, coefficient et ordre sur le bulletin (RG-ENS-01, RG-ENS-03).
 */
class AffectationService
{
    /** @var list<string> avertissements non bloquants du dernier appel */
    public array $avertissements = [];

    public function __construct(private JournalService $journal, private EmploiDuTempsService $emplois) {}

    public function ajouter(Classe $classe, int $matiereId, ?int $enseignantId, float $coefficient): Affectation
    {
        $this->avertissements = [];
        $this->verifierModifiable($classe);

        if ($classe->affectations()->where('matiere_id', $matiereId)->exists()) {
            throw ValidationException::withMessages(['matiere_id' => 'Cette matière est déjà enseignée dans la classe : modifiez la ligne existante.']);
        }

        $affectation = Affectation::create([
            'classe_id' => $classe->id,
            'matiere_id' => $matiereId,
            'enseignant_id' => $enseignantId,
            'coefficient' => $coefficient,
            'ordre_bulletin' => (int) $classe->affectations()->max('ordre_bulletin') + 1,
        ]);
        $affectation->load(['matiere', 'enseignant.user', 'classe']);
        $this->verifierHabilitation($affectation);

        $this->journal->enregistrer('affectation.creee', $affectation, "{$affectation->libelle()} ajoutée", null, $this->etat($affectation));

        return $affectation;
    }

    /**
     * Mise à jour groupée des lignes d'une classe, chaque changement est journalisé
     * (le coefficient influe sur les moyennes, RG-NOT-05).
     *
     * @param  array<int|string, array{enseignant_id: ?int, coefficient: float|string, ordre_bulletin: int|string}>  $lignes  id => valeurs
     * @return int nombre de lignes modifiées
     */
    public function mettreAJour(Classe $classe, array $lignes): int
    {
        $this->avertissements = [];
        $this->verifierModifiable($classe);

        return DB::transaction(function () use ($classe, $lignes) {
            $modifiees = 0;
            foreach ($classe->affectations()->with(['matiere', 'enseignant.user', 'classe'])->get() as $affectation) {
                if (! isset($lignes[$affectation->id])) {
                    continue;
                }
                $avant = $this->etat($affectation);
                $affectation->fill([
                    'enseignant_id' => $lignes[$affectation->id]['enseignant_id'] ?: null,
                    'coefficient' => $lignes[$affectation->id]['coefficient'],
                    'ordre_bulletin' => (int) $lignes[$affectation->id]['ordre_bulletin'],
                ]);
                if (! $affectation->isDirty()) {
                    continue;
                }

                $changementEnseignant = $affectation->isDirty('enseignant_id');
                $affectation->save();
                $affectation->load('enseignant.user');
                $modifiees++;

                if ($changementEnseignant) {
                    $this->verifierHabilitation($affectation);
                    $this->verifierHoraires($affectation);
                }
                $this->journal->enregistrer('affectation.modifiee', $affectation, "{$affectation->libelle()} modifiée", $avant, $this->etat($affectation));
            }

            return $modifiees;
        });
    }

    public function retirer(Affectation $affectation): void
    {
        $this->verifierModifiable($affectation->classe);

        if ($affectation->evaluations()->exists() || $affectation->cours()->exists() || $affectation->devoirs()->exists()) {
            throw ValidationException::withMessages(['affectation' => 'Des évaluations, cours ou devoirs existent pour cette matière : elle ne peut pas être retirée de la classe.']);
        }

        DB::transaction(function () use ($affectation) {
            $libelle = $affectation->libelle();
            $avant = $this->etat($affectation);
            $affectation->creneaux()->delete();
            $affectation->delete();
            $this->journal->enregistrer('affectation.supprimee', $affectation->classe, "{$libelle} retirée", $avant);
        });
    }

    /**
     * Pré-remplit une classe à partir d'une autre (même niveau, autre année…) : matières, coefficients,
     * ordre et, si demandé, enseignants. Les matières déjà présentes sont conservées telles quelles.
     *
     * @return int nombre de matières ajoutées
     */
    public function copier(Classe $source, Classe $cible, bool $avecEnseignants): int
    {
        $this->avertissements = [];
        $this->verifierModifiable($cible);
        if ($source->is($cible)) {
            throw ValidationException::withMessages(['source_id' => 'Choisissez une autre classe que celle-ci.']);
        }

        return DB::transaction(function () use ($source, $cible, $avecEnseignants) {
            $presentes = $cible->affectations()->pluck('matiere_id')->all();
            $ajoutees = 0;
            foreach ($source->affectations()->with('enseignant')->get() as $modele) {
                if (in_array($modele->matiere_id, $presentes, true)) {
                    continue;
                }
                Affectation::create([
                    'classe_id' => $cible->id,
                    'matiere_id' => $modele->matiere_id,
                    'enseignant_id' => $avecEnseignants && $modele->enseignant?->est_actif ? $modele->enseignant_id : null,
                    'coefficient' => $modele->coefficient,
                    'ordre_bulletin' => $modele->ordre_bulletin,
                ]);
                $ajoutees++;
            }
            $this->journal->enregistrer('affectation.copiees', $cible, "{$ajoutees} matière(s) recopiée(s) de {$source->nom} vers {$cible->nom}");

            return $ajoutees;
        });
    }

    private function verifierModifiable(Classe $classe): void
    {
        if ($classe->anneeScolaire->estCloturee()) {
            throw ValidationException::withMessages(['classe' => "L'année {$classe->anneeScolaire->libelle} est clôturée : les affectations sont en lecture seule."]);
        }
    }

    private function verifierHabilitation(Affectation $affectation): void
    {
        $enseignant = $affectation->enseignant;
        if ($enseignant && $enseignant->matieres()->exists() && ! $enseignant->matieres()->whereKey($affectation->matiere_id)->exists()) {
            $this->avertissements[] = "{$enseignant->user->name} n'a pas « {$affectation->matiere->libelle} » parmi ses matières enseignées.";
        }
    }

    /**
     * Après un changement d'enseignant, les créneaux existants peuvent chevaucher son emploi du temps.
     */
    private function verifierHoraires(Affectation $affectation): void
    {
        if (! $affectation->enseignant_id) {
            return;
        }
        foreach ($affectation->creneaux as $creneau) {
            $conflits = $this->emplois->conflits($affectation, $creneau->jour->value, $creneau->heure_debut, $creneau->heure_fin, null, $creneau->id);
            if ($conflits['enseignant'] ?? null) {
                $this->avertissements[] = "Conflit d'horaire pour {$affectation->enseignant->user->name} le {$creneau->jour->libelle()} ({$creneau->horaire()}) : {$conflits['enseignant']}.";
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function etat(Affectation $affectation): array
    {
        return [
            'enseignant' => $affectation->enseignant_id ? Enseignant::with('user')->find($affectation->enseignant_id)?->user->name : null,
            'coefficient' => (float) $affectation->coefficient,
            'ordre_bulletin' => (int) $affectation->ordre_bulletin,
        ];
    }
}
