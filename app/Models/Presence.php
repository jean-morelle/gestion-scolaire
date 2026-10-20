<?php

namespace App\Models;

use App\Enums\StatutPresence;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Presence extends Model
{
    use HasFactory;

    protected $fillable = ['appel_id', 'inscription_id', 'statut', 'minutes_retard', 'remarque'];

    protected function casts(): array
    {
        return [
            'statut' => StatutPresence::class,
        ];
    }

    public function appel(): BelongsTo
    {
        return $this->belongsTo(Appel::class);
    }

    public function inscription(): BelongsTo
    {
        return $this->belongsTo(Inscription::class);
    }

    public function justificatifs(): BelongsToMany
    {
        return $this->belongsToMany(Justificatif::class, 'justificatif_presence');
    }
}
