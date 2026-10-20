<?php

namespace App\Http\Controllers\Pedagogie;

use App\Http\Controllers\Controller;
use App\Models\Classe;
use App\Services\EmploiDuTempsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Classes et matières attribuées à l'enseignant connecté pour l'année active.
 */
class MesClassesController extends Controller
{
    public function __invoke(Request $request): View
    {
        $enseignant = $request->user()->enseignant;
        abort_unless($enseignant, 403, 'Aucune fiche enseignant n\'est liée à votre compte.');

        $affectations = $enseignant->affectationsActives()
            ->with(['classe.niveau', 'matiere', 'creneaux'])
            ->withCount('evaluations')
            ->get();
        $classesPrincipales = $enseignant->classesPrincipales()->whereHas('anneeScolaire', fn ($q) => $q->where('est_active', true))->pluck('id');

        $classes = $affectations->groupBy('classe_id')->map(fn ($lignes) => [
            'classe' => $lignes->first()->classe,
            'affectations' => $lignes->sortBy('matiere.libelle')->values(),
            'principal' => $classesPrincipales->contains($lignes->first()->classe_id),
        ]);
        // Classes dont il est professeur principal sans y enseigner.
        foreach (Classe::query()->with('niveau')->whereIn('id', $classesPrincipales->diff($classes->keys()))->get() as $classe) {
            $classes[$classe->id] = ['classe' => $classe, 'affectations' => collect(), 'principal' => true];
        }
        $effectifs = Classe::query()->whereIn('id', $classes->keys())->withCount('inscriptionsActives')->pluck('inscriptions_actives_count', 'id');

        return view('pedagogie.mes-classes.index', [
            'classes' => $classes->sortBy(fn ($c) => [$c['classe']->niveau->ordre, $c['classe']->nom])->values(),
            'effectifs' => $effectifs,
            'volume' => EmploiDuTempsService::volumeHoraire($affectations->flatMap->creneaux),
            'enseignant' => $enseignant,
        ]);
    }
}
