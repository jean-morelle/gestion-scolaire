<?php

namespace App\Services;

use App\Enums\ModePaiement;
use App\Enums\StatutDepense;
use App\Models\AnneeScolaire;
use App\Models\Depense;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Dépenses de l'établissement : numéro unique DEP-AAAA-NNNNN, pièce justificative privée,
 * jamais modifiées ni supprimées (annulation motivée et journalisée).
 */
class DepenseService
{
    public function __construct(private NumerotationService $numerotation, private JournalService $journal) {}

    /**
     * @param  array{categorie_depense_id: int, libelle: string, montant: int, date_depense: string, mode: string, beneficiaire?: ?string, reference_piece?: ?string}  $donnees
     */
    public function enregistrer(array $donnees, ?UploadedFile $justificatif, User $user): Depense
    {
        $date = CarbonImmutable::parse($donnees['date_depense']);
        if ($date->isFuture() && ! $date->isToday()) {
            throw ValidationException::withMessages(['date_depense' => 'La date ne peut pas être dans le futur.']);
        }
        $annee = AnneeScolaire::query()->whereDate('date_debut', '<=', $date)->whereDate('date_fin', '>=', $date)->first() ?? annee_active();
        if (! $annee) {
            throw ValidationException::withMessages(['date_depense' => 'Aucune année scolaire ne couvre cette date.']);
        }
        if ($annee->estCloturee()) {
            throw ValidationException::withMessages(['date_depense' => "L'année {$annee->libelle} est clôturée."]);
        }

        $chemin = $justificatif?->store('depenses', 'local');

        try {
            return DB::transaction(function () use ($donnees, $date, $annee, $chemin, $user) {
                $depense = Depense::create([
                    'numero' => $this->numerotation->depense($date->year),
                    'categorie_depense_id' => $donnees['categorie_depense_id'],
                    'annee_scolaire_id' => $annee->id,
                    'libelle' => $donnees['libelle'],
                    'montant' => (int) $donnees['montant'],
                    'date_depense' => $date->toDateString(),
                    'mode' => ModePaiement::from($donnees['mode']),
                    'beneficiaire' => $donnees['beneficiaire'] ?? null,
                    'reference_piece' => $donnees['reference_piece'] ?? null,
                    'justificatif_chemin' => $chemin,
                    'saisi_par' => $user->id,
                    'statut' => StatutDepense::Valide,
                ]);
                $this->journal->enregistrer('depense.enregistree', $depense, "Dépense {$depense->numero} : {$depense->libelle} (".montant($depense->montant).')');

                return $depense;
            });
        } catch (\Throwable $e) {
            if ($chemin) {
                Storage::disk('local')->delete($chemin);
            }
            throw $e;
        }
    }

    public function annuler(Depense $depense, string $motif, User $user): void
    {
        if ($depense->estAnnulee()) {
            throw ValidationException::withMessages(['motif_annulation' => 'Cette dépense est déjà annulée.']);
        }
        $depense->update(['statut' => StatutDepense::Annulee, 'annulee_par' => $user->id, 'annulee_at' => now(), 'motif_annulation' => $motif]);
        $this->journal->enregistrer('depense.annulee', $depense, "Dépense {$depense->numero} annulée (".montant($depense->montant).')',
            ['statut' => 'valide'], ['statut' => 'annulee', 'motif' => $motif]);
    }
}
