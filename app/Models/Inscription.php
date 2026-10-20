<?php

namespace App\Models;

use App\Enums\StatutInscription;
use App\Enums\TypeInscription;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Élève × année scolaire. Notes, présences, frais et paiements s'y rattachent.
 */
class Inscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'eleve_id', 'annee_scolaire_id', 'classe_id', 'date_inscription', 'type', 'statut',
        'date_sortie', 'motif_sortie', 'categorie_frais', 'redoublant',
    ];

    protected function casts(): array
    {
        return [
            'date_inscription' => 'date',
            'date_sortie' => 'date',
            'type' => TypeInscription::class,
            'statut' => StatutInscription::class,
            'redoublant' => 'boolean',
        ];
    }

    public function eleve(): BelongsTo
    {
        return $this->belongsTo(Eleve::class);
    }

    public function anneeScolaire(): BelongsTo
    {
        return $this->belongsTo(AnneeScolaire::class);
    }

    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class);
    }

    public function changementsClasse(): HasMany
    {
        return $this->hasMany(ChangementClasse::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }

    public function appreciations(): HasMany
    {
        return $this->hasMany(Appreciation::class);
    }

    public function presences(): HasMany
    {
        return $this->hasMany(Presence::class);
    }

    public function justificatifs(): HasMany
    {
        return $this->hasMany(Justificatif::class);
    }

    public function fraisEleves(): HasMany
    {
        return $this->hasMany(FraisEleve::class)->orderBy('date_limite');
    }

    public function paiements(): HasMany
    {
        return $this->hasMany(Paiement::class);
    }

    public function bulletins(): HasMany
    {
        return $this->hasMany(Bulletin::class);
    }

    public function remisesDevoirs(): HasMany
    {
        return $this->hasMany(RemiseDevoir::class);
    }

    public function estActive(): bool
    {
        return $this->statut === StatutInscription::Inscrit;
    }

    public function scopeActives(Builder $query): void
    {
        $query->where('statut', StatutInscription::Inscrit);
    }
}
