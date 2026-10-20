<?php

namespace App\Http\Controllers\Pedagogie;

use App\Http\Controllers\Controller;
use App\Models\Affectation;
use App\Models\AnneeScolaire;
use App\Models\Classe;
use App\Models\Enseignant;
use App\Models\Matiere;
use App\Services\AffectationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Matières, enseignants titulaires et coefficients de chaque classe.
 */
class AffectationController extends Controller
{
    public function __construct(private AffectationService $service) {}

    public function index(Request $request): View
    {
        $annees = AnneeScolaire::query()->orderByDesc('date_debut')->get();
        $annee = $annees->firstWhere('id', $request->integer('annee')) ?? $annees->firstWhere('est_active', true) ?? $annees->first();

        $classes = $annee ? Classe::query()
            ->where('annee_scolaire_id', $annee->id)
            ->with(['niveau', 'affectations'])
            ->withCount(['affectations', 'affectations as non_pourvues' => fn ($q) => $q->whereNull('enseignant_id')])
            ->join('niveaux', 'niveaux.id', '=', 'classes.niveau_id')
            ->orderBy('niveaux.ordre')->orderBy('classes.nom')->select('classes.*')
            ->get() : collect();

        // Charge horaire et nombre de classes par enseignant pour l'année affichée.
        $enseignants = $annee ? Enseignant::query()->actifs()->with('user')
            ->withCount(['affectations as nb_affectations' => fn ($q) => $q->whereHas('classe', fn ($c) => $c->where('annee_scolaire_id', $annee->id))])
            ->get()->sortBy('user.name')->values() : collect();

        return view('pedagogie.affectations.index', compact('annees', 'annee', 'classes', 'enseignants'));
    }

    public function classe(Classe $classe): View
    {
        $classe->load(['anneeScolaire', 'niveau', 'professeurPrincipal.user']);
        $affectations = $classe->affectations()->with(['matiere', 'enseignant.user', 'enseignant.matieres'])
            ->withCount(['evaluations', 'creneaux'])->get();

        $enseignants = Enseignant::query()->actifs()->with(['user', 'matieres'])->get()->sortBy('user.name')->values();
        // Classes candidates pour pré-remplir : même niveau d'abord, toutes années.
        $modeles = Classe::query()->with('anneeScolaire')->whereKeyNot($classe->id)->has('affectations')
            ->join('annees_scolaires as a', 'a.id', '=', 'classes.annee_scolaire_id')
            ->orderByRaw('CASE WHEN classes.niveau_id = ? THEN 0 ELSE 1 END', [$classe->niveau_id])
            ->orderByDesc('a.date_debut')->orderBy('classes.nom')->select('classes.*')->get();

        return view('pedagogie.affectations.classe', [
            'classe' => $classe,
            'affectations' => $affectations,
            'enseignants' => $enseignants,
            'matieresDisponibles' => Matiere::query()->whereNotIn('id', $affectations->pluck('matiere_id'))->orderBy('libelle')->pluck('libelle', 'id'),
            'modeles' => $modeles,
            'totalCoefficients' => $affectations->sum(fn ($a) => (float) $a->coefficient),
        ]);
    }

    public function store(Request $request, Classe $classe): RedirectResponse
    {
        $donnees = $request->validate([
            'matiere_id' => ['required', 'exists:matieres,id'],
            'enseignant_id' => ['nullable', Rule::exists('enseignants', 'id')->where('est_actif', true)],
            'coefficient' => ['required', 'numeric', 'min:0.5', 'max:20', 'decimal:0,2'],
        ], [], ['matiere_id' => 'matière', 'enseignant_id' => 'enseignant']);

        return $this->executer(function () use ($classe, $donnees) {
            $affectation = $this->service->ajouter($classe, (int) $donnees['matiere_id'], ! empty($donnees['enseignant_id']) ? (int) $donnees['enseignant_id'] : null, (float) $donnees['coefficient']);

            return "{$affectation->matiere->libelle} ajoutée à la classe {$classe->nom}.";
        });
    }

    public function update(Request $request, Classe $classe): RedirectResponse
    {
        $donnees = $request->validate([
            'lignes' => ['required', 'array'],
            'lignes.*.enseignant_id' => ['nullable', Rule::exists('enseignants', 'id')],
            'lignes.*.coefficient' => ['required', 'numeric', 'min:0.5', 'max:20', 'decimal:0,2'],
            'lignes.*.ordre_bulletin' => ['required', 'integer', 'between:0,99'],
        ], [], ['lignes.*.coefficient' => 'coefficient', 'lignes.*.ordre_bulletin' => 'ordre', 'lignes.*.enseignant_id' => 'enseignant']);

        // Un enseignant inactif ne peut pas recevoir de nouvelle affectation (il peut conserver l'actuelle).
        $actuels = $classe->affectations()->pluck('enseignant_id', 'id');
        foreach ($donnees['lignes'] as $id => $ligne) {
            $nouveau = $ligne['enseignant_id'] ?? null;
            if ($nouveau && (int) $nouveau !== (int) ($actuels[$id] ?? 0) && ! Enseignant::whereKey($nouveau)->where('est_actif', true)->exists()) {
                return back()->withInput()->withErrors(["lignes.{$id}.enseignant_id" => 'Cet enseignant est inactif.']);
            }
        }

        return $this->executer(fn () => ($n = $this->service->mettreAJour($classe, $donnees['lignes']))
            ? "{$n} ligne(s) mise(s) à jour." : 'Aucune modification.');
    }

    public function destroy(Classe $classe, Affectation $affectation): RedirectResponse
    {
        return $this->executer(function () use ($affectation) {
            $libelle = $affectation->matiere->libelle;
            $this->service->retirer($affectation);

            return "{$libelle} a été retirée de la classe.";
        });
    }

    public function copier(Request $request, Classe $classe): RedirectResponse
    {
        $donnees = $request->validate(['source_id' => ['required', 'exists:classes,id']], [], ['source_id' => 'classe modèle']);

        return $this->executer(function () use ($classe, $donnees, $request) {
            $source = Classe::findOrFail($donnees['source_id']);
            $n = $this->service->copier($source, $classe, $request->boolean('avec_enseignants'));

            return $n ? "{$n} matière(s) reprise(s) de {$source->nom}." : 'Toutes les matières de cette classe sont déjà présentes.';
        });
    }

    /**
     * @param  callable(): string  $action
     */
    private function executer(callable $action): RedirectResponse
    {
        try {
            $message = $action();
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        $retour = back()->with('succes', $message);

        return $this->service->avertissements ? $retour->with('avertissement', implode(' ', $this->service->avertissements)) : $retour;
    }
}
