<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Publication des résultats d'une classe pour une période (RG-NOT-10).
 */
class Publication extends Model
{
    protected $fillable = ['classe_id', 'periode_id', 'publiee_par', 'publiee_at', 'depubliee_at'];

    protected function casts(): array
    {
        return [
            'publiee_at' => 'datetime',
            'depubliee_at' => 'datetime',
        ];
    }

    public function scopeEnVigueur(Builder $query): void
    {
        $query->whereNull('depubliee_at');
    }

    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class);
    }

    public function periode(): BelongsTo
    {
        return $this->belongsTo(Periode::class);
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'publiee_par');
    }
}
