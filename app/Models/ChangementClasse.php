<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChangementClasse extends Model
{
    protected $table = 'changements_classe';

    protected $fillable = ['inscription_id', 'ancienne_classe_id', 'nouvelle_classe_id', 'date_effet', 'motif', 'user_id'];

    protected function casts(): array
    {
        return [
            'date_effet' => 'date',
        ];
    }

    public function inscription(): BelongsTo
    {
        return $this->belongsTo(Inscription::class);
    }

    public function ancienneClasse(): BelongsTo
    {
        return $this->belongsTo(Classe::class, 'ancienne_classe_id');
    }

    public function nouvelleClasse(): BelongsTo
    {
        return $this->belongsTo(Classe::class, 'nouvelle_classe_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
