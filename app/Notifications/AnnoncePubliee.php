<?php

namespace App\Notifications;

use App\Models\Annonce;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Nouvelle annonce de l'établissement (canal interne).
 */
class AnnoncePubliee extends Notification
{
    public function __construct(private Annonce $annonce) {}

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
        return [
            'titre' => 'Annonce : '.$this->annonce->titre,
            'message' => Str::limit(strip_tags($this->annonce->contenu), 140),
            'lien' => route('annonces.show', $this->annonce),
            'annonce_id' => $this->annonce->id,
        ];
    }
}
