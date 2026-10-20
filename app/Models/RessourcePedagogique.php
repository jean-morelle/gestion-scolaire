<?php

namespace App\Models;

use App\Enums\TypeRessource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RessourcePedagogique extends Model
{
    protected $table = 'ressources_pedagogiques';

    protected $fillable = [
        'cours_id', 'type', 'titre', 'contenu', 'url', 'fichier_chemin', 'fichier_nom', 'fichier_mime',
        'fichier_taille', 'ordre',
    ];

    protected function casts(): array
    {
        return [
            'type' => TypeRessource::class,
        ];
    }

    public function cours(): BelongsTo
    {
        return $this->belongsTo(Cours::class);
    }
}
