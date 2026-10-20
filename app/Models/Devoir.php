<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Devoir extends Model
{
    protected $fillable = [
        'affectation_id', 'cours_id', 'titre', 'consignes', 'fichier_chemin', 'fichier_nom', 'date_limite',
        'bareme', 'remise_en_ligne', 'accepte_retard', 'est_publie', 'publie_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date_limite' => 'datetime',
            'bareme' => 'decimal:2',
            'remise_en_ligne' => 'boolean',
            'accepte_retard' => 'boolean',
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

    public function cours(): BelongsTo
    {
        return $this->belongsTo(Cours::class);
    }

    public function remises(): HasMany
    {
        return $this->hasMany(RemiseDevoir::class);
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function estEchu(): bool
    {
        return $this->date_limite->isPast();
    }

    /**
     * Un élève peut encore déposer (ou remplacer) son travail.
     */
    public function accepteLesRemises(): bool
    {
        return $this->est_publie && $this->remise_en_ligne && (! $this->estEchu() || $this->accepte_retard);
    }
}
