<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Responsable légal d'un ou plusieurs élèves. Le compte parent (user) est facultatif.
 */
class Responsable extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'nom', 'prenoms', 'telephone', 'telephone_2', 'email', 'profession', 'adresse'];

    protected function nomComplet(): Attribute
    {
        return Attribute::get(fn () => mb_strtoupper($this->nom).' '.$this->prenoms);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function eleves(): BelongsToMany
    {
        return $this->belongsToMany(Eleve::class)
            ->withPivot(['lien', 'est_contact_principal', 'est_payeur'])
            ->withTimestamps();
    }

    /**
     * Base du contrôle d'accès de l'espace parents (RG-PAR-02).
     */
    public function estResponsableDe(Eleve $eleve): bool
    {
        return $this->eleves()->whereKey($eleve->getKey())->exists();
    }
}
