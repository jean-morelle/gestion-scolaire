<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Compteur nommé (numéros de reçus, matricules). Toujours incrémenté dans une
 * transaction avec verrou ; voir NumerotationService (étape 5).
 */
class Sequence extends Model
{
    protected $fillable = ['cle', 'valeur'];

    protected function casts(): array
    {
        return [
            'valeur' => 'integer',
        ];
    }
}
