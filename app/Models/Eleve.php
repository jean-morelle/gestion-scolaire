<?php

namespace App\Models;

use App\Enums\Sexe;
use App\Enums\StatutEleve;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Eleve extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'matricule', 'matricule_national', 'nom', 'prenoms', 'sexe', 'date_naissance',
        'lieu_naissance', 'nationalite', 'adresse', 'photo_chemin', 'statut', 'observations',
    ];

    protected function casts(): array
    {
        return [
            'sexe' => Sexe::class,
            'date_naissance' => 'date',
            'statut' => StatutEleve::class,
        ];
    }

    protected function nomComplet(): Attribute
    {
        return Attribute::get(fn () => mb_strtoupper($this->nom).' '.$this->prenoms);
    }

    public function responsables(): BelongsToMany
    {
        return $this->belongsToMany(Responsable::class)
            ->withPivot(['lien', 'est_contact_principal', 'est_payeur'])
            ->withTimestamps();
    }

    public function inscriptions(): HasMany
    {
        return $this->hasMany(Inscription::class);
    }

    public function inscriptionPour(AnneeScolaire $annee): ?Inscription
    {
        return $this->inscriptions()->where('annee_scolaire_id', $annee->id)->first();
    }

    /**
     * Inscription de l'année scolaire active, s'il y en a une.
     */
    public function inscriptionActive(): ?Inscription
    {
        $annee = AnneeScolaire::active();

        return $annee ? $this->inscriptionPour($annee) : null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(DocumentEleve::class)->latest();
    }

    /**
     * Pièces ou informations manquantes du dossier (tableau de bord du secrétariat).
     *
     * @return list<string>
     */
    public function elementsManquants(): array
    {
        $manquants = [];
        if (! $this->photo_chemin) {
            $manquants[] = 'Photo';
        }
        if (! $this->lieu_naissance) {
            $manquants[] = 'Lieu de naissance';
        }
        if ($this->responsables->isEmpty()) {
            $manquants[] = 'Responsable légal';
        }
        if ($this->documents->isEmpty()) {
            $manquants[] = 'Pièces du dossier';
        }

        return $manquants;
    }
}
