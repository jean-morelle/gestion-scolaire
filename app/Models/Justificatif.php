<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Justificatif d'absence couvrant une ou plusieurs présences (RG-PRE-05).
 */
class Justificatif extends Model
{
    protected $fillable = ['inscription_id', 'date_debut', 'date_fin', 'motif', 'fichier_chemin', 'saisi_par'];

    protected function casts(): array
    {
        return [
            'date_debut' => 'date',
            'date_fin' => 'date',
        ];
    }

    public function inscription(): BelongsTo
    {
        return $this->belongsTo(Inscription::class);
    }

    public function presences(): BelongsToMany
    {
        return $this->belongsToMany(Presence::class, 'justificatif_presence');
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'saisi_par');
    }
}
