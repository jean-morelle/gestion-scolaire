<?php

namespace App\Services;

use App\Enums\StatutPresence;
use App\Models\Inscription;
use App\Models\Justificatif;
use App\Models\Presence;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * RG-PRE-05 : un justificatif (motif obligatoire, pièce facultative) excuse les absences de sa plage de dates,
 * y compris celles saisies plus tard (voir AppelService::enregistrer).
 */
class JustificatifService
{
    public function __construct(private JournalService $journal) {}

    /**
     * @return array{0: Justificatif, 1: int} le justificatif et le nombre d'absences excusées
     */
    public function enregistrer(Inscription $inscription, string $debut, string $fin, string $motif, ?UploadedFile $fichier): array
    {
        $chemin = $fichier?->store("justificatifs/{$inscription->id}", 'local');

        try {
            return DB::transaction(function () use ($inscription, $debut, $fin, $motif, $chemin) {
                $justificatif = Justificatif::create([
                    'inscription_id' => $inscription->id,
                    'date_debut' => $debut,
                    'date_fin' => $fin,
                    'motif' => $motif,
                    'fichier_chemin' => $chemin,
                    'saisi_par' => auth()->id(),
                ]);

                $absences = $this->presencesCouvertes($justificatif)->where('statut', StatutPresence::Absent)->get();
                foreach ($absences as $presence) {
                    $presence->update(['statut' => StatutPresence::AbsentExcuse]);
                }
                $justificatif->presences()->sync($absences->pluck('id'));

                $this->journal->enregistrer('absence.justifiee', $inscription,
                    "Justificatif pour {$inscription->eleve->nom_complet} du {$justificatif->date_debut->format('d/m/Y')} au {$justificatif->date_fin->format('d/m/Y')}",
                    null, ['motif' => $motif, 'absences_excusees' => $absences->count()]);

                return [$justificatif, $absences->count()];
            });
        } catch (\Throwable $e) {
            if ($chemin) {
                Storage::disk('local')->delete($chemin);
            }
            throw $e;
        }
    }

    /**
     * Retire le justificatif : les absences qu'il excusait (et qu'aucun autre ne couvre) redeviennent non justifiées.
     */
    public function supprimer(Justificatif $justificatif): int
    {
        return DB::transaction(function () use ($justificatif) {
            $retablies = 0;
            foreach ($justificatif->presences()->withCount('justificatifs')->get() as $presence) {
                if ($presence->justificatifs_count <= 1) {
                    $presence->update(['statut' => StatutPresence::Absent]);
                    $retablies++;
                }
            }
            $justificatif->presences()->detach();
            if ($justificatif->fichier_chemin) {
                Storage::disk('local')->delete($justificatif->fichier_chemin);
            }
            $justificatif->delete();

            $this->journal->enregistrer('absence.justificatif_supprime', $justificatif->inscription,
                "Justificatif du {$justificatif->date_debut->format('d/m/Y')} retiré pour {$justificatif->inscription->eleve->nom_complet}",
                ['motif' => $justificatif->motif], ['absences_retablies' => $retablies]);

            return $retablies;
        });
    }

    /**
     * @return Builder<Presence>
     */
    private function presencesCouvertes(Justificatif $justificatif): Builder
    {
        return Presence::query()
            ->where('inscription_id', $justificatif->inscription_id)
            ->whereHas('appel', fn (Builder $q) => $q->whereDate('date_appel', '>=', $justificatif->date_debut)
                ->whereDate('date_appel', '<=', $justificatif->date_fin));
    }
}
