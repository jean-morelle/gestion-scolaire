<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Réduction accordée sur un frais (bourse, fratrie...). Annulée, jamais supprimée.
 */
class Remise extends Model
{
    protected $fillable = ['frais_eleve_id', 'montant', 'motif', 'accordee_par', 'annulee_at', 'annulee_par', 'motif_annulation'];

    protected function casts(): array
    {
        return [
            'montant' => 'integer',
            'annulee_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(fn () => throw new LogicException('Une remise ne peut pas être supprimée : elle doit être annulée.'));
    }

    public function scopeActives(Builder $query): void
    {
        $query->whereNull('annulee_at');
    }

    public function fraisEleve(): BelongsTo
    {
        return $this->belongsTo(FraisEleve::class);
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accordee_par');
    }

    public function annulateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'annulee_par');
    }
}
