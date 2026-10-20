<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RemiseDevoir extends Model
{
    protected $table = 'remises_devoirs';

    protected $fillable = [
        'devoir_id', 'inscription_id', 'commentaire', 'fichier_chemin', 'fichier_nom', 'fichier_mime',
        'fichier_taille', 'remis_at', 'en_retard', 'note', 'correction', 'corrige_par', 'corrige_at',
    ];

    protected function casts(): array
    {
        return [
            'remis_at' => 'datetime',
            'en_retard' => 'boolean',
            'note' => 'decimal:2',
            'corrige_at' => 'datetime',
        ];
    }

    public function devoir(): BelongsTo
    {
        return $this->belongsTo(Devoir::class);
    }

    public function inscription(): BelongsTo
    {
        return $this->belongsTo(Inscription::class);
    }

    public function correcteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corrige_par');
    }

    public function estCorrigee(): bool
    {
        return $this->corrige_at !== null;
    }
}
