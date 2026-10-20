<?php

namespace App\Services;

use App\Enums\CibleAnnonce;
use App\Enums\Role;
use App\Enums\StatutInscription;
use App\Models\Annonce;
use App\Models\Inscription;
use App\Models\User;
use App\Notifications\AnnoncePubliee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Annonces de l'établissement. Les destinataires notifiés sont exactement ceux qui peuvent la lire
 * (voir Annonce::scopeVisiblesPar) ; une annonce programmée est notifiée à sa date de publication.
 */
class AnnonceService
{
    public function __construct(private JournalService $journal) {}

    public function publier(array $donnees, User $auteur): Annonce
    {
        $annonce = Annonce::create($donnees + ['auteur_id' => $auteur->id, 'publiee_at' => $donnees['publiee_at'] ?? now()]);
        $this->journal->enregistrer('annonce.publiee', $annonce, "Annonce « {$annonce->titre} » ({$annonce->cible->libelle()})");
        $this->diffuser($annonce);

        return $annonce;
    }

    /**
     * Envoie les notifications si la date de publication est atteinte et qu'elles ne sont pas déjà parties.
     *
     * @return int nombre de destinataires notifiés
     */
    public function diffuser(Annonce $annonce): int
    {
        if ($annonce->notifiee_at || $annonce->publiee_at->isFuture()) {
            return 0;
        }
        $destinataires = $this->destinataires($annonce)->reject(fn (User $u) => $u->id === $annonce->auteur_id);
        foreach ($destinataires as $user) {
            $user->notify(new AnnoncePubliee($annonce));
        }
        $annonce->forceFill(['notifiee_at' => now()])->save();

        return $destinataires->count();
    }

    /**
     * Annonces programmées dont la date est arrivée (tâche planifiée).
     */
    public function diffuserEnAttente(): int
    {
        $total = 0;
        Annonce::query()->whereNull('notifiee_at')->where('publiee_at', '<=', now())
            ->where(fn (Builder $q) => $q->whereNull('expire_le')->orWhereDate('expire_le', '>=', today()))
            ->each(function (Annonce $annonce) use (&$total) {
                $total += $this->diffuser($annonce);
            });

        return $total;
    }

    /**
     * @return Collection<int, User>
     */
    public function destinataires(Annonce $annonce): Collection
    {
        $actifs = fn () => User::query()->where('est_actif', true);
        $personnel = fn () => $actifs()->role(array_map(fn (Role $r) => $r->value, Role::personnel()))->get();

        // Familles : limitées à la classe le cas échéant (via les inscriptions en cours).
        $inscriptions = $annonce->classe_id
            ? Inscription::query()->where('classe_id', $annonce->classe_id)->where('statut', StatutInscription::Inscrit)->with(['eleve.user', 'eleve.responsables.user'])->get()
            : null;
        $parents = fn () => $inscriptions
            ? $inscriptions->flatMap(fn ($i) => $i->eleve->responsables->pluck('user'))->filter(fn ($u) => $u?->est_actif && $u->aRole(Role::Parent))
            : $actifs()->role(Role::Parent->value)->get();
        $eleves = fn () => $inscriptions
            ? $inscriptions->map(fn ($i) => $i->eleve->user)->filter(fn ($u) => $u?->est_actif)
            : $actifs()->role(Role::Eleve->value)->get();

        return (match ($annonce->cible) {
            CibleAnnonce::Personnel => $personnel(),
            CibleAnnonce::Parents => $parents(),
            CibleAnnonce::Eleves => $eleves(),
            CibleAnnonce::Tous => $personnel()->concat($parents())->concat($eleves()),
        })->filter()->unique('id')->values();
    }
}
