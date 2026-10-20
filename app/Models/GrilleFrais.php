<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Montant d'un type de frais pour une année, un niveau et éventuellement une catégorie d'élèves.
 */
class GrilleFrais extends Model
{
    use HasFactory;

    protected $table = 'grilles_frais';

    protected $fillable = ['annee_scolaire_id', 'niveau_id', 'type_frais_id', 'categorie', 'montant'];

    protected function casts(): array
    {
        return [
            'montant' => 'integer',
        ];
    }

    public function anneeScolaire(): BelongsTo
    {
        return $this->belongsTo(AnneeScolaire::class);
    }

    public function niveau(): BelongsTo
    {
        return $this->belongsTo(Niveau::class);
    }

    public function typeFrais(): BelongsTo
    {
        return $this->belongsTo(TypeFrais::class);
    }

    public function tranches(): HasMany
    {
        return $this->hasMany(Tranche::class)->orderBy('ordre');
    }
}
