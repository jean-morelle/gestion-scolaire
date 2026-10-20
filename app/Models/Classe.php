<?php

namespace App\Models;

use App\Enums\StatutInscription;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Classe extends Model
{
    use HasFactory;

    protected $fillable = ['annee_scolaire_id', 'niveau_id', 'nom', 'serie', 'capacite', 'professeur_principal_id'];

    public function anneeScolaire(): BelongsTo
    {
        return $this->belongsTo(AnneeScolaire::class);
    }

    public function niveau(): BelongsTo
    {
        return $this->belongsTo(Niveau::class);
    }

    public function professeurPrincipal(): BelongsTo
    {
        return $this->belongsTo(Enseignant::class, 'professeur_principal_id');
    }

    public function inscriptions(): HasMany
    {
        return $this->hasMany(Inscription::class);
    }

    /**
     * Élèves actuellement présents dans la classe (hors départs).
     */
    public function inscriptionsActives(): HasMany
    {
        return $this->inscriptions()->where('statut', StatutInscription::Inscrit);
    }

    public function affectations(): HasMany
    {
        return $this->hasMany(Affectation::class)->orderBy('ordre_bulletin');
    }

    public function appels(): HasMany
    {
        return $this->hasMany(Appel::class);
    }

    public function publications(): HasMany
    {
        return $this->hasMany(Publication::class);
    }

    public function estPublieePour(Periode $periode): bool
    {
        return $this->publications()->enVigueur()->where('periode_id', $periode->id)->exists();
    }

    /**
     * Classes de l'année scolaire active, triées par niveau puis par nom.
     */
    public function scopeDeLAnneeActive(Builder $query): void
    {
        $query->whereHas('anneeScolaire', fn (Builder $q) => $q->where('est_active', true))
            ->join('niveaux', 'niveaux.id', '=', 'classes.niveau_id')
            ->orderBy('niveaux.ordre')->orderBy('classes.nom')
            ->select('classes.*');
    }
}
