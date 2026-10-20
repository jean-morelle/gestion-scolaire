<?php

namespace App\Http\Controllers\Finance;

use App\Enums\StatutAnnee;
use App\Http\Controllers\Controller;
use App\Models\AnneeScolaire;
use App\Models\GrilleFrais;
use App\Models\Niveau;
use App\Models\TypeFrais;
use App\Services\FraisService;
use App\Services\JournalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Types de frais, grilles par niveau et échéanciers (comptabilité).
 */
class FraisController extends Controller
{
    public function __construct(private FraisService $service, private JournalService $journal) {}

    public function index(Request $request): View
    {
        $annees = AnneeScolaire::query()->orderByDesc('date_debut')->get();
        $annee = $annees->firstWhere('id', $request->integer('annee')) ?? $annees->firstWhere('est_active', true) ?? $annees->first();

        $grilles = $annee ? GrilleFrais::query()->with(['typeFrais', 'tranches', 'niveau'])->where('annee_scolaire_id', $annee->id)
            ->get()->sortBy(fn ($g) => [$g->niveau->ordre, $g->typeFrais->libelle, $g->categorie])->groupBy('niveau_id') : collect();

        return view('finance.frais.index', [
            'annees' => $annees,
            'annee' => $annee,
            'niveaux' => Niveau::query()->orderBy('ordre')->get(),
            'grilles' => $grilles,
            'types' => TypeFrais::query()->withCount('grilles')->orderBy('libelle')->get(),
            'anneesSource' => $annees->where('id', '!=', $annee?->id),
        ]);
    }

    public function create(Request $request): View
    {
        return view('finance.frais.form', $this->formulaire(new GrilleFrais([
            'annee_scolaire_id' => $request->integer('annee') ?: annee_active()?->id,
            'niveau_id' => $request->integer('niveau') ?: null,
            'categorie' => '',
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->enregistrer($request);
    }

    public function edit(GrilleFrais $grille): View
    {
        return view('finance.frais.form', $this->formulaire($grille->load('tranches')));
    }

    public function update(Request $request, GrilleFrais $grille): RedirectResponse
    {
        return $this->enregistrer($request, $grille);
    }

    public function destroy(GrilleFrais $grille): RedirectResponse
    {
        $annee = $grille->annee_scolaire_id;
        $this->service->supprimerGrille($grille);

        return redirect()->route('grilles-frais.index', ['annee' => $annee])->with('succes', 'Grille supprimée. Les frais déjà facturés aux élèves sont conservés.');
    }

    public function copier(Request $request): RedirectResponse
    {
        $donnees = $request->validate([
            'source_id' => ['required', 'exists:annees_scolaires,id', 'different:cible_id'],
            'cible_id' => ['required', Rule::exists('annees_scolaires', 'id')->whereNot('statut', StatutAnnee::Cloturee->value)],
        ], [], ['source_id' => 'année source', 'cible_id' => 'année cible']);

        $n = $this->service->copierGrilles(AnneeScolaire::findOrFail($donnees['source_id']), AnneeScolaire::findOrFail($donnees['cible_id']));

        return back()->with('succes', "{$n} grille(s) recopiée(s), dates limites décalées d'une année. Vérifiez les montants.");
    }

    public function generer(AnneeScolaire $annee): RedirectResponse
    {
        try {
            $n = $this->service->genererPourLAnnee($annee);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('succes', $n ? "{$n} ligne(s) de frais ajoutée(s) aux élèves inscrits." : 'Tous les élèves inscrits ont déjà leurs frais obligatoires.');
    }

    public function storeType(Request $request): RedirectResponse
    {
        $type = TypeFrais::create($this->validerType($request));
        $this->journal->enregistrer('type_frais.cree', null, "Type de frais « {$type->libelle} » créé");

        return back()->with('succes', "Type « {$type->libelle} » ajouté.");
    }

    public function updateType(Request $request, TypeFrais $type): RedirectResponse
    {
        $type->update($this->validerType($request, $type));
        $this->journal->enregistrer('type_frais.modifie', null, "Type de frais « {$type->libelle} » modifié");

        return back()->with('succes', 'Type de frais modifié.');
    }

    private function enregistrer(Request $request, ?GrilleFrais $grille = null): RedirectResponse
    {
        $request->merge([
            'montant' => preg_replace('/\D/', '', (string) $request->input('montant')),
            'tranches' => collect($request->input('tranches', []))->map(fn ($t) => array_merge($t, ['montant' => preg_replace('/\D/', '', (string) ($t['montant'] ?? ''))]))->all(),
        ]);
        $donnees = $request->validate([
            'annee_scolaire_id' => ['required', 'exists:annees_scolaires,id'],
            'niveau_id' => ['required', 'exists:niveaux,id'],
            'type_frais_id' => ['required', 'exists:types_frais,id'],
            'categorie' => ['nullable', 'string', 'max:30'],
            'montant' => ['required', 'integer', 'min:1', 'max:100000000'],
            'tranches' => ['array', 'max:12'],
            'tranches.*.libelle' => ['required_with:tranches.*.montant', 'nullable', 'string', 'max:50'],
            'tranches.*.montant' => ['required_with:tranches.*.libelle', 'nullable', 'integer', 'min:1'],
            'tranches.*.date_limite' => ['required_with:tranches.*.libelle', 'nullable', 'date'],
        ], [], ['niveau_id' => 'niveau', 'type_frais_id' => 'type de frais', 'tranches.*.date_limite' => 'date limite', 'tranches.*.montant' => 'montant de la tranche']);

        try {
            $grille = $this->service->enregistrerGrille(collect($donnees)->except('tranches')->all(), $donnees['tranches'] ?? [], $grille);
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return redirect()->route('grilles-frais.index', ['annee' => $grille->annee_scolaire_id])
            ->with('succes', "Grille enregistrée : {$grille->typeFrais->libelle} — {$grille->niveau->libelle}, ".montant($grille->montant).'.');
    }

    private function validerType(Request $request, ?TypeFrais $type = null): array
    {
        $request->merge(['code' => mb_strtoupper(trim((string) $request->input('code')))]);

        return $request->validate([
            'code' => ['required', 'alpha_dash', 'max:30', Rule::unique('types_frais')->ignore($type)],
            'libelle' => ['required', 'string', 'max:100'],
            'est_obligatoire' => ['boolean'],
        ]) + ['est_obligatoire' => $request->boolean('est_obligatoire')];
    }

    private function formulaire(GrilleFrais $grille): array
    {
        return [
            'grille' => $grille,
            'annees' => AnneeScolaire::query()->where('statut', '!=', StatutAnnee::Cloturee)->orderByDesc('date_debut')->pluck('libelle', 'id'),
            'niveaux' => Niveau::query()->orderBy('ordre')->pluck('libelle', 'id'),
            'types' => TypeFrais::query()->orderBy('libelle')->get()->mapWithKeys(fn ($t) => [$t->id => $t->libelle.($t->est_obligatoire ? '' : ' (facultatif)')]),
            'categories' => GrilleFrais::query()->where('categorie', '!=', '')->distinct()->pluck('categorie'),
        ];
    }
}
