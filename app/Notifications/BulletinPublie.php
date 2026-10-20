<?php

namespace App\Notifications;

use App\Models\Bulletin;
use Illuminate\Notifications\Notification;

/**
 * Publication d'un bulletin, adressée aux comptes parents et au compte de l'élève (canal interne).
 */
class BulletinPublie extends Notification
{
    public function __construct(private Bulletin $bulletin) {}

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
        $eleve = $this->bulletin->inscription->eleve;
        $republication = $this->bulletin->version > 1;

        return [
            'titre' => ($republication ? 'Bulletin corrigé' : 'Bulletin disponible')." : {$eleve->prenoms}",
            'message' => "Le bulletin du {$this->bulletin->periode->libelle} de {$eleve->nom_complet} est disponible"
                .($republication ? ' (version corrigée).' : '.'),
            'lien' => route('bulletins.pdf', $this->bulletin),
            'bulletin_id' => $this->bulletin->id,
        ];
    }
}
