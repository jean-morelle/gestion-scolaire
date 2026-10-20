<?php

namespace App\Models;

use App\Enums\ModePaiement;
use App\Enums\StatutPaiement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * Transaction d'encaissement. Jamais supprimée ni modifiée : seule l'annulation est permise (RG-FIN-08).
 */
class Paiement extends Model
{
    use HasFactory;

    /**
     * Seules colonnes modifiables après création : celles de l'annulation.
     */
    private const COLONNES_ANNULATION = ['statut', 'annule_par', 'annule_at', 'motif_annulation', 'updated_at'];

    protected $fillable = [
        'numero_recu', 'inscription_id', 'montant', 'date_paiement', 'mode', 'reference_externe',
        'payeur_nom', 'encaisse_par', 'statut', 'annule_par', 'annule_at', 'motif_annulation',
    ];

    protected function casts(): array
    {
        return [
            'montant' => 'integer',
            'date_paiement' => 'date',
            'mode' => ModePaiement::class,
            'statut' => StatutPaiement::class,
            'annule_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (Paiement $paiement) {
            $interdites = array_diff(array_keys($paiement->getDirty()), self::COLONNES_ANNULATION);
            if ($interdites !== []) {
                throw new LogicException('Un paiement ne peut pas être modifié, seulement annulé.');
            }
        });

        static::deleting(fn () => throw new LogicException('Un paiement ne peut pas être supprimé, seulement annulé.'));
    }

    public function scopeValides(Builder $query): void
    {
        $query->where('statut', StatutPaiement::Valide);
    }

    public function inscription(): BelongsTo
    {
        return $this->belongsTo(Inscription::class);
    }

    public function imputations(): HasMany
    {
        return $this->hasMany(Imputation::class);
    }

    public function encaisseur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'encaisse_par');
    }

    public function annulateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'annule_par');
    }

    public function estAnnule(): bool
    {
        return $this->statut === StatutPaiement::Annule;
    }
}
