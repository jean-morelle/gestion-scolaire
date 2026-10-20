<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Appreciation extends Model
{
    protected $fillable = ['inscription_id', 'periode_id', 'appreciation_generale', 'mention', 'saisie_par'];

    public function inscription(): BelongsTo
    {
        return $this->belongsTo(Inscription::class);
    }

    public function periode(): BelongsTo
    {
        return $this->belongsTo(Periode::class);
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'saisie_par');
    }
}
