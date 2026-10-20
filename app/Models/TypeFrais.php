<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TypeFrais extends Model
{
    use HasFactory;

    protected $table = 'types_frais';

    protected $fillable = ['code', 'libelle', 'est_obligatoire'];

    protected function casts(): array
    {
        return [
            'est_obligatoire' => 'boolean',
        ];
    }

    public function grilles(): HasMany
    {
        return $this->hasMany(GrilleFrais::class);
    }
}
