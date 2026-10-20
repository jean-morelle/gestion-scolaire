<?php

namespace App\Http\Controllers\Scolarite;

use App\Enums\StatutAnnee;
use App\Enums\StatutInscription;
use App\Enums\TypeInscription;
use App\Http\Controllers\Controller;
use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\Eleve;
use App\Models\Inscription;
use App\Services\InscriptionService;
use App\Support\ExportCsv;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class InscriptionController extends Controller
{
    public function __construct(private InscriptionService $service) {}

    public function index(Request $request): View|Response
    {
        $annees = AnneeScolaire::query()->orderByDesc('date_debut')->get();
        $annee = $annees->firstWhere('id', $request->integer('annee')) ?? $annees->firstWhere('est_active', true) ?? $annees->first();

        $requete = Inscription::query()
            ->where('inscriptions.annee_scolaire_id', $annee?->id)
            ->with(['eleve', 'classe'])
            ->when($request->filled('classe'), fn (Builder $q) => $q->where('classe_id', $request->integer('classe')))
            ->when($request->filled('type'), fn (Builder $q) => $q->where('type', $request->input('type')))
            ->when($request->filled('statut'), fn (Builder $q) => $q->where('inscriptions.statut', $request->input('statut')))
            ->when($request->filled('q'), function (Builder $q) use ($request) {
                foreach (preg_split('/\s+/', trim($request->string('q'))) as $mot) {
                    $q->whereHas('eleve', fn (Builder $e) => $e->where(fn (Builder $w) => $w->where('nom', 'like', "%{$mot}%")
                        ->orWhere('prenoms', 'like', "%{$mot}%")->orWhere('matricule', 'like', "%{$mot}%")));
                }
            })
            ->orderByDesc('date_inscription')->orderByDesc('inscriptions.id');

        if ($request->query('format') === 'csv') {
            return ExportCsv::telecharger("inscriptions-{$annee?->libelle}.csv",
                ['Date', 'Matricule', 'Nom', 'Prénoms', 'Classe', 'Type', 'Redoublant', 'Catégorie', 'Statut', 'Date de sortie'],
                $requete->lazy()->map(fn (Inscription $i) => [$i->date_inscription, $i->eleve->matricule, $i->eleve->nom, $i->eleve->prenoms,
                    $i->classe->nom, $i->type, $i->redoublant, $i->categorie_frais, $i->statut, $i->date_sortie]));
        }

        $stats = $annee ? Inscription::query()->where('annee_scolaire_id', $annee->id)
            ->selectRaw('type, statut, COUNT(*) as total')->groupBy('type', 'statut')->get() : collect();

        return view('scolarite.inscriptions.index', [
            'annees' => $annees,
            'annee' => $annee,
            'inscriptions' => $requete->paginate(25)->withQueryString(),
            'classes' => $annee ? $annee->classes()->orderBy('nom')->pluck('nom', 'id') : collect(),
            'stats' => [
                'inscrits' => $stats->where('statut', StatutInscription::Inscrit)->sum('total'),
                'nouvelles' => $stats->where('type', TypeInscription::Nouvelle)->sum('total'),
                'reinscriptions' => $stats->where('type', TypeInscription::Reinscription)->sum('total'),
                'partis' => $stats->where('statut', StatutInscription::Parti)->sum('total'),
            ],
        ]);
    }

    /**
     * Inscription ou réinscription d'un élève existant, depuis sa fiche.
     */
    public function store(Request $request, Eleve $eleve): RedirectResponse
    {
        $donnees = $request->validate([
            'classe_id' => ['required', $this->regleClasseOuverte()],
            'date_inscription' => ['required', 'date'],
            'categorie_frais' => ['nullable', 'string', 'max:30'],
            'redoublant' => ['boolean'],
        ], ['classe_id.exists' => 'Choisissez une classe d\'une année non clôturée.'], ['classe_id' => 'classe', 'date_inscription' => 'date d\'inscription']);

        return $this->executer(function () use ($eleve, $donnees, $request) {
            $inscription = $this->service->inscrire($eleve, Classe::findOrFail($donnees['classe_id']), [
                'date_inscription' => $donnees['date_inscription'],
                'categorie_frais' => $donnees['categorie_frais'] ?? null,
                'redoublant' => $request->boolean('redoublant'),
            ]);

            return "{$inscription->type->libelle()} enregistrée en {$inscription->classe->nom} ({$inscription->anneeScolaire->libelle}).";
        });
    }

    public function changerClasse(Request $request, Inscription $inscription): RedirectResponse
    {
        $donnees = $request->validate([
            'nouvelle_classe_id' => ['required', 'exists:classes,id'],
            'date_effet' => ['required', 'date'],
            'motif' => ['nullable', 'string', 'max:255'],
        ], [], ['nouvelle_classe_id' => 'nouvelle classe', 'date_effet' => 'date d\'effet']);

        return $this->executer(function () use ($inscription, $donnees) {
            $classe = Classe::findOrFail($donnees['nouvelle_classe_id']);
            $this->service->changerClasse($inscription, $classe, $donnees['date_effet'], $donnees['motif'] ?? null);

            return "{$inscription->eleve->nom_complet} est désormais en {$classe->nom}. Ses notes et présences sont conservées.";
        });
    }

    public function depart(Request $request, Inscription $inscription): RedirectResponse
    {
        $donnees = $request->validate([
            'date_sortie' => ['required', 'date', 'before_or_equal:today'],
            'motif_sortie' => ['required', 'string', 'max:255'],
        ], [], ['date_sortie' => 'date de départ', 'motif_sortie' => 'motif du départ']);

        return $this->executer(function () use ($inscription, $donnees) {
            $this->service->enregistrerDepart($inscription, $donnees['date_sortie'], $donnees['motif_sortie']);

            return "Le départ de {$inscription->eleve->nom_complet} est enregistré. Son historique et sa situation financière sont conservés.";
        });
    }

    public function annulerDepart(Inscription $inscription): RedirectResponse
    {
        return $this->executer(function () use ($inscription) {
            $this->service->annulerDepart($inscription);

            return "{$inscription->eleve->nom_complet} est de nouveau inscrit(e) en {$inscription->classe->nom}.";
        });
    }

    /**
     * Réinscription en lot : élèves d'une classe d'une année précédente vers une classe d'une année ouverte.
     */
    public function reinscriptions(Request $request): View
    {
        $source = $request->filled('source') ? Classe::with('anneeScolaire')->find($request->integer('source')) : null;
        $eleves = collect();
        if ($source) {
            $eleves = $source->inscriptions()->with(['eleve.inscriptions.classe', 'eleve.inscriptions.anneeScolaire'])
                ->join('eleves', 'eleves.id', '=', 'inscriptions.eleve_id')->orderBy('eleves.nom')->orderBy('eleves.prenoms')
                ->select('inscriptions.*')->get();
        }

        $ouvertes = Classe::query()->with('anneeScolaire')->whereHas('anneeScolaire', fn ($q) => $q->where('statut', '!=', StatutAnnee::Cloturee))
            ->join('niveaux', 'niveaux.id', '=', 'classes.niveau_id')->orderBy('niveaux.ordre')->orderBy('classes.nom')->select('classes.*')->get();

        return view('scolarite.inscriptions.reinscriptions', [
            'source' => $source,
            'inscriptionsSource' => $eleves,
            'classesSource' => Classe::query()->with('anneeScolaire')
                ->join('annees_scolaires as a', 'a.id', '=', 'classes.annee_scolaire_id')
                ->join('niveaux', 'niveaux.id', '=', 'classes.niveau_id')
                ->orderByDesc('a.date_debut')->orderBy('niveaux.ordre')->orderBy('classes.nom')->select('classes.*')->get()
                ->groupBy(fn ($c) => $c->anneeScolaire->libelle),
            'classesCible' => $ouvertes->when($source, fn ($c) => $c->where('annee_scolaire_id', '!=', $source->annee_scolaire_id))
                ->groupBy(fn ($c) => $c->anneeScolaire->libelle),
        ]);
    }

    public function storeReinscriptions(Request $request): RedirectResponse
    {
        $donnees = $request->validate([
            'classe_id' => ['required', $this->regleClasseOuverte()],
            'date_inscription' => ['required', 'date'],
            'eleves' => ['required', 'array', 'min:1'],
            'eleves.*' => ['integer', 'exists:eleves,id'],
            'redoublants' => ['array'],
            'redoublants.*' => ['integer'],
        ], ['eleves.required' => 'Cochez au moins un élève à réinscrire.'], ['classe_id' => 'classe de destination']);

        $classe = Classe::findOrFail($donnees['classe_id']);
        $redoublants = array_map('intval', $donnees['redoublants'] ?? []);
        $reussies = 0;
        $echecs = [];

        foreach (Eleve::query()->whereIn('id', $donnees['eleves'])->get() as $eleve) {
            try {
                DB::transaction(fn () => $this->service->inscrire($eleve, $classe, [
                    'date_inscription' => $donnees['date_inscription'],
                    // La catégorie de frais de l'année précédente est reprise.
                    'categorie_frais' => $eleve->inscriptions()->latest('date_inscription')->value('categorie_frais'),
                    'redoublant' => in_array($eleve->id, $redoublants, true),
                ]));
                $reussies++;
            } catch (ValidationException $e) {
                $echecs[] = "{$eleve->nom_complet} : ".collect($e->errors())->flatten()->first();
            }
        }

        $retour = back()->with('succes', "{$reussies} élève(s) inscrit(s) en {$classe->nom}.");
        if ($echecs !== []) {
            $retour->with('avertissement', 'Non traités — '.implode(' ; ', array_slice($echecs, 0, 10)));
        } elseif ($this->service->avertissement) {
            $retour->with('avertissement', $this->service->avertissement);
        }

        return $retour;
    }

    private function regleClasseOuverte(): \Illuminate\Validation\Rules\Exists
    {
        return Rule::exists('classes', 'id')->where(fn ($q) => $q->whereIn('annee_scolaire_id',
            AnneeScolaire::query()->where('statut', '!=', StatutAnnee::Cloturee->value)->select('id')));
    }

    /**
     * @param  callable(): string  $action  renvoie le message de succès
     */
    private function executer(callable $action): RedirectResponse
    {
        try {
            $message = $action();
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        $retour = back()->with('succes', $message);

        return $this->service->avertissement ? $retour->with('avertissement', $this->service->avertissement) : $retour;
    }
}
