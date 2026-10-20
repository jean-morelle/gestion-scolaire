<?php

namespace App\Notifications;

use App\Models\FraisEleve;
use Illuminate\Notifications\Notification;

/**
 * Rappel d'échéance (« proche » ou « depassee ») adressé aux responsables payeurs (canal interne).
 */
class EcheancePaiement extends Notification
{
    public function __construct(private FraisEleve $frais, private int $reste, private string $rappel) {}

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
        $eleve = $this->frais->inscription->eleve;
        $date = $this->frais->date_limite->format('d/m/Y');

        return [
            'titre' => $this->rappel === 'depassee' ? "Échéance dépassée : {$eleve->prenoms}" : "Échéance à venir : {$eleve->prenoms}",
            'message' => "« {$this->frais->libelle} » : reste ".montant($this->reste).($this->rappel === 'depassee' ? " depuis le {$date}." : " à régler avant le {$date}."),
            'lien' => route('famille.enfant', [$eleve, 'finances']),
            'frais_eleve_id' => $this->frais->id,
            'rappel' => $this->rappel,
        ];
    }
}
