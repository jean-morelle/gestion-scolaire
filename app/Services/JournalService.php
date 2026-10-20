<?php

namespace App\Services;

use App\Models\JournalActivite;
use Illuminate\Database\Eloquent\Model;

/**
 * Journal d'audit en ajout seul (RG-SEC-01/02) : qui, quoi, quand, valeurs avant/après.
 */
class JournalService
{
    public function enregistrer(
        string $action,
        ?Model $sujet = null,
        ?string $description = null,
        ?array $anciennes = null,
        ?array $nouvelles = null,
        ?int $userId = null,
    ): JournalActivite {
        $requete = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();

        return JournalActivite::create([
            'user_id' => $userId ?? auth()->id(),
            'action' => $action,
            'sujet_type' => $sujet?->getMorphClass(),
            'sujet_id' => $sujet?->getKey(),
            'description' => $description !== null ? mb_substr($description, 0, 255) : null,
            'anciennes_valeurs' => $anciennes,
            'nouvelles_valeurs' => $nouvelles,
            'ip' => $requete?->ip(),
            'user_agent' => $requete ? mb_substr((string) $requete->userAgent(), 0, 255) : null,
        ]);
    }
}
