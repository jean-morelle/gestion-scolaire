<?php

namespace App\Http\Controllers\Scolarite;

use App\Enums\LienParente;
use App\Http\Controllers\Controller;
use App\Http\Requests\Scolarite\ResponsableRequest;
use App\Models\Eleve;
use App\Models\Responsable;
use App\Services\EleveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Responsables légaux rattachés aux élèves et comptes parents (réservé au secrétariat, RG-PAR-04).
 */
class ResponsableController extends Controller
{
    public function __construct(private EleveService $eleves) {}

    public function create(Eleve $eleve): View
    {
        $this->authorize('update', $eleve);

        return view('scolarite.responsables.form', [
            'eleve' => $eleve,
            'responsable' => new Responsable,
            'pivot' => ['lien' => null, 'est_contact_principal' => false, 'est_payeur' => false],
            'liens' => LienParente::options(),
        ]);
    }

    public function store(ResponsableRequest $request, Eleve $eleve): RedirectResponse
    {
        try {
            $responsable = $this->eleves->rattacher($eleve, ResponsableRequest::responsable($request->validated()),
                $request->safe()->only(['lien', 'est_contact_principal', 'est_payeur']));
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return redirect()->route('eleves.show', $eleve)->with('succes', "{$responsable->nom_complet} a été rattaché(e) à l'élève.");
    }

    public function edit(Eleve $eleve, Responsable $responsable): View
    {
        $this->authorize('update', $eleve);
        $lien = $eleve->responsables()->whereKey($responsable->id)->firstOrFail()->pivot;

        return view('scolarite.responsables.form', [
            'eleve' => $eleve,
            'responsable' => $responsable,
            'pivot' => $lien->only(['lien', 'est_contact_principal', 'est_payeur']),
            'liens' => LienParente::options(),
            'autresEnfants' => $responsable->eleves()->whereKeyNot($eleve->id)->orderBy('prenoms')->get(),
        ]);
    }

    public function update(ResponsableRequest $request, Eleve $eleve, Responsable $responsable): RedirectResponse
    {
        abort_unless($responsable->estResponsableDe($eleve), 404);

        $this->eleves->modifierResponsable($responsable, $request->safe()->only(array_keys(ResponsableRequest::reglesFiche())),
            $eleve, $request->safe()->only(['lien', 'est_contact_principal', 'est_payeur']));

        return redirect()->route('eleves.show', $eleve)->with('succes', "La fiche de {$responsable->nom_complet} a été mise à jour.");
    }

    public function destroy(Eleve $eleve, Responsable $responsable): RedirectResponse
    {
        $this->authorize('update', $eleve);
        abort_unless($responsable->estResponsableDe($eleve), 404);

        try {
            $this->eleves->detacher($eleve, $responsable);
        } catch (ValidationException $e) {
            return back()->with('erreur', collect($e->errors())->flatten()->first());
        }

        return back()->with('succes', "{$responsable->nom_complet} n'est plus rattaché(e) à cet élève.");
    }

    public function creerCompte(Eleve $eleve, Responsable $responsable): RedirectResponse
    {
        $this->authorize('update', $eleve);
        abort_unless($responsable->estResponsableDe($eleve), 404);

        try {
            $motDePasse = $this->eleves->creerCompteParent($responsable);
        } catch (ValidationException $e) {
            return back()->with('erreur', collect($e->errors())->flatten()->first());
        }

        return back()->with('succes', "Compte parent créé. Identifiant : {$responsable->telephone} — mot de passe temporaire : {$motDePasse} (à communiquer une seule fois).");
    }
}
