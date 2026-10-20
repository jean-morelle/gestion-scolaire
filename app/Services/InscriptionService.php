<?php

namespace App\Services;

use App\Enums\StatutEleve;
use App\Enums\StatutInscription;
use App\Enums\TypeInscription;
use App\Models\ChangementClasse;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\Inscription;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Inscriptions, réinscriptions, changements de classe et départs (RG-ELV-02 à 04, RG-ELV-07, RG-FIN-03).
 */
class InscriptionService
{
    /** Avertissement non bloquant du dernier appel (capacité dépassée, RG-ELV-07). */
    public ?string $avertissement = null;

    public function __construct(private JournalService $journal, private FraisService $frais) {}

    /**
     * Inscrit l'élève dans la classe pour l'année de celle-ci. Le type (nouvelle inscription ou
     * réinscription) se déduit de l'historique de l'élève.
     *
     * @param  array{date_inscription?: string, categorie_frais?: ?string, redoublant?: bool}  $options
     */
    public function inscrire(Eleve $eleve, Classe $classe, array $options = []): Inscription
    {
        $this->avertissement = null;
        $annee = $classe->anneeScolaire;

        if ($annee->estCloturee()) {
            throw ValidationException::withMessages(['classe_id' => "L'année {$annee->libelle} est clôturée : aucune inscription n'est possible."]);
        }
        if ($eleve->statut === StatutEleve::Archive) {
            throw ValidationException::withMessages(['eleve' => 'Ce dossier est archivé : réactivez-le avant de l\'inscrire.']);
        }

        return DB::transaction(function () use ($eleve, $classe, $annee, $options) {
            // RG-ELV-02 : vérification applicative en plus de l'index unique (message lisible).
            $existante = Inscription::query()->where('eleve_id', $eleve->id)->where('annee_scolaire_id', $annee->id)->lockForUpdate()->first();
            if ($existante) {
                throw ValidationException::withMessages(['classe_id' => "{$eleve->nom_complet} est déjà inscrit(e) pour {$annee->libelle} ({$existante->classe->nom})."]);
            }

            $inscription = Inscription::create([
                'eleve_id' => $eleve->id,
                'annee_scolaire_id' => $annee->id,
                'classe_id' => $classe->id,
                'date_inscription' => $options['date_inscription'] ?? now()->toDateString(),
                'type' => $eleve->inscriptions()->exists() ? TypeInscription::Reinscription : TypeInscription::Nouvelle,
                'statut' => StatutInscription::Inscrit,
                'categorie_frais' => ($options['categorie_frais'] ?? null) ?: null,
                'redoublant' => (bool) ($options['redoublant'] ?? false),
            ]);

            $frais = $this->genererFrais($inscription);
            $this->verifierCapacite($classe);

            $this->journal->enregistrer('inscription.creee', $inscription,
                "{$eleve->nom_complet} : {$inscription->type->libelle()} en {$classe->nom} ({$annee->libelle})", null,
                ['classe' => $classe->nom, 'type' => $inscription->type->value, 'frais_generes' => $frais]);

            return $inscription;
        });
    }

    /**
     * RG-ELV-04 : la classe change, l'inscription (donc notes, présences et frais) est conservée.
     */
    public function changerClasse(Inscription $inscription, Classe $nouvelle, string $dateEffet, ?string $motif): void
    {
        $this->avertissement = null;
        $this->verifierModifiable($inscription);

        if ($nouvelle->annee_scolaire_id !== $inscription->annee_scolaire_id) {
            throw ValidationException::withMessages(['classe_id' => 'La nouvelle classe doit appartenir à la même année scolaire.']);
        }
        if ($nouvelle->id === $inscription->classe_id) {
            throw ValidationException::withMessages(['classe_id' => 'L\'élève est déjà dans cette classe.']);
        }
        if (! $inscription->estActive()) {
            throw ValidationException::withMessages(['classe_id' => 'Un élève parti ne peut pas changer de classe.']);
        }

        $ancienne = $inscription->classe;

        DB::transaction(function () use ($inscription, $ancienne, $nouvelle, $dateEffet, $motif) {

            ChangementClasse::create([
                'inscription_id' => $inscription->id,
                'ancienne_classe_id' => $ancienne->id,
                'nouvelle_classe_id' => $nouvelle->id,
                'date_effet' => $dateEffet,
                'motif' => $motif,
                'user_id' => auth()->id(),
            ]);
            $inscription->update(['classe_id' => $nouvelle->id]);
            $this->verifierCapacite($nouvelle);

            $this->journal->enregistrer('inscription.classe_changee', $inscription,
                "{$inscription->eleve->nom_complet} : {$ancienne->nom} → {$nouvelle->nom}",
                ['classe' => $ancienne->nom], ['classe' => $nouvelle->nom, 'date_effet' => $dateEffet, 'motif' => $motif]);
        });

        if ($nouvelle->niveau_id !== $ancienne->niveau_id && $inscription->fraisEleves()->exists()) {
            // Les frais dus restent ceux de l'inscription initiale ; un ajustement passe par une remise.
            $this->avertissement = trim(($this->avertissement ?? '').' Changement de niveau : les frais dus n\'ont pas été modifiés.');
        }
    }

    /**
     * RG-ELV-03 : l'élève parti sort des appels et bulletins suivants ; son historique et ses dettes restent (RG-FIN-10).
     */
    public function enregistrerDepart(Inscription $inscription, string $date, string $motif): void
    {
        $this->verifierModifiable($inscription);
        if (! $inscription->estActive()) {
            throw ValidationException::withMessages(['date_sortie' => 'Le départ de cet élève est déjà enregistré.']);
        }
        if (CarbonImmutable::parse($date)->lt($inscription->date_inscription)) {
            throw ValidationException::withMessages(['date_sortie' => 'La date de départ ne peut pas précéder la date d\'inscription.']);
        }

        $inscription->update(['statut' => StatutInscription::Parti, 'date_sortie' => $date, 'motif_sortie' => $motif]);
        $this->journal->enregistrer('inscription.depart', $inscription,
            "Départ de {$inscription->eleve->nom_complet} ({$inscription->classe->nom})", null, ['date_sortie' => $date, 'motif' => $motif]);
    }

    public function annulerDepart(Inscription $inscription): void
    {
        $this->verifierModifiable($inscription);
        $avant = ['date_sortie' => $inscription->date_sortie?->format('Y-m-d'), 'motif' => $inscription->motif_sortie];

        $inscription->update(['statut' => StatutInscription::Inscrit, 'date_sortie' => null, 'motif_sortie' => null]);
        $this->journal->enregistrer('inscription.depart_annule', $inscription,
            "Réintégration de {$inscription->eleve->nom_complet} ({$inscription->classe->nom})", $avant);
    }

    /**
     * RG-FIN-03 : frais obligatoires générés à partir des grilles (voir FraisService).
     *
     * @return int nombre de lignes créées
     */
    public function genererFrais(Inscription $inscription): int
    {
        return $this->frais->genererObligatoires($inscription);
    }

    private function verifierModifiable(Inscription $inscription): void
    {
        if ($inscription->anneeScolaire->estCloturee()) {
            throw ValidationException::withMessages(['inscription' => "L'année {$inscription->anneeScolaire->libelle} est clôturée : l'inscription est en lecture seule."]);
        }
    }

    private function verifierCapacite(Classe $classe): void
    {
        $effectif = $classe->inscriptionsActives()->count();
        if ($classe->capacite && $effectif > $classe->capacite) {
            $this->avertissement = "Attention : la classe {$classe->nom} compte désormais {$effectif} élèves pour une capacité de {$classe->capacite}.";
        }
    }
}
