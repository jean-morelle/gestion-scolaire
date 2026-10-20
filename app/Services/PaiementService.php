<?php

namespace App\Services;

use App\Enums\ModePaiement;
use App\Enums\StatutPaiement;
use App\Models\FraisEleve;
use App\Models\Imputation;
use App\Models\Inscription;
use App\Models\Paiement;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Encaissements et annulations. Toute l'opération (verrou, contrôle du reste dû, numéro de reçu, imputations)
 * se fait dans une seule transaction : en cas d'échec, rien n'est enregistré et aucun numéro n'est consommé.
 */
class PaiementService
{
    public function __construct(
        private NumerotationService $numerotation,
        private JournalService $journal,
        private SoldeService $soldes,
    ) {}

    /**
     * @param  array<int, int>|null  $imputationManuelle  frais_eleve_id => montant ; null = échéances les plus anciennes d'abord
     */
    public function encaisser(
        Inscription $inscription,
        int $montant,
        string $date,
        ModePaiement $mode,
        ?string $reference,
        ?string $payeur,
        User $encaisseur,
        ?array $imputationManuelle = null,
    ): Paiement {
        if ($montant <= 0) {
            throw ValidationException::withMessages(['montant' => 'Le montant doit être supérieur à zéro.']);
        }
        if (CarbonImmutable::parse($date)->isFuture() && ! CarbonImmutable::parse($date)->isToday()) {
            throw ValidationException::withMessages(['date_paiement' => 'La date de paiement ne peut pas être dans le futur.']);
        }
        if ($mode !== ModePaiement::Especes && blank($reference)) {
            throw ValidationException::withMessages(['reference_externe' => "Indiquez la référence de l'opération ({$mode->libelle()})."]);
        }

        return DB::transaction(function () use ($inscription, $montant, $date, $mode, $reference, $payeur, $encaisseur, $imputationManuelle) {
            // Verrou sur l'inscription : deux caisses ne peuvent pas encaisser le même reste dû en même temps.
            Inscription::query()->whereKey($inscription->id)->lockForUpdate()->first();

            $lignes = $this->soldes->situation($inscription)['lignes']->filter(fn ($l) => $l['reste'] > 0);
            $reste = (int) $lignes->sum('reste');
            if ($reste <= 0) {
                throw ValidationException::withMessages(['montant' => 'Cet élève n\'a aucun reste à payer.']);
            }
            // RG-FIN-05 (Q5) : pas d'avance au-delà du reste dû.
            if ($montant > $reste) {
                throw ValidationException::withMessages(['montant' => 'Le montant dépasse le reste à payer ('.montant($reste).').']);
            }

            $repartition = $imputationManuelle === null
                ? $this->repartir($lignes, $montant)
                : $this->verifierRepartition($lignes, $imputationManuelle, $montant);

            $paiement = Paiement::create([
                'numero_recu' => $this->numerotation->recu(CarbonImmutable::parse($date)->year),
                'inscription_id' => $inscription->id,
                'montant' => $montant,
                'date_paiement' => $date,
                'mode' => $mode,
                'reference_externe' => $reference ?: null,
                'payeur_nom' => $payeur ?: null,
                'encaisse_par' => $encaisseur->id,
                'statut' => StatutPaiement::Valide,
            ]);
            foreach ($repartition as $fraisId => $part) {
                Imputation::create(['paiement_id' => $paiement->id, 'frais_eleve_id' => $fraisId, 'montant' => $part]);
            }

            $this->journal->enregistrer('paiement.enregistre', $paiement,
                "Reçu {$paiement->numero_recu} : ".montant($montant)." pour {$inscription->eleve->nom_complet}", null,
                ['montant' => $montant, 'mode' => $mode->value, 'imputations' => $repartition]);

            return $paiement;
        });
    }

    /**
     * RG-FIN-08 : jamais supprimé ni modifié ; l'annulation est motivée, datée, attribuée et journalisée.
     * Le reçu reste consultable avec la mention « ANNULÉ ».
     */
    public function annuler(Paiement $paiement, string $motif, User $user): void
    {
        DB::transaction(function () use ($paiement, $motif, $user) {
            $paiement = Paiement::query()->whereKey($paiement->id)->lockForUpdate()->firstOrFail();
            if ($paiement->estAnnule()) {
                throw ValidationException::withMessages(['motif_annulation' => 'Ce paiement est déjà annulé.']);
            }
            $paiement->update([
                'statut' => StatutPaiement::Annule,
                'annule_par' => $user->id,
                'annule_at' => now(),
                'motif_annulation' => $motif,
            ]);
            $this->journal->enregistrer('paiement.annule', $paiement, "Reçu {$paiement->numero_recu} annulé (".montant($paiement->montant).')',
                ['statut' => 'valide'], ['statut' => 'annule', 'motif' => $motif]);
        });
    }

    /**
     * Échéances les plus anciennes d'abord (les lignes arrivent triées par date limite).
     *
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $lignes
     * @return array<int, int>
     */
    private function repartir($lignes, int $montant): array
    {
        $repartition = [];
        foreach ($lignes as $ligne) {
            if ($montant <= 0) {
                break;
            }
            $part = min($montant, $ligne['reste']);
            $repartition[$ligne['frais']->id] = $part;
            $montant -= $part;
        }

        return $repartition;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $lignes
     * @param  array<int, int>  $saisie
     * @return array<int, int>
     */
    private function verifierRepartition($lignes, array $saisie, int $montant): array
    {
        $parFrais = $lignes->keyBy(fn ($l) => $l['frais']->id);
        $repartition = [];
        foreach ($saisie as $fraisId => $part) {
            $part = (int) $part;
            if ($part <= 0) {
                continue;
            }
            if (! $parFrais->has((int) $fraisId)) {
                throw ValidationException::withMessages(['imputations' => 'Une des lignes choisies n\'appartient pas à cet élève ou est déjà réglée.']);
            }
            if ($part > $parFrais[(int) $fraisId]['reste']) {
                $libelle = FraisEleve::find($fraisId)?->libelle;
                throw ValidationException::withMessages(['imputations' => "Le montant imputé sur « {$libelle} » dépasse son reste à payer."]);
            }
            $repartition[(int) $fraisId] = $part;
        }
        if (array_sum($repartition) !== $montant) {
            throw ValidationException::withMessages(['imputations' => 'La répartition ('.montant(array_sum($repartition)).') doit être égale au montant encaissé ('.montant($montant).').']);
        }

        return $repartition;
    }
}
