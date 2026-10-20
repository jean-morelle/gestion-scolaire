<?php

namespace App\Http\Controllers\Pedagogie;

use App\Enums\Jour;
use App\Http\Controllers\Controller;
use App\Models\Classe;
use App\Models\EmploiDuTemps;
use App\Models\Enseignant;
use App\Services\EmploiDuTempsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class EmploiDuTempsController extends Controller
{
    public function __construct(private EmploiDuTempsService $service) {}

    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        $gestion = $user->can('emplois_du_temps.gerer') || $user->can('eleves.consulter') || $user->can('enseignants.gerer');
        if (! $gestion) {
            // Un enseignant consulte directement son propre emploi du temps.
            abort_unless($user->enseignant, 403);

            return redirect()->route('emplois-du-temps.enseignant', $user->enseignant);
        }

        $annee = annee_active();

        return view('pedagogie.emplois-du-temps.index', [
            'annee' => $annee,
            'classes' => $annee ? Classe::query()->where('annee_scolaire_id', $annee->id)->with('niveau')
                ->withCount(['affectations as nb_creneaux' => fn ($q) => $q->join('emplois_du_temps', 'emplois_du_temps.affectation_id', '=', 'affectations.id')])
                ->join('niveaux', 'niveaux.id', '=', 'classes.niveau_id')->orderBy('niveaux.ordre')->orderBy('classes.nom')->select('classes.*')->get() : collect(),
            'enseignants' => Enseignant::query()->actifs()->with('user')->get()->sortBy('user.name')->values(),
        ]);
    }

    public function classe(Classe $classe): View
    {
        $this->authorize('voirEmploiDuTemps', $classe);
        $classe->load(['anneeScolaire', 'niveau']);
        $affectations = $classe->affectations()->with(['matiere', 'enseignant.user', 'creneaux'])->get();
        $creneaux = $affectations->flatMap(fn ($a) => $a->creneaux->each->setRelation('affectation', $a->setRelation('classe', $classe)));

        return view('pedagogie.emplois-du-temps.grille', [
            'titre' => 'Emploi du temps — '.$classe->nom,
            'sousTitre' => $classe->anneeScolaire->libelle.' · '.EmploiDuTempsService::volumeHoraire($creneaux).' h par semaine',
            'retour' => route('emplois-du-temps.index'),
            'jours' => EmploiDuTempsService::parJour($creneaux),
            'vue' => 'classe',
            'classe' => $classe,
            'affectations' => $affectations,
            'modifiable' => request()->user()->can('emplois_du_temps.gerer') && ! $classe->anneeScolaire->estCloturee(),
        ]);
    }

    public function enseignant(Request $request, Enseignant $enseignant): View
    {
        $user = $request->user();
        abort_unless($user->can('emplois_du_temps.gerer') || $user->can('enseignants.gerer') || $user->can('eleves.consulter')
            || $user->enseignant?->is($enseignant), 403);

        $enseignant->load('user');
        $affectations = $enseignant->affectationsActives()->with(['classe', 'matiere', 'creneaux'])->get();
        $creneaux = $affectations->flatMap(fn ($a) => $a->creneaux->each->setRelation('affectation', $a));

        return view('pedagogie.emplois-du-temps.grille', [
            'titre' => 'Emploi du temps — '.$enseignant->user->name,
            'sousTitre' => (annee_active()?->libelle ?? '').' · '.EmploiDuTempsService::volumeHoraire($creneaux).' h par semaine',
            'retour' => $user->enseignant?->is($enseignant) && ! $user->can('emplois_du_temps.gerer') ? null : route('emplois-du-temps.index'),
            'jours' => EmploiDuTempsService::parJour($creneaux),
            'vue' => 'enseignant',
            'classe' => null,
            'affectations' => collect(),
            'modifiable' => false,
        ]);
    }

    public function store(Request $request, Classe $classe): RedirectResponse
    {
        $donnees = $request->validate([
            'affectation_id' => ['required', Rule::exists('affectations', 'id')->where('classe_id', $classe->id)],
            'jour' => ['required', Rule::enum(Jour::class)],
            'heure_debut' => ['required', 'date_format:H:i'],
            'heure_fin' => ['required', 'date_format:H:i'],
            'salle' => ['nullable', 'string', 'max:50'],
        ], [], ['affectation_id' => 'matière', 'heure_debut' => 'heure de début', 'heure_fin' => 'heure de fin']);

        try {
            $creneau = $this->service->ajouter($classe->affectations()->findOrFail($donnees['affectation_id']),
                (int) $donnees['jour'], $donnees['heure_debut'], $donnees['heure_fin'], $donnees['salle'] ?? null);
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return back()->with('succes', "Créneau ajouté : {$creneau->jour->libelle()} {$creneau->horaire()}.");
    }

    public function destroy(EmploiDuTemps $creneau): RedirectResponse
    {
        try {
            $this->service->supprimer($creneau);
        } catch (ValidationException $e) {
            return back()->with('erreur', collect($e->errors())->flatten()->first());
        }

        return back()->with('succes', 'Le créneau a été supprimé.');
    }
}
