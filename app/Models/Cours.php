<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cours extends Model
{
    protected $table = 'cours';

    protected $fillable = ['affectation_id', 'titre', 'description', 'est_publie', 'publie_at', 'created_by'];

    protected function casts(): array
    {
        return [
            'est_publie' => 'boolean',
            'publie_at' => 'datetime',
        ];
    }

    public function scopePublies(Builder $query): void
    {
        $query->where('est_publie', true);
    }

    public function affectation(): BelongsTo
    {
        return $this->belongsTo(Affectation::class);
    }

    public function ressources(): HasMany
    {
        return $this->hasMany(RessourcePedagogique::class)->orderBy('ordre')->orderBy('id');
    }

    public function devoirs(): HasMany
    {
        return $this->hasMany(Devoir::class);
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
