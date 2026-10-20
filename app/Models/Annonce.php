<?php

namespace App\Models;

use App\Enums\CibleAnnonce;
use App\Enums\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Annonce extends Model
{
    protected $fillable = ['titre', 'contenu', 'cible', 'classe_id', 'publiee_at', 'expire_le', 'notifiee_at', 'auteur_id'];

    protected function casts(): array
    {
        return [
            'cible' => CibleAnnonce::class,
            'publiee_at' => 'datetime',
            'expire_le' => 'date',
            'notifiee_at' => 'datetime',
        ];
    }

    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class);
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auteur_id');
    }

    public function scopeEnCours(Builder $query): void
    {
        $query->where('publiee_at', '<=', now())
            ->where(fn (Builder $q) => $q->whereNull('expire_le')->orWhereDate('expire_le', '>=', today()));
    }

    /**
     * Annonces destinées à un utilisateur selon sa population (personnel, parent, élève) et,
     * pour une annonce limitée à une classe, selon les classes qui le concernent.
     */
    public function scopeVisiblesPar(Builder $query, User $user): void
    {
        $cibles = [CibleAnnonce::Tous];
        if ($user->estPersonnel()) {
            $cibles[] = CibleAnnonce::Personnel;
        }
        if ($user->hasRole(Role::Parent->value)) {
            $cibles[] = CibleAnnonce::Parents;
        }
        if ($user->hasRole(Role::Eleve->value)) {
            $cibles[] = CibleAnnonce::Eleves;
        }

        $query->whereIn('cible', $cibles);

        if (! $user->estPersonnel()) {
            $classes = $user->classesSuivies();
            $query->where(fn (Builder $q) => $q->whereNull('classe_id')->orWhereIn('classe_id', $classes));
        }
    }
}
