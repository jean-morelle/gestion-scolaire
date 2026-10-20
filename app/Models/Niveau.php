<?php

namespace App\Models;

use App\Enums\Cycle;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Niveau extends Model
{
    use HasFactory;

    protected $table = 'niveaux';

    protected $fillable = ['libelle', 'cycle', 'ordre'];

    protected function casts(): array
    {
        return [
            'cycle' => Cycle::class,
        ];
    }

    public function classes(): HasMany
    {
        return $this->hasMany(Classe::class);
    }

    public function grillesFrais(): HasMany
    {
        return $this->hasMany(GrilleFrais::class);
    }
}
