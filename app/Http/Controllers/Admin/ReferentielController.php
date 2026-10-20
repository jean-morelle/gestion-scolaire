<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Cycle;
use App\Http\Controllers\Controller;
use App\Models\Matiere;
use App\Models\Niveau;
use App\Services\JournalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Niveaux (primaire, collège, lycée) et matières : données de référence de l'établissement.
 */
class ReferentielController extends Controller
{
    public function __construct(private JournalService $journal) {}

    public function index(): View
    {
        return view('admin.referentiel', [
            'niveaux' => Niveau::query()->withCount('classes')->orderBy('ordre')->get(),
            'matieres' => Matiere::query()->withCount('affectations')->orderBy('libelle')->get(),
        ]);
    }

    public function storeNiveau(Request $request): RedirectResponse
    {
        $niveau = Niveau::create($this->validerNiveau($request));
        $this->journal->enregistrer('niveau.cree', $niveau, "Niveau {$niveau->libelle} créé");

        return back()->with('succes', "Le niveau {$niveau->libelle} a été ajouté.");
    }

    public function updateNiveau(Request $request, Niveau $niveau): RedirectResponse
    {
        $niveau->update($this->validerNiveau($request, $niveau));
        $this->journal->enregistrer('niveau.modifie', $niveau, "Niveau {$niveau->libelle} modifié");

        return back()->with('succes', 'Le niveau a été modifié.');
    }

    public function destroyNiveau(Niveau $niveau): RedirectResponse
    {
        if ($niveau->classes()->exists() || $niveau->grillesFrais()->exists()) {
            return back()->with('erreur', 'Ce niveau est utilisé par des classes ou des frais : il ne peut pas être supprimé.');
        }
        $niveau->delete();
        $this->journal->enregistrer('niveau.supprime', null, "Niveau {$niveau->libelle} supprimé");

        return back()->with('succes', "Le niveau {$niveau->libelle} a été supprimé.");
    }

    public function storeMatiere(Request $request): RedirectResponse
    {
        $matiere = Matiere::create($this->validerMatiere($request));
        $this->journal->enregistrer('matiere.creee', null, "Matière {$matiere->libelle} créée");

        return back()->with('succes', "La matière {$matiere->libelle} a été ajoutée.");
    }

    public function updateMatiere(Request $request, Matiere $matiere): RedirectResponse
    {
        $matiere->update($this->validerMatiere($request, $matiere));
        $this->journal->enregistrer('matiere.modifiee', null, "Matière {$matiere->libelle} modifiée");

        return back()->with('succes', 'La matière a été modifiée.');
    }

    public function destroyMatiere(Matiere $matiere): RedirectResponse
    {
        if ($matiere->affectations()->exists()) {
            return back()->with('erreur', 'Cette matière est affectée à des classes : elle ne peut pas être supprimée.');
        }
        $matiere->enseignants()->detach();
        $matiere->delete();
        $this->journal->enregistrer('matiere.supprimee', null, "Matière {$matiere->libelle} supprimée");

        return back()->with('succes', "La matière {$matiere->libelle} a été supprimée.");
    }

    private function validerNiveau(Request $request, ?Niveau $niveau = null): array
    {
        return $request->validateWithBag('niveau', [
            'libelle' => ['required', 'string', 'max:30', Rule::unique('niveaux')->ignore($niveau)],
            'cycle' => ['required', Rule::enum(Cycle::class)],
            'ordre' => ['required', 'integer', 'between:1,50'],
        ]);
    }

    private function validerMatiere(Request $request, ?Matiere $matiere = null): array
    {
        $request->merge(['code' => mb_strtoupper(trim((string) $request->input('code')))]);

        return $request->validateWithBag('matiere', [
            'code' => ['required', 'string', 'max:20', 'alpha_dash', Rule::unique('matieres')->ignore($matiere)],
            'libelle' => ['required', 'string', 'max:100'],
            'couleur' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);
    }
}
