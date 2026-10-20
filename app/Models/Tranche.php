<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Tranche extends Model
{
    use HasFactory;

    protected $fillable = ['grille_frais_id', 'libelle', 'montant', 'date_limite', 'ordre'];

    protected function casts(): array
    {
        return [
            'montant' => 'integer',
            'date_limite' => 'date',
        ];
    }

    public function grilleFrais(): BelongsTo
    {
        return $this->belongsTo(GrilleFrais::class);
    }
}
