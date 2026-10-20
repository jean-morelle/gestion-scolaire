<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Part d'un paiement affectée à un frais dû.
 */
class Imputation extends Model
{
    protected $fillable = ['paiement_id', 'frais_eleve_id', 'montant'];

    protected function casts(): array
    {
        return [
            'montant' => 'integer',
        ];
    }

    public function paiement(): BelongsTo
    {
        return $this->belongsTo(Paiement::class);
    }

    public function fraisEleve(): BelongsTo
    {
        return $this->belongsTo(FraisEleve::class);
    }
}
