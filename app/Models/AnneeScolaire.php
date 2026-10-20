<?php

namespace App\Models;

use App\Enums\StatutAnnee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnneeScolaire extends Model
{
    use HasFactory;

    protected $table = 'annees_scolaires';

    protected $fillable = ['libelle', 'date_debut', 'date_fin', 'est_active', 'statut'];

    protected function casts(): array
    {
        return [
            'date_debut' => 'date',
            'date_fin' => 'date',
            'est_active' => 'boolean',
            'statut' => StatutAnnee::class,
        ];
    }

    public static function active(): ?self
    {
        return static::query()->where('est_active', true)->first();
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('est_active', true);
    }

    public function periodes(): HasMany
    {
        return $this->hasMany(Periode::class)->orderBy('ordre');
    }

    public function classes(): HasMany
    {
        return $this->hasMany(Classe::class);
    }

    public function inscriptions(): HasMany
    {
        return $this->hasMany(Inscription::class);
    }

    public function grillesFrais(): HasMany
    {
        return $this->hasMany(GrilleFrais::class);
    }

    /**
     * Période de l'année qui contient la date (null hors période).
     */
    public function periodePour(\DateTimeInterface|string $date): ?Periode
    {
        $jour = \Carbon\CarbonImmutable::parse($date)->toDateString();

        return $this->periodes()->whereDate('date_debut', '<=', $jour)->whereDate('date_fin', '>=', $jour)->first();
    }

    public function estCloturee(): bool
    {
        return $this->statut === StatutAnnee::Cloturee;
    }
}
