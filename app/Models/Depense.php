<?php

namespace App\Models;

use App\Enums\ModePaiement;
use App\Enums\StatutDepense;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class Depense extends Model
{
    private const COLONNES_ANNULATION = ['statut', 'annulee_par', 'annulee_at', 'motif_annulation', 'updated_at'];

    protected $fillable = [
        'numero', 'categorie_depense_id', 'annee_scolaire_id', 'libelle', 'montant', 'date_depense', 'mode',
        'beneficiaire', 'reference_piece', 'justificatif_chemin', 'saisi_par', 'statut', 'annulee_par',
        'annulee_at', 'motif_annulation',
    ];

    protected function casts(): array
    {
        return [
            'montant' => 'integer',
            'date_depense' => 'date',
            'mode' => ModePaiement::class,
            'statut' => StatutDepense::class,
            'annulee_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (Depense $depense) {
            if (array_diff(array_keys($depense->getDirty()), self::COLONNES_ANNULATION) !== []) {
                throw new LogicException('Une dépense enregistrée ne peut pas être modifiée, seulement annulée.');
            }
        });
        static::deleting(fn () => throw new LogicException('Une dépense ne peut pas être supprimée, seulement annulée.'));
    }

    public function scopeValides(Builder $query): void
    {
        $query->where('statut', StatutDepense::Valide);
    }

    public function categorie(): BelongsTo
    {
        return $this->belongsTo(CategorieDepense::class, 'categorie_depense_id');
    }

    public function anneeScolaire(): BelongsTo
    {
        return $this->belongsTo(AnneeScolaire::class);
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'saisi_par');
    }

    public function annulateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'annulee_par');
    }

    public function estAnnulee(): bool
    {
        return $this->statut === StatutDepense::Annulee;
    }
}
