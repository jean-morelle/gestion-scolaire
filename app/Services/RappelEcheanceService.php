<?php

namespace App\Services;

use App\Enums\StatutInscription;
use App\Models\AnneeScolaire;
use App\Models\Inscription;
use App\Models\User;
use App\Notifications\EcheancePaiement;
use Carbon\CarbonImmutable;

/**
 * Rappels d'échéances (paramètre « jours_rappel_echeance ») : un rappel « proche » quand l'échéance approche,
 * un rappel « depassee » une fois la date passée. Chaque rappel n'est envoyé qu'une fois par ligne de frais et par
 * destinataire, même si la tâche tourne plusieurs fois ou a manqué des jours.
 */
class RappelEcheanceService
{
    public function __construct(private SoldeService $soldes) {}

    /**
     * @return int nombre de notifications envoyées
     */
    public function envoyer(?CarbonImmutable $aujourdhui = null): int
    {
        $aujourdhui ??= CarbonImmutable::today();
        $jours = (int) etablissement()->parametre('jours_rappel_echeance', 7);
        $annee = AnneeScolaire::active();
        if (! $annee || $jours < 0) {
            return 0;
        }

        $envoyees = 0;
        Inscription::query()->where('annee_scolaire_id', $annee->id)->where('statut', StatutInscription::Inscrit)
            ->whereHas('fraisEleves', fn ($q) => $q->whereDate('date_limite', '<=', $aujourdhui->addDays($jours)))
            ->with(['eleve.responsables.user'])
            ->each(function (Inscription $inscription) use ($aujourdhui, $jours, &$envoyees) {
                $destinataires = $this->destinataires($inscription);
                if ($destinataires->isEmpty()) {
                    return;
                }
                foreach ($this->soldes->situation($inscription, $aujourdhui)['lignes'] as $ligne) {
                    if ($ligne['reste'] <= 0) {
                        continue;
                    }
                    $date = CarbonImmutable::parse($ligne['frais']->date_limite);
                    $rappel = match (true) {
                        $date->lt($aujourdhui) => 'depassee',
                        $date->lte($aujourdhui->addDays($jours)) => 'proche',
                        default => null,
                    };
                    if (! $rappel) {
                        continue;
                    }
                    foreach ($destinataires as $user) {
                        if (! $this->dejaEnvoye($user, $ligne['frais']->id, $rappel)) {
                            $ligne['frais']->setRelation('inscription', $inscription);
                            $user->notify(new EcheancePaiement($ligne['frais'], $ligne['reste'], $rappel));
                            $envoyees++;
                        }
                    }
                }
            });

        return $envoyees;
    }

    /**
     * Responsables désignés comme payeurs ; à défaut, tous les responsables ayant un compte actif.
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    private function destinataires(Inscription $inscription)
    {
        $responsables = $inscription->eleve->responsables;
        $payeurs = $responsables->filter(fn ($r) => $r->pivot->est_payeur);

        return ($payeurs->isNotEmpty() ? $payeurs : $responsables)
            ->pluck('user')->filter(fn ($u) => $u?->est_actif)->unique('id')->values();
    }

    private function dejaEnvoye(User $user, int $fraisId, string $rappel): bool
    {
        return $user->notifications()->where('type', EcheancePaiement::class)
            ->where('data->frais_eleve_id', $fraisId)->where('data->rappel', $rappel)->exists();
    }
}
