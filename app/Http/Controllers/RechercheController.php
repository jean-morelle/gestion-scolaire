<?php

namespace App\Http\Controllers;

use App\Models\Classe;
use App\Models\Eleve;
use App\Models\Enseignant;
use App\Models\Paiement;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Recherche globale de la barre du haut. Chaque catégorie n'est interrogée que si l'utilisateur a le droit
 * d'ouvrir les fiches correspondantes : la recherche ne révèle rien de plus que la navigation.
 */
class RechercheController extends Controller
{
    public function __invoke(Request $request): View|JsonResponse
    {
        $q = trim((string) $request->input('q'));
        $resultats = mb_strlen($q) >= 2 ? $this->chercher($request, $q, $request->wantsJson() ? 5 : 25) : [];

        if ($request->wantsJson()) {
            return response()->json(['resultats' => $resultats]);
        }

        return view('recherche', ['q' => $q, 'resultats' => $resultats]);
    }

    /**
     * Les catégories que l'utilisateur peut interroger (sert aussi à afficher ou non la barre de recherche).
     */
    public static function disponible(User $user): bool
    {
        return $user->can('eleves.consulter') || $user->can('enseignants.gerer') || $user->can('classes.gerer') || $user->can('finances.consulter');
    }

    /**
     * @return list<array{categorie: string, titre: string, detail: string, url: string}>
     */
    private function chercher(Request $request, string $q, int $limite): array
    {
        $user = $request->user();
        $mots = preg_split('/\s+/', $q);
        $resultats = [];

        if ($user->can('eleves.consulter')) {
            $eleves = Eleve::query()->with(['inscriptions' => fn ($i) => $i->whereHas('anneeScolaire', fn ($a) => $a->where('est_active', true))->with('classe')])
                ->where(function (Builder $w) use ($mots) {
                    foreach ($mots as $mot) {
                        $w->where(fn (Builder $m) => $m->where('nom', 'like', "%{$mot}%")->orWhere('prenoms', 'like', "%{$mot}%")->orWhere('matricule', 'like', "%{$mot}%"));
                    }
                })->orderBy('nom')->limit($limite)->get();
            foreach ($eleves as $e) {
                $resultats[] = ['categorie' => 'Élèves', 'titre' => $e->nom_complet,
                    'detail' => $e->matricule.' · '.($e->inscriptions->first()?->classe->nom ?? 'non inscrit'), 'url' => route('eleves.show', $e)];
            }
        }

        if ($user->can('enseignants.gerer')) {
            $enseignants = Enseignant::query()->with('user')->whereHas('user', fn (Builder $u) => $u->where(function (Builder $w) use ($mots) {
                foreach ($mots as $mot) {
                    $w->where('name', 'like', "%{$mot}%");
                }
            }))->limit($limite)->get();
            foreach ($enseignants as $e) {
                $resultats[] = ['categorie' => 'Enseignants', 'titre' => $e->user->name, 'detail' => $e->specialite ?? 'Enseignant', 'url' => route('enseignants.show', $e)];
            }
        }

        if ($user->can('classes.gerer') || $user->can('eleves.consulter')) {
            foreach (Classe::query()->deLAnneeActive()->with('niveau')->where('classes.nom', 'like', "%{$q}%")->limit($limite)->get() as $c) {
                $resultats[] = ['categorie' => 'Classes', 'titre' => $c->nom, 'detail' => $c->niveau->libelle, 'url' => route('classes.show', $c)];
            }
        }

        if ($user->can('finances.consulter') && preg_match('/^(rec-?)?[\d-]{3,}$/i', $q)) {
            foreach (Paiement::query()->with('inscription.eleve')->where('numero_recu', 'like', '%'.ltrim(strtoupper($q), 'REC-').'%')->limit($limite)->get() as $p) {
                $resultats[] = ['categorie' => 'Reçus', 'titre' => $p->numero_recu, 'detail' => $p->inscription->eleve->nom_complet.' · '.montant($p->montant), 'url' => route('finances.show', $p->inscription)];
            }
        }

        return $resultats;
    }
}
