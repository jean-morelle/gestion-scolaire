<?php

namespace App\Models;

use App\Enums\Creneau;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Appel extends Model
{
    use HasFactory;

    protected $fillable = ['classe_id', 'date_appel', 'creneau', 'saisi_par'];

    protected function casts(): array
    {
        return [
            'date_appel' => 'date',
            'creneau' => Creneau::class,
        ];
    }

    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class);
    }

    public function presences(): HasMany
    {
        return $this->hasMany(Presence::class);
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'saisi_par');
    }
}
