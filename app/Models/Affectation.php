<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Matière enseignée dans une classe : enseignant titulaire et coefficient.
 */
class Affectation extends Model
{
    use HasFactory;

    protected $fillable = ['classe_id', 'matiere_id', 'enseignant_id', 'coefficient', 'ordre_bulletin'];

    protected function casts(): array
    {
        return [
            'coefficient' => 'decimal:2',
        ];
    }

    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class);
    }

    public function matiere(): BelongsTo
    {
        return $this->belongsTo(Matiere::class);
    }

    public function enseignant(): BelongsTo
    {
        return $this->belongsTo(Enseignant::class);
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class);
    }

    public function creneaux(): HasMany
    {
        return $this->hasMany(EmploiDuTemps::class)->orderBy('jour')->orderBy('heure_debut');
    }

    public function cours(): HasMany
    {
        return $this->hasMany(Cours::class);
    }

    public function devoirs(): HasMany
    {
        return $this->hasMany(Devoir::class);
    }

    /**
     * Libellé court « 6e A — Mathématiques ».
     */
    public function libelle(): string
    {
        return $this->classe->nom.' — '.$this->matiere->libelle;
    }
}
