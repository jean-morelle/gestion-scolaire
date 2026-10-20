<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentEleve extends Model
{
    protected $table = 'documents_eleves';

    protected $fillable = ['eleve_id', 'libelle', 'fichier_chemin', 'fichier_nom', 'fichier_mime', 'fichier_taille', 'ajoute_par'];

    public function eleve(): BelongsTo
    {
        return $this->belongsTo(Eleve::class);
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ajoute_par');
    }
}
