<?php

namespace App\Http\Controllers\Famille;

use App\Http\Controllers\Controller;
use App\Models\Eleve;
use App\Services\SuiviEleveService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Espace famille (un parent, ses enfants) et espace élève (lui-même). Chaque requête vérifie le lien
 * responsable–élève ou l'identité de l'élève côté serveur (ElevePolicy::suivre), jamais seulement l'interface.
 */
class SuiviController extends Controller
{
    private const SECTIONS = ['resume', 'resultats', 'assiduite', 'finances', 'emploi-du-temps'];

    public function __construct(private SuiviEleveService $suivi) {}

    public function enfants(Request $request): View
    {
        $responsable = $request->user()->responsable;
        abort_unless($responsable, 403, 'Aucune fiche de responsable n\'est liée à votre compte : contactez le secrétariat.');

        return view('famille.enfants', [
            'resumes' => $responsable->eleves()->orderBy('prenoms')->get()->map(fn (Eleve $e) => $this->suivi->resume($e, true)),
        ]);
    }

    public function enfant(Request $request, Eleve $eleve, string $section = 'resume'): View
    {
        $this->authorize('suivre', $eleve);

        return $this->afficher($request, $eleve, $section, 'famille');
    }

    public function monEspace(Request $request, string $section = 'resume'): View
    {
        $eleve = $request->user()->eleve;
        abort_unless($eleve, 403, 'Aucun dossier élève n\'est lié à votre compte.');
        abort_if($section === 'finances', 404);

        return $this->afficher($request, $eleve, $section, 'eleve');
    }

    private function afficher(Request $request, Eleve $eleve, string $section, string $espace): View
    {
        abort_unless(in_array($section, self::SECTIONS, true), 404);
        $finances = $request->user()->can('suivreFinances', $eleve);
        abort_if($section === 'finances' && ! $finances, 403);

        $inscription = $this->suivi->inscriptionCourante($eleve);
        $donnees = match ($section) {
            'resume' => $this->suivi->resume($eleve, $finances),
            'resultats' => ['bulletins' => $this->suivi->bulletinsPublies($eleve), 'notes' => $inscription ? $this->suivi->notesPubliees($inscription) : collect()],
            'assiduite' => $inscription ? $this->suivi->assiduite($inscription) : ['evenements' => collect(), 'periodes' => collect()],
            'finances' => $inscription ? $this->suivi->finances($inscription) : null,
            'emploi-du-temps' => ['jours' => $inscription ? $this->suivi->emploiDuTemps($inscription) : []],
        };

        return view('famille.suivi', array_merge($donnees ?? [], [
            'eleve' => $eleve,
            'inscription' => $inscription,
            'section' => $section,
            'espace' => $espace,
            'avecFinances' => $finances,
        ]));
    }
}
