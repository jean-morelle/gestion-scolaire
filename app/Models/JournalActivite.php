<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * Journal des actions sensibles, en ajout seul (RG-SEC-02).
 */
class JournalActivite extends Model
{
    protected $table = 'journal_activites';

    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'action', 'sujet_type', 'sujet_id', 'description',
        'anciennes_valeurs', 'nouvelles_valeurs', 'ip', 'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'anciennes_valeurs' => 'array',
            'nouvelles_valeurs' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Le journal d\'activité ne peut pas être modifié.'));
        static::deleting(fn () => throw new LogicException('Le journal d\'activité ne peut pas être supprimé.'));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sujet(): MorphTo
    {
        return $this->morphTo();
    }
}
