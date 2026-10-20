<?php

namespace App\Http\Controllers\Scolarite;

use App\Enums\LienParente;
use App\Enums\StatutAnnee;
use App\Enums\StatutEleve;
use App\Enums\StatutInscription;
use App\Http\Controllers\Controller;
use App\Http\Requests\Scolarite\EleveRequest;
use App\Http\Requests\Scolarite\ResponsableRequest;
use App\Enums\StatutPresence;
use App\Models\Bulletin;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\GrilleFrais;
use App\Models\Presence;
use App\Services\AppelService;
use App\Services\EleveService;
use App\Services\InscriptionService;
use App\Services\SoldeService;
use App\Support\ExportCsv;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class EleveController extends Controller
{
    public function __construct(private EleveService $eleves, private InscriptionService $inscriptions) {}

    public function index(Request $request): View|Response
    {
        $this->authorize('viewAny', Eleve::class);
        $annee = annee_active();

        $requete = Eleve::query()
            ->with(['inscriptions' => fn ($q) => $q->where('annee_scolaire_id', $annee?->id)->with('classe.niveau'), 'responsables'])
            // Chaque mot doit se retrouver dans le nom, les prénoms ou un matricule (« KOFFI Ama »).
            ->when($request->filled('q'), function (Builder $q) use ($request) {
                foreach (preg_split('/\s+/', trim($request->string('q'))) as $mot) {
                    $terme = '%'.$mot.'%';
                    $q->where(fn (Builder $w) => $w->where('nom', 'like', $terme)->orWhere('prenoms', 'like', $terme)
                        ->orWhere('matricule', 'like', $terme)->orWhere('matricule_national', 'like', $terme));
                }
            })
            ->when($request->filled('classe'), fn (Builder $q) => $q->whereHas('inscriptions',
                fn (Builder $i) => $i->where('classe_id', $request->integer('classe'))->where('statut', StatutInscription::Inscrit)))
            ->when($request->input('inscription') === 'non_inscrit' && $annee, fn (Builder $q) => $q->whereDoesntHave('inscriptions',
                fn (Builder $i) => $i->where('annee_scolaire_id', $annee->id)))
            ->when($request->filled('sexe'), fn (Builder $q) => $q->where('sexe', $request->input('sexe')))
            ->where('statut', $request->input('statut', StatutEleve::Actif->value) === StatutEleve::Archive->value ? StatutEleve::Archive : StatutEleve::Actif)
            ->orderBy('nom')->orderBy('prenoms');

        if ($request->query('format') === 'csv') {
            return ExportCsv::telecharger('eleves.csv',
                ['Matricule', 'Matricule national', 'Nom', 'Prénoms', 'Sexe', 'Date de naissance', 'Lieu de naissance', 'Classe', 'Responsable', 'Téléphone'],
                $requete->lazy()->map(function (Eleve $e) {
                    $r = $e->responsables->sortByDesc('pivot.est_contact_principal')->first();

                    return [$e->matricule, $e->matricule_national, $e->nom, $e->prenoms, $e->sexe->value, $e->date_naissance,
                        $e->lieu_naissance, $e->inscriptions->first()?->classe->nom, $r?->nom_complet, $r?->telephone];
                }));
        }

        return view('scolarite.eleves.index', [
            'eleves' => $requete->paginate(25)->withQueryString(),
            'annee' => $annee,
            'classes' => $this->classesOuvertes(true),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Eleve::class);

        return view('scolarite.eleves.form', [
            'eleve' => new Eleve(['nationalite' => null]),
            'classes' => $this->classesOuvertes(),
            'categories' => $this->categoriesFrais(),
        ]);
    }

    public function store(EleveRequest $request): RedirectResponse
    {
        $valides = $request->validated();

        try {
            $eleve = DB::transaction(function () use ($request, $valides) {
                $eleve = $this->eleves->creer($request->identite(), ResponsableRequest::responsable($valides['responsable']),
                    ['lien' => $valides['responsable']['lien']]);

                if (! empty($valides['classe_id'])) {
                    $this->inscriptions->inscrire($eleve, Classe::findOrFail($valides['classe_id']), [
                        'date_inscription' => $valides['date_inscription'],
                        'categorie_frais' => $valides['categorie_frais'] ?? null,
                        'redoublant' => $request->boolean('redoublant'),
                    ]);
                }
                if ($request->hasFile('photo')) {
                    $this->eleves->enregistrerPhoto($eleve, $request->file('photo'));
                }

                return $eleve;
            });
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return redirect()->route('eleves.show', $eleve)
            ->with('succes', "Le dossier de {$eleve->nom_complet} a été créé (matricule {$eleve->matricule}).")
            ->with($this->inscriptions->avertissement ? ['avertissement' => $this->inscriptions->avertissement] : []);
    }

    public function show(Eleve $eleve, AppelService $appels): View
    {
        $this->authorize('voirDossier', $eleve);

        $eleve->load([
            'responsables.user',
            'documents.auteur',
            'inscriptions' => fn ($q) => $q->with(['anneeScolaire', 'classe.niveau', 'changementsClasse.ancienneClasse', 'changementsClasse.nouvelleClasse'])
                ->join('annees_scolaires', 'annees_scolaires.id', '=', 'inscriptions.annee_scolaire_id')
                ->orderByDesc('annees_scolaires.date_debut')->select('inscriptions.*'),
        ]);
        $classes = $this->classesOuvertes();
        $courante = $eleve->inscriptions->first(fn ($i) => $i->anneeScolaire->est_active);
        $assiduite = $courante ? $courante->anneeScolaire->periodes->map(fn ($p) => ['periode' => $p] + $appels->totaux($courante, $p)) : collect();

        $user = request()->user();

        return view('scolarite.eleves.show', [
            'assiduite' => $assiduite,
            'courante' => $courante,
            // Onglets : chacun n'est alimenté que si l'utilisateur a le droit correspondant.
            'bulletins' => $user->canAny(['bulletins.consulter', 'bulletins.publier'])
                ? Bulletin::query()->with(['periode.anneeScolaire', 'publication', 'inscription.classe'])->whereIn('inscription_id', $eleve->inscriptions->pluck('id'))
                    ->get()->sortByDesc(fn ($b) => [$b->periode->anneeScolaire->date_debut->timestamp, $b->periode->ordre])->values()
                : null,
            'evenements' => $courante ? Presence::query()->with(['appel', 'justificatifs'])->where('inscription_id', $courante->id)
                ->where('statut', '!=', StatutPresence::Present)->join('appels', 'appels.id', '=', 'presences.appel_id')
                ->orderByDesc('appels.date_appel')->select('presences.*')->limit(20)->get() : collect(),
            'finances' => $courante && $user->canAny(['finances.consulter', 'paiements.enregistrer'])
                ? app(SoldeService::class)->soldes([$courante->id])[$courante->id] : null,
            'eleve' => $eleve,
            'liens' => LienParente::options(),
            'classes' => $classes,
            // Années ouvertes où l'élève n'est pas encore inscrit (inscription / réinscription).
            'classesDisponibles' => $classes->reject(fn ($c) => $eleve->inscriptions->contains('annee_scolaire_id', $c->annee_scolaire_id)),
            'categories' => $this->categoriesFrais(),
            'tailleMaxKo' => (int) etablissement()->parametre('taille_max_fichier_ko', 5120),
        ]);
    }

    public function edit(Eleve $eleve): View
    {
        $this->authorize('update', $eleve);

        return view('scolarite.eleves.form', ['eleve' => $eleve, 'classes' => collect(), 'categories' => []]);
    }

    public function update(EleveRequest $request, Eleve $eleve): RedirectResponse
    {
        $this->eleves->modifier($eleve, $request->identite());
        if ($request->hasFile('photo')) {
            $this->eleves->enregistrerPhoto($eleve, $request->file('photo'));
        }

        return redirect()->route('eleves.show', $eleve)->with('succes', 'Le dossier a été mis à jour.');
    }

    public function archiver(Eleve $eleve): RedirectResponse
    {
        $this->authorize('update', $eleve);
        if ($eleve->inscriptionActive()?->estActive()) {
            return back()->with('erreur', 'Cet élève est inscrit pour l\'année en cours : enregistrez d\'abord son départ.');
        }
        $this->eleves->changerStatut($eleve, StatutEleve::Archive);

        return back()->with('succes', 'Le dossier a été archivé.');
    }

    public function desarchiver(Eleve $eleve): RedirectResponse
    {
        $this->authorize('update', $eleve);
        $this->eleves->changerStatut($eleve, StatutEleve::Actif);

        return back()->with('succes', 'Le dossier est de nouveau actif.');
    }

    public function creerCompte(Eleve $eleve): RedirectResponse
    {
        $this->authorize('update', $eleve);

        try {
            $motDePasse = $this->eleves->creerCompteEleve($eleve);
        } catch (ValidationException $e) {
            return back()->with('erreur', collect($e->errors())->flatten()->first());
        }

        return back()->with('succes', "Compte élève créé. Identifiant : {$eleve->matricule} — mot de passe temporaire : {$motDePasse} (à communiquer une seule fois).");
    }

    public function destroy(Eleve $eleve): RedirectResponse
    {
        $this->authorize('delete', $eleve);

        try {
            $this->eleves->supprimer($eleve);
        } catch (ValidationException $e) {
            return back()->with('erreur', collect($e->errors())->flatten()->first());
        }

        return redirect()->route('eleves.index')->with('succes', 'Le dossier a été supprimé.');
    }

    /**
     * Photo servie depuis le stockage privé, après contrôle d'accès.
     */
    public function photo(Eleve $eleve): Response
    {
        $this->authorize('view', $eleve);
        abort_unless($eleve->photo_chemin && Storage::disk('local')->exists($eleve->photo_chemin), 404);

        return Storage::disk('local')->response($eleve->photo_chemin, null, ['Cache-Control' => 'private, max-age=600']);
    }

    /**
     * Classes des années non clôturées, libellées « 6e A (2026-2027) ».
     *
     * @return \Illuminate\Support\Collection<int, Classe>
     */
    private function classesOuvertes(bool $anneeActiveSeulement = false)
    {
        return Classe::query()
            ->with('anneeScolaire')
            ->whereHas('anneeScolaire', fn (Builder $q) => $anneeActiveSeulement
                ? $q->where('est_active', true)
                : $q->where('statut', '!=', StatutAnnee::Cloturee))
            ->join('niveaux', 'niveaux.id', '=', 'classes.niveau_id')
            ->join('annees_scolaires as a', 'a.id', '=', 'classes.annee_scolaire_id')
            ->orderByDesc('a.est_active')->orderBy('a.date_debut')->orderBy('niveaux.ordre')->orderBy('classes.nom')
            ->select('classes.*')
            ->get();
    }

    /**
     * @return list<string>
     */
    private function categoriesFrais(): array
    {
        return GrilleFrais::query()->where('categorie', '!=', '')->distinct()->orderBy('categorie')->pluck('categorie')->all();
    }
}
