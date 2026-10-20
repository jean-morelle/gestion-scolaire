<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Enseignant extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'matricule', 'specialite', 'diplome', 'date_embauche', 'est_actif'];

    protected function casts(): array
    {
        return [
            'est_actif' => 'boolean',
            'date_embauche' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function affectations(): HasMany
    {
        return $this->hasMany(Affectation::class);
    }

    /**
     * Affectations de l'année scolaire active.
     */
    public function affectationsActives(): HasMany
    {
        return $this->affectations()->whereHas('classe.anneeScolaire', fn (Builder $q) => $q->where('est_active', true));
    }

    /**
     * Matières que l'enseignant est habilité à enseigner.
     */
    public function matieres(): BelongsToMany
    {
        return $this->belongsToMany(Matiere::class)->withTimestamps()->orderBy('libelle');
    }

    public function classesPrincipales(): HasMany
    {
        return $this->hasMany(Classe::class, 'professeur_principal_id');
    }

    /**
     * Base du contrôle d'accès à la saisie des notes (RG-ENS-02).
     */
    public function enseigne(Affectation $affectation): bool
    {
        return $affectation->enseignant_id === $this->id;
    }

    /**
     * Enseigne dans la classe ou en est le professeur principal.
     */
    public function intervientDans(Classe $classe): bool
    {
        return $classe->professeur_principal_id === $this->id
            || $this->affectations()->where('classe_id', $classe->id)->exists();
    }

    public function scopeActifs(Builder $query): void
    {
        $query->where('enseignants.est_actif', true);
    }
}
