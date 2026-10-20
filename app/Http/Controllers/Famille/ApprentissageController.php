<?php

namespace App\Http\Controllers\Famille;

use App\Http\Controllers\Controller;
use App\Models\Cours;
use App\Models\Devoir;
use App\Models\Inscription;
use App\Services\ApprentissageService;
use App\Services\SuiviEleveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Espace élève : cours publiés de sa classe, devoirs, dépôt de ses travaux.
 */
class ApprentissageController extends Controller
{
    public function __construct(private SuiviEleveService $suivi, private ApprentissageService $service) {}

    public function cours(Request $request): View
    {
        $inscription = $this->inscription($request);

        return view('famille.apprentissage.cours', [
            'eleve' => $request->user()->eleve,
            'cours' => $inscription ? Cours::query()->publies()->with(['affectation.matiere', 'affectation.enseignant.user'])->withCount('ressources')
                ->whereHas('affectation', fn ($q) => $q->where('classe_id', $inscription->classe_id))
                ->latest('publie_at')->get()->groupBy('affectation.matiere.libelle')->sortKeys() : collect(),
        ]);
    }

    public function coursShow(Request $request, Cours $cours): View
    {
        $this->authorize('view', $cours);

        return view('famille.apprentissage.cours-show', [
            'cours' => $cours->load(['affectation.matiere', 'affectation.enseignant.user', 'ressources', 'devoirs' => fn ($q) => $q->publies()]),
        ]);
    }

    public function devoirs(Request $request): View
    {
        $inscription = $this->inscription($request);
        $devoirs = $inscription ? Devoir::query()->publies()->with(['affectation.matiere', 'remises' => fn ($q) => $q->where('inscription_id', $inscription->id)])
            ->whereHas('affectation', fn ($q) => $q->where('classe_id', $inscription->classe_id))
            ->orderBy('date_limite')->get() : collect();

        return view('famille.apprentissage.devoirs', [
            'eleve' => $request->user()->eleve,
            'aRendre' => $devoirs->filter(fn ($d) => $d->remises->isEmpty() && ! $d->estEchu()),
            'autres' => $devoirs->reject(fn ($d) => $d->remises->isEmpty() && ! $d->estEchu())->sortByDesc('date_limite'),
        ]);
    }

    public function devoirShow(Request $request, Devoir $devoir): View
    {
        $this->authorize('view', $devoir);
        $inscription = $this->inscription($request);

        return view('famille.apprentissage.devoir-show', [
            'devoir' => $devoir->load(['affectation.matiere', 'affectation.enseignant.user', 'cours']),
            // Uniquement SA remise : jamais celle d'un camarade.
            'remise' => $inscription ? $devoir->remises()->where('inscription_id', $inscription->id)->first() : null,
            'enLigne' => $devoir->remise_en_ligne && etablissement()->parametre('remise_devoirs_en_ligne', true),
            'tailleMaxKo' => (int) etablissement()->parametre('taille_max_fichier_ko', 5120),
        ]);
    }

    public function remettre(Request $request, Devoir $devoir): RedirectResponse
    {
        $this->authorize('remettre', $devoir);
        $inscription = $this->inscription($request);
        abort_unless($inscription && $inscription->classe_id === $devoir->affectation->classe_id, 403);
        $tailleMax = (int) etablissement()->parametre('taille_max_fichier_ko', 5120);

        $donnees = $request->validate([
            'fichier' => ['nullable', 'file', 'mimes:'.ApprentissageService::MIMES_COPIES, "max:{$tailleMax}"],
            'commentaire' => ['nullable', 'string', 'max:5000'],
        ], [], ['fichier' => 'travail', 'commentaire' => 'réponse']);

        try {
            $remise = $this->service->remettre($devoir, $inscription, $request->file('fichier'), $donnees['commentaire'] ?? null);
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return back()->with('succes', 'Travail déposé le '.$remise->remis_at->format('d/m/Y à H:i').($remise->en_retard ? ' (en retard).' : '.'));
    }

    private function inscription(Request $request): ?Inscription
    {
        $eleve = $request->user()->eleve;
        abort_unless($eleve, 403, 'Aucun dossier élève n\'est lié à votre compte.');

        return $this->suivi->inscriptionCourante($eleve);
    }
}
