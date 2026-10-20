<?php

namespace App\Http\Controllers;

use App\Enums\CibleAnnonce;
use App\Models\Annonce;
use App\Models\Classe;
use App\Services\AnnonceService;
use App\Services\JournalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AnnonceController extends Controller
{
    public function __construct(private AnnonceService $service) {}

    /**
     * Annonces en cours visibles par l'utilisateur ; les gestionnaires voient aussi programmées et expirées.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $gestion = $user->can('annonces.gerer') && $request->query('vue') === 'gestion';

        $annonces = Annonce::query()->with(['auteur', 'classe'])
            ->when(! $gestion, fn ($q) => $q->enCours()->visiblesPar($user))
            ->latest('publiee_at')->paginate(15)->withQueryString();

        return view('annonces.index', ['annonces' => $annonces, 'gestion' => $gestion]);
    }

    public function show(Request $request, Annonce $annonce): View
    {
        $user = $request->user();
        abort_unless($user->can('annonces.gerer')
            || Annonce::query()->whereKey($annonce->id)->enCours()->visiblesPar($user)->exists(), 403);

        return view('annonces.show', ['annonce' => $annonce->load(['auteur', 'classe'])]);
    }

    public function create(): View
    {
        return view('annonces.form', ['annonce' => new Annonce(['cible' => CibleAnnonce::Tous]), 'classes' => $this->classes()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $annonce = $this->service->publier($this->valider($request), $request->user());

        return redirect()->route('annonces.show', $annonce)->with('succes', $annonce->notifiee_at
            ? 'Annonce publiée et notifiée à ses destinataires.'
            : 'Annonce programmée : elle sera visible et notifiée le '.$annonce->publiee_at->format('d/m/Y à H:i').'.');
    }

    public function edit(Annonce $annonce): View
    {
        return view('annonces.form', ['annonce' => $annonce, 'classes' => $this->classes()]);
    }

    public function update(Request $request, Annonce $annonce, JournalService $journal): RedirectResponse
    {
        $annonce->update($this->valider($request));
        $journal->enregistrer('annonce.modifiee', $annonce, "Annonce « {$annonce->titre} » modifiée");
        // Programmée à une date passée et pas encore notifiée : diffusion immédiate.
        $this->service->diffuser($annonce);

        return redirect()->route('annonces.show', $annonce)->with('succes', 'Annonce modifiée (sans nouvelle notification si elle avait déjà été diffusée).');
    }

    public function destroy(Annonce $annonce, JournalService $journal): RedirectResponse
    {
        $titre = $annonce->titre;
        $annonce->delete();
        $journal->enregistrer('annonce.supprimee', null, "Annonce « {$titre} » supprimée");

        return redirect()->route('annonces.index', ['vue' => 'gestion'])->with('succes', 'Annonce supprimée.');
    }

    private function valider(Request $request): array
    {
        $donnees = $request->validate([
            'titre' => ['required', 'string', 'max:150'],
            'contenu' => ['required', 'string', 'max:10000'],
            'cible' => ['required', Rule::enum(CibleAnnonce::class)],
            'classe_id' => ['nullable', 'exists:classes,id'],
            'publiee_at' => ['nullable', 'date'],
            'expire_le' => ['nullable', 'date', 'after_or_equal:today'],
        ], [], ['cible' => 'destinataires', 'classe_id' => 'classe', 'publiee_at' => 'date de publication', 'expire_le' => 'date d\'expiration']);

        // Une annonce au personnel n'est pas limitée à une classe.
        if ($donnees['cible'] === CibleAnnonce::Personnel->value) {
            $donnees['classe_id'] = null;
        }
        $donnees['publiee_at'] = $donnees['publiee_at'] ?? now();

        return $donnees;
    }

    private function classes()
    {
        return Classe::query()->deLAnneeActive()->get()->pluck('nom', 'id');
    }
}
