<?php

namespace App\Models;

use App\Enums\StatutEvaluation;
use App\Enums\TypeEvaluation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Evaluation extends Model
{
    use HasFactory;

    protected $fillable = [
        'affectation_id', 'periode_id', 'libelle', 'type', 'date_evaluation',
        'bareme', 'poids', 'statut', 'validee_par', 'validee_at', 'deverrouillee_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => TypeEvaluation::class,
            'statut' => StatutEvaluation::class,
            'date_evaluation' => 'date',
            'bareme' => 'decimal:2',
            'poids' => 'decimal:2',
            'validee_at' => 'datetime',
            'deverrouillee_at' => 'datetime',
        ];
    }

    public function affectation(): BelongsTo
    {
        return $this->belongsTo(Affectation::class);
    }

    public function periode(): BelongsTo
    {
        return $this->belongsTo(Periode::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class);
    }

    public function validateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validee_par');
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function estValidee(): bool
    {
        return $this->statut === StatutEvaluation::Validee;
    }
}
