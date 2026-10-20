<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Matiere extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'libelle', 'couleur'];

    public function affectations(): HasMany
    {
        return $this->hasMany(Affectation::class);
    }

    public function enseignants(): BelongsToMany
    {
        return $this->belongsToMany(Enseignant::class)->withTimestamps();
    }
}
