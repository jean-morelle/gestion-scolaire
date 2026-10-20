<?php

namespace App\Notifications;

use App\Enums\Role;
use App\Models\Devoir;
use Illuminate\Notifications\Notification;

/**
 * Nouveau devoir à rendre, pour les élèves de la classe et leurs responsables (canal interne).
 */
class DevoirPublie extends Notification
{
    public function __construct(private Devoir $devoir) {}

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
        $affectation = $this->devoir->affectation;

        return [
            'titre' => "Devoir de {$affectation->matiere->libelle}",
            'message' => "« {$this->devoir->titre} » ({$affectation->classe->nom}) à rendre le {$this->devoir->date_limite->translatedFormat('l j F à H\hi')}.",
            'lien' => $notifiable->aRole(Role::Eleve) ? route('eleve.devoirs.show', $this->devoir) : route('famille.enfants'),
            'devoir_id' => $this->devoir->id,
        ];
    }
}
