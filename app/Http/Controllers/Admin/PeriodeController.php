<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StatutPeriode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PeriodeRequest;
use App\Models\AnneeScolaire;
use App\Models\Periode;
use App\Services\AnneeScolaireService;
use App\Services\JournalService;
use Illuminate\Http\RedirectResponse;

class PeriodeController extends Controller
{
    public function store(PeriodeRequest $request, AnneeScolaire $annee, JournalService $journal): RedirectResponse
    {
        abort_if($annee->estCloturee(), 403, 'Une année clôturée est en lecture seule.');

        $periode = $annee->periodes()->create($request->validated() + ['statut' => StatutPeriode::Ouverte]);
        $journal->enregistrer('periode.creee', $periode, "{$periode->libelle} ajoutée à {$annee->libelle}");

        return back()->with('succes', 'La période a été ajoutée.');
    }

    public function update(PeriodeRequest $request, AnneeScolaire $annee, Periode $periode, JournalService $journal): RedirectResponse
    {
        abort_if($annee->estCloturee(), 403, 'Une année clôturée est en lecture seule.');

        $avant = $periode->only(['libelle', 'ordre', 'date_debut', 'date_fin']);
        $periode->update($request->validated());
        $journal->enregistrer('periode.modifiee', $periode, "{$periode->libelle} modifiée", $avant, $periode->only(['libelle', 'ordre', 'date_debut', 'date_fin']));

        return back()->with('succes', 'La période a été modifiée.');
    }

    public function cloturer(AnneeScolaire $annee, Periode $periode, AnneeScolaireService $service): RedirectResponse
    {
        $service->changerStatutPeriode($periode, StatutPeriode::Cloturee);

        return back()->with('succes', "{$periode->libelle} est clôturée : plus aucune note ni présence ne peut y être saisie.");
    }

    public function rouvrir(AnneeScolaire $annee, Periode $periode, AnneeScolaireService $service): RedirectResponse
    {
        abort_if($annee->estCloturee(), 403, 'Une année clôturée est en lecture seule.');
        $service->changerStatutPeriode($periode, StatutPeriode::Ouverte);

        return back()->with('succes', "{$periode->libelle} est rouverte.");
    }
}
