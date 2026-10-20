<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AnneeScolaireRequest;
use App\Models\AnneeScolaire;
use App\Services\AnneeScolaireService;
use App\Services\JournalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AnneeScolaireController extends Controller
{
    public function index(): View
    {
        return view('admin.annees.index', [
            'annees' => AnneeScolaire::query()->withCount(['periodes', 'classes', 'inscriptions'])->orderByDesc('date_debut')->get(),
        ]);
    }

    public function create(): View
    {
        $annee = new AnneeScolaire;
        $derniere = AnneeScolaire::query()->orderByDesc('date_fin')->first();
        $debut = $derniere ? $derniere->date_debut->year + 1 : (now()->month >= 8 ? now()->year : now()->year - 1);
        $annee->forceFill([
            'libelle' => $debut.'-'.($debut + 1),
            'date_debut' => "{$debut}-09-01",
            'date_fin' => ($debut + 1).'-07-15',
        ]);

        return view('admin.annees.form', compact('annee'));
    }

    public function store(AnneeScolaireRequest $request, AnneeScolaireService $service): RedirectResponse
    {
        $annee = $service->creer($request->validated(), $request->validated('decoupage'));

        return redirect()->route('annees.show', $annee)->with('succes', "L'année {$annee->libelle} a été créée. Vérifiez les dates des périodes.");
    }

    public function show(AnneeScolaire $annee): View
    {
        $annee->load('periodes');

        return view('admin.annees.show', compact('annee'));
    }

    public function edit(AnneeScolaire $annee): View
    {
        return view('admin.annees.form', compact('annee'));
    }

    public function update(AnneeScolaireRequest $request, AnneeScolaire $annee, JournalService $journal): RedirectResponse
    {
        abort_if($annee->estCloturee(), 403, 'Une année clôturée est en lecture seule.');

        $avant = $annee->only(['libelle', 'date_debut', 'date_fin']);
        $annee->update($request->safe()->only(['libelle', 'date_debut', 'date_fin']));
        $journal->enregistrer('annee.modifiee', $annee, "Année {$annee->libelle} modifiée", $avant, $annee->only(['libelle', 'date_debut', 'date_fin']));

        return redirect()->route('annees.show', $annee)->with('succes', 'L\'année scolaire a été modifiée.');
    }

    public function activer(AnneeScolaire $annee, AnneeScolaireService $service): RedirectResponse
    {
        abort_if($annee->estCloturee(), 403, 'Une année clôturée ne peut pas redevenir active.');
        $service->activer($annee);

        return back()->with('succes', "L'année {$annee->libelle} est désormais l'année active.");
    }

    public function cloturer(AnneeScolaire $annee, AnneeScolaireService $service): RedirectResponse
    {
        $service->cloturer($annee);

        return back()->with('succes', "L'année {$annee->libelle} est clôturée et passe en lecture seule.");
    }
}
