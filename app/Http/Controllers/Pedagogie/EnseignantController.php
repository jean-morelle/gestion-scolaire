<?php

namespace App\Http\Controllers\Pedagogie;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pedagogie\EnseignantRequest;
use App\Models\Enseignant;
use App\Models\Matiere;
use App\Services\CompteService;
use App\Services\EmploiDuTempsService;
use App\Services\JournalService;
use App\Support\ExportCsv;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class EnseignantController extends Controller
{
    public function __construct(private CompteService $comptes, private JournalService $journal) {}

    public function index(Request $request): View|Response
    {
        $requete = Enseignant::query()
            ->with(['user', 'matieres'])
            ->withCount(['affectationsActives as nb_affectations'])
            ->join('users', 'users.id', '=', 'enseignants.user_id')
            ->when($request->filled('q'), function (Builder $q) use ($request) {
                $terme = '%'.trim($request->string('q')).'%';
                $q->where(fn (Builder $w) => $w->where('users.name', 'like', $terme)->orWhere('users.telephone', 'like', $terme)
                    ->orWhere('users.email', 'like', $terme)->orWhere('enseignants.matricule', 'like', $terme)->orWhere('enseignants.specialite', 'like', $terme));
            })
            ->when($request->filled('matiere'), fn (Builder $q) => $q->whereHas('matieres', fn (Builder $m) => $m->whereKey($request->integer('matiere'))))
            ->when($request->input('statut', 'actif') !== 'tous', fn (Builder $q) => $q->where('enseignants.est_actif', $request->input('statut', 'actif') === 'actif'))
            ->orderBy('users.name')
            ->select('enseignants.*');

        if ($request->query('format') === 'csv') {
            return ExportCsv::telecharger('enseignants.csv', ['Matricule', 'Nom', 'Téléphone', 'E-mail', 'Spécialité', 'Matières', 'Affectations', 'Statut'],
                $requete->get()->map(fn (Enseignant $e) => [$e->matricule, $e->user->name, $e->user->telephone, $e->user->email, $e->specialite,
                    $e->matieres->pluck('libelle')->join(', '), $e->nb_affectations, $e->est_actif ? 'Actif' : 'Inactif']));
        }

        return view('pedagogie.enseignants.index', [
            'enseignants' => $requete->paginate(25)->withQueryString(),
            'matieres' => Matiere::query()->orderBy('libelle')->pluck('libelle', 'id'),
        ]);
    }

    public function create(): View
    {
        return view('pedagogie.enseignants.form', [
            'enseignant' => new Enseignant(['est_actif' => true]),
            'matieres' => Matiere::query()->orderBy('libelle')->get(),
        ]);
    }

    /**
     * Crée le compte de connexion (rôle enseignant, mot de passe temporaire) et la fiche.
     */
    public function store(EnseignantRequest $request): RedirectResponse
    {
        $donnees = $request->validated();

        [$enseignant, $motDePasse] = DB::transaction(function () use ($donnees, $request) {
            [$user, $motDePasse] = $this->comptes->creer($donnees['name'], $donnees['email'], $donnees['telephone'],
                [Role::Enseignant->value], $request->boolean('est_actif', true));
            $enseignant = $user->enseignant()->firstOrFail();
            $enseignant->update($this->fiche($donnees) + ['est_actif' => $request->boolean('est_actif', true)]);
            $enseignant->matieres()->sync($donnees['matieres'] ?? []);
            $this->journal->enregistrer('enseignant.cree', $enseignant, "Fiche enseignant de {$user->name} créée");

            return [$enseignant, $motDePasse];
        });

        return redirect()->route('enseignants.show', $enseignant)
            ->with('succes', "Enseignant enregistré. Identifiant : {$enseignant->user->identifiant()} — mot de passe temporaire : {$motDePasse} (à communiquer une seule fois).");
    }

    public function show(Enseignant $enseignant): View
    {
        $enseignant->load(['user', 'matieres', 'classesPrincipales' => fn ($q) => $q->whereHas('anneeScolaire', fn ($a) => $a->where('est_active', true))]);
        $affectations = $enseignant->affectationsActives()
            ->with(['classe.niveau', 'matiere', 'creneaux'])
            ->withCount(['evaluations'])
            ->get()
            ->sortBy(fn ($a) => [$a->classe->niveau->ordre, $a->classe->nom, $a->matiere->libelle])
            ->values();
        $creneaux = $affectations->flatMap(fn ($a) => $a->creneaux->each->setRelation('affectation', $a));

        return view('pedagogie.enseignants.show', [
            'enseignant' => $enseignant,
            'affectations' => $affectations,
            'jours' => EmploiDuTempsService::parJour($creneaux),
            'volume' => EmploiDuTempsService::volumeHoraire($creneaux),
        ]);
    }

    public function edit(Enseignant $enseignant): View
    {
        return view('pedagogie.enseignants.form', [
            'enseignant' => $enseignant->load(['user', 'matieres']),
            'matieres' => Matiere::query()->orderBy('libelle')->get(),
        ]);
    }

    public function update(EnseignantRequest $request, Enseignant $enseignant): RedirectResponse
    {
        $donnees = $request->validated();

        DB::transaction(function () use ($donnees, $request, $enseignant) {
            // Le compte n'est désactivé que par l'administration : ici seule la fiche change de statut.
            $this->comptes->modifier($enseignant->user, [
                'name' => $donnees['name'], 'email' => $donnees['email'], 'telephone' => $donnees['telephone'],
            ]);

            $avant = $enseignant->only(['matricule', 'specialite', 'diplome', 'est_actif']) + ['matieres' => $enseignant->matieres->pluck('libelle')->all()];
            $enseignant->update($this->fiche($donnees) + ['est_actif' => $request->boolean('est_actif')]);
            $enseignant->matieres()->sync($donnees['matieres'] ?? []);
            $apres = $enseignant->only(['matricule', 'specialite', 'diplome', 'est_actif']) + ['matieres' => $enseignant->matieres()->pluck('libelle')->all()];

            if ($avant != $apres) {
                $this->journal->enregistrer('enseignant.modifie', $enseignant, "Fiche enseignant de {$enseignant->user->name} modifiée", $avant, $apres);
            }
        });

        $message = 'La fiche a été mise à jour.';
        if (! $enseignant->est_actif && $enseignant->affectationsActives()->exists()) {
            return redirect()->route('enseignants.show', $enseignant)->with('succes', $message)
                ->with('avertissement', 'Cet enseignant est inactif mais garde des affectations cette année : pensez à le remplacer.');
        }

        return redirect()->route('enseignants.show', $enseignant)->with('succes', $message);
    }

    /**
     * @param  array<string, mixed>  $donnees
     * @return array<string, mixed>
     */
    private function fiche(array $donnees): array
    {
        return [
            'matricule' => $donnees['matricule'] ?? null,
            'specialite' => $donnees['specialite'] ?? null,
            'diplome' => $donnees['diplome'] ?? null,
            'date_embauche' => $donnees['date_embauche'] ?? null,
        ];
    }
}
