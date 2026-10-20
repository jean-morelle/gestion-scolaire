<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Instantané figé d'un bulletin au moment de la publication : il ne bouge plus si les notes changent,
 * jusqu'à une republication (version + 1, historisée).
 */
class Bulletin extends Model
{
    protected $fillable = [
        'inscription_id', 'periode_id', 'publication_id', 'moyenne_generale', 'rang', 'effectif',
        'mention', 'distinction', 'moyenne_annuelle', 'rang_annuel', 'appreciation', 'lignes', 'assiduite', 'version', 'publie_par',
    ];

    protected function casts(): array
    {
        return [
            'moyenne_generale' => 'float',
            'moyenne_annuelle' => 'float',
            'lignes' => 'array',
            'assiduite' => 'array',
        ];
    }

    public function inscription(): BelongsTo
    {
        return $this->belongsTo(Inscription::class);
    }

    public function periode(): BelongsTo
    {
        return $this->belongsTo(Periode::class);
    }

    public function publication(): BelongsTo
    {
        return $this->belongsTo(Publication::class);
    }

    /**
     * Visible des familles uniquement si la publication est en vigueur (RG-NOT-10).
     */
    public function estVisibleDesFamilles(): bool
    {
        return $this->publication !== null && $this->publication->depubliee_at === null;
    }
}
