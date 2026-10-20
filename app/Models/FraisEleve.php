<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Montant dû par un élève (une ligne par tranche), généré à l'inscription (RG-FIN-03).
 */
class FraisEleve extends Model
{
    use HasFactory;

    protected $table = 'frais_eleves';

    protected $fillable = ['inscription_id', 'type_frais_id', 'tranche_id', 'libelle', 'montant_du', 'date_limite'];

    protected function casts(): array
    {
        return [
            'montant_du' => 'integer',
            'date_limite' => 'date',
        ];
    }

    public function inscription(): BelongsTo
    {
        return $this->belongsTo(Inscription::class);
    }

    public function typeFrais(): BelongsTo
    {
        return $this->belongsTo(TypeFrais::class);
    }

    public function tranche(): BelongsTo
    {
        return $this->belongsTo(Tranche::class);
    }

    public function remises(): HasMany
    {
        return $this->hasMany(Remise::class);
    }

    public function imputations(): HasMany
    {
        return $this->hasMany(Imputation::class);
    }
}
