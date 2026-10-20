<?php

namespace App\Http\Controllers\Scolarite;

use App\Enums\Sexe;
use App\Enums\StatutAnnee;
use App\Enums\StatutInscription;
use App\Http\Controllers\Controller;
use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\Enseignant;
use App\Models\Niveau;
use App\Services\JournalService;
use App\Support\ExportCsv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClasseController extends Controller
{
    public function __construct(private JournalService $journal) {}

    public function index(Request $request): View
    {
        $annees = AnneeScolaire::query()->orderByDesc('date_debut')->get();
        $annee = $annees->firstWhere('id', $request->integer('annee')) ?? $annees->firstWhere('est_active', true) ?? $annees->first();

        $classes = $annee ? Classe::query()
            ->where('annee_scolaire_id', $annee->id)
            ->with(['niveau', 'professeurPrincipal.user'])
            ->withCount([
                'inscriptionsActives as effectif',
                'inscriptionsActives as filles' => fn ($q) => $q->whereHas('eleve', fn ($e) => $e->where('sexe', Sexe::Feminin)),
            ])
            ->join('niveaux', 'niveaux.id', '=', 'classes.niveau_id')
            ->orderBy('niveaux.ordre')->orderBy('classes.nom')
            ->select('classes.*')
            ->get() : collect();

        return view('scolarite.classes.index', [
            'annees' => $annees,
            'annee' => $annee,
            'classes' => $classes,
            'anneesSource' => $annees->where('id', '!=', $annee?->id),
        ]);
    }

    public function create(Request $request): View
    {
        $classe = new Classe(['annee_scolaire_id' => $request->integer('annee') ?: annee_active()?->id]);

        return view('scolarite.classes.form', $this->donneesFormulaire($classe));
    }

    public function store(Request $request): RedirectResponse
    {
        $classe = Classe::create($this->valider($request));
        $this->journal->enregistrer('classe.creee', $classe, "Classe {$classe->nom} créée ({$classe->anneeScolaire->libelle})");

        return redirect()->route('classes.index', ['annee' => $classe->annee_scolaire_id])->with('succes', "La classe {$classe->nom} a été créée.");
    }

    public function show(Request $request, Classe $classe): View|StreamedResponse
    {
        $classe->load(['niveau', 'anneeScolaire', 'professeurPrincipal.user']);
        // Les coordonnées des familles sont réservées au personnel qui consulte les dossiers.
        $contacts = $request->user()->can('eleves.consulter');
        $inscriptions = $classe->inscriptions()
            ->with($contacts ? ['eleve.responsables'] : ['eleve'])
            ->join('eleves', 'eleves.id', '=', 'inscriptions.eleve_id')
            ->orderBy('eleves.nom')->orderBy('eleves.prenoms')
            ->select('inscriptions.*')
            ->get();

        if ($request->query('format') === 'csv') {
            return ExportCsv::telecharger("classe-{$classe->nom}.csv",
                array_merge(['N°', 'Matricule', 'Nom', 'Prénoms', 'Sexe', 'Date de naissance', 'Statut'], $contacts ? ['Responsable', 'Téléphone'] : []),
                $inscriptions->where('statut', StatutInscription::Inscrit)->values()->map(function ($i, $n) use ($contacts) {
                    $ligne = [$n + 1, $i->eleve->matricule, $i->eleve->nom, $i->eleve->prenoms, $i->eleve->sexe->value,
                        $i->eleve->date_naissance, $i->redoublant ? 'Redoublant' : ''];
                    if ($contacts) {
                        $responsable = $i->eleve->responsables->sortByDesc('pivot.est_contact_principal')->first();
                        array_push($ligne, $responsable?->nom_complet, $responsable?->telephone);
                    }

                    return $ligne;
                }));
        }

        return view('scolarite.classes.show', [
            'classe' => $classe,
            'contacts' => $contacts,
            'inscrits' => $inscriptions->filter->estActive()->values(),
            'partis' => $inscriptions->reject->estActive()->values(),
            'affectations' => $classe->affectations()->with(['matiere', 'enseignant.user'])->get(),
        ]);
    }

    public function edit(Classe $classe): View
    {
        return view('scolarite.classes.form', $this->donneesFormulaire($classe));
    }

    public function update(Request $request, Classe $classe): RedirectResponse
    {
        abort_if($classe->anneeScolaire->estCloturee(), 403, 'Une année clôturée est en lecture seule.');

        $avant = $classe->only(['nom', 'niveau_id', 'serie', 'capacite', 'professeur_principal_id']);
        $donnees = $this->valider($request, $classe);
        if ($classe->inscriptions()->exists()) {
            // Les élèves inscrits fixent l'année ; le niveau reste modifiable (correction de saisie).
            unset($donnees['annee_scolaire_id']);
        }
        $classe->update($donnees);
        $this->journal->enregistrer('classe.modifiee', $classe, "Classe {$classe->nom} modifiée", $avant,
            $classe->only(array_keys($avant)));

        return redirect()->route('classes.show', $classe)->with('succes', 'La classe a été modifiée.');
    }

    public function destroy(Classe $classe): RedirectResponse
    {
        if ($classe->inscriptions()->exists() || $classe->affectations()->exists() || $classe->appels()->exists()) {
            return back()->with('erreur', 'Cette classe a des élèves, des affectations ou des appels : elle ne peut pas être supprimée.');
        }
        $annee = $classe->annee_scolaire_id;
        $classe->delete();
        $this->journal->enregistrer('classe.supprimee', null, "Classe {$classe->nom} supprimée");

        return redirect()->route('classes.index', ['annee' => $annee])->with('succes', "La classe {$classe->nom} a été supprimée.");
    }

    /**
     * Prépare une nouvelle année : recopie les classes (nom, niveau, série, capacité) d'une autre année.
     */
    public function dupliquer(Request $request): RedirectResponse
    {
        $donnees = $request->validate([
            'source_id' => ['required', 'exists:annees_scolaires,id', 'different:cible_id'],
            'cible_id' => ['required', Rule::exists('annees_scolaires', 'id')->whereNot('statut', StatutAnnee::Cloturee->value)],
        ], [], ['source_id' => 'année source', 'cible_id' => 'année cible']);

        $cible = AnneeScolaire::findOrFail($donnees['cible_id']);
        $existantes = $cible->classes()->pluck('nom')->all();

        $creees = DB::transaction(function () use ($donnees, $cible, $existantes) {
            $creees = 0;
            foreach (Classe::query()->where('annee_scolaire_id', $donnees['source_id'])->get() as $source) {
                if (in_array($source->nom, $existantes, true)) {
                    continue;
                }
                Classe::create(['annee_scolaire_id' => $cible->id] + $source->only(['niveau_id', 'nom', 'serie', 'capacite']));
                $creees++;
            }

            return $creees;
        });

        $this->journal->enregistrer('classe.dupliquees', $cible, "{$creees} classe(s) recopiée(s) vers {$cible->libelle}");

        return redirect()->route('classes.index', ['annee' => $cible->id])
            ->with($creees ? 'succes' : 'info', $creees ? "{$creees} classe(s) créée(s) pour {$cible->libelle}." : 'Aucune nouvelle classe : elles existent déjà.');
    }

    private function valider(Request $request, ?Classe $classe = null): array
    {
        $request->merge(['nom' => trim((string) $request->input('nom'))]);
        $annee = $request->input('annee_scolaire_id', $classe?->annee_scolaire_id);

        return $request->validate([
            'annee_scolaire_id' => ['required', Rule::exists('annees_scolaires', 'id')->whereNot('statut', StatutAnnee::Cloturee->value)],
            'niveau_id' => ['required', 'exists:niveaux,id'],
            'nom' => ['required', 'string', 'max:30', Rule::unique('classes')->where('annee_scolaire_id', $annee)->ignore($classe)],
            'serie' => ['nullable', 'string', 'max:10'],
            'capacite' => ['nullable', 'integer', 'between:1,500'],
            'professeur_principal_id' => ['nullable', Rule::exists('enseignants', 'id')->where('est_actif', true)],
        ], [
            'nom.unique' => 'Une classe porte déjà ce nom pour cette année scolaire.',
            'annee_scolaire_id.exists' => 'Choisissez une année scolaire non clôturée.',
        ], [
            'annee_scolaire_id' => 'année scolaire', 'niveau_id' => 'niveau', 'professeur_principal_id' => 'professeur principal',
        ]);
    }

    private function donneesFormulaire(Classe $classe): array
    {
        return [
            'classe' => $classe,
            'annees' => AnneeScolaire::query()->where('statut', '!=', StatutAnnee::Cloturee)->orderByDesc('date_debut')->pluck('libelle', 'id'),
            'niveaux' => Niveau::query()->orderBy('ordre')->pluck('libelle', 'id'),
            'enseignants' => Enseignant::query()->where('est_actif', true)->with('user')->get()
                ->sortBy('user.name')->mapWithKeys(fn ($e) => [$e->id => $e->user->name]),
        ];
    }
}
