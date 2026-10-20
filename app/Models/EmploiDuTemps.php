<?php

namespace App\Models;

use App\Enums\Jour;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmploiDuTemps extends Model
{
    protected $table = 'emplois_du_temps';

    protected $fillable = ['affectation_id', 'jour', 'heure_debut', 'heure_fin', 'salle'];

    protected function casts(): array
    {
        return [
            'jour' => Jour::class,
        ];
    }

    public function affectation(): BelongsTo
    {
        return $this->belongsTo(Affectation::class);
    }

    public function horaire(): string
    {
        return substr($this->heure_debut, 0, 5).' - '.substr($this->heure_fin, 0, 5);
    }
}
