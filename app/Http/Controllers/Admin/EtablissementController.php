<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EtablissementRequest;
use App\Models\Etablissement;
use App\Services\JournalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class EtablissementController extends Controller
{
    public function edit(): View
    {
        return view('admin.etablissement', ['etablissement' => Etablissement::courant() ?? new Etablissement]);
    }

    public function update(EtablissementRequest $request, JournalService $journal): RedirectResponse
    {
        $etablissement = Etablissement::courant() ?? new Etablissement;
        $avant = $etablissement->only(['nom', 'sigle', 'devise', 'parametres']);

        $donnees = $request->safe()->except(['logo', 'image_connexion', 'parametres']);
        $parametres = array_replace($etablissement->parametres ?? [], $request->validated('parametres'));
        $parametres['notifier_absences'] = $request->boolean('parametres.notifier_absences');
        $parametres['remise_devoirs_en_ligne'] = $request->boolean('parametres.remise_devoirs_en_ligne');
        $parametres['mentions'] = collect($parametres['mentions'])
            ->map(fn ($m) => ['min' => (float) $m['min'], 'libelle' => $m['libelle']])
            ->sortByDesc('min')->values()->all();
        if ($request->hasFile('image_connexion')) {
            if ($ancienne = $etablissement->parametre('image_connexion')) {
                Storage::disk('local')->delete($ancienne);
            }
            $parametres['image_connexion'] = $request->file('image_connexion')->store('etablissement', 'local');
        }
        $donnees['parametres'] = $parametres;

        if ($request->hasFile('logo')) {
            if ($etablissement->logo_chemin) {
                Storage::disk('local')->delete($etablissement->logo_chemin);
            }
            $donnees['logo_chemin'] = $request->file('logo')->store('etablissement', 'local');
        }

        $etablissement->fill($donnees)->save();
        $journal->enregistrer('etablissement.modifie', $etablissement, 'Paramètres de l\'établissement modifiés',
            $avant, $etablissement->only(['nom', 'sigle', 'devise', 'parametres']));

        return back()->with('succes', 'Les paramètres de l\'établissement ont été enregistrés.');
    }

    /**
     * Le logo apparaît sur la page de connexion et les documents : il est servi sans authentification.
     */
    /**
     * Photo de fond de la page de connexion (publique, comme le logo).
     */
    public function imageConnexion(): Response
    {
        $chemin = Etablissement::courant()?->parametre('image_connexion');
        abort_unless($chemin && Storage::disk('local')->exists($chemin), 404);

        return Storage::disk('local')->response($chemin, null, ['Cache-Control' => 'public, max-age=86400']);
    }

    public function logo(): Response
    {
        $chemin = Etablissement::courant()?->logo_chemin;
        abort_unless($chemin && Storage::disk('local')->exists($chemin), 404);

        return Storage::disk('local')->response($chemin, null, ['Cache-Control' => 'public, max-age=3600']);
    }
}
