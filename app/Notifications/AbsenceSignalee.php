<?php

namespace App\Notifications;

use App\Models\Presence;
use Illuminate\Notifications\Notification;

/**
 * Absence ou retard signalé au responsable légal (canal interne uniquement, aucun SMS simulé).
 */
class AbsenceSignalee extends Notification
{
    public function __construct(private Presence $presence) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $appel = $this->presence->appel;
        $eleve = $this->presence->inscription->eleve;
        $quoi = $this->presence->statut->value === 'retard'
            ? 'est arrivé(e) en retard'.($this->presence->minutes_retard ? " ({$this->presence->minutes_retard} min)" : '')
            : 'a été noté(e) absent(e)';

        return [
            'titre' => "Vie scolaire : {$eleve->prenoms}",
            'message' => "{$eleve->nom_complet} {$quoi} le {$appel->date_appel->format('d/m/Y')} ({$appel->creneau->libelle()}).",
            'lien' => route('famille.enfant', [$eleve, 'assiduite']),
            'eleve_id' => $eleve->id,
            'presence_id' => $this->presence->id,
        ];
    }
}
