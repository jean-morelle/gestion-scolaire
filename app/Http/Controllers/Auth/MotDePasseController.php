<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\JournalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Changement de mot de passe par l'utilisateur, obligatoire après une création de compte
 * ou une réinitialisation par l'établissement (RG-PAR-05).
 */
class MotDePasseController extends Controller
{
    public function edit(Request $request): View
    {
        return view('auth.mot-de-passe', ['obligatoire' => $request->user()->doit_changer_mot_de_passe]);
    }

    public function update(Request $request, JournalService $journal): RedirectResponse
    {
        $request->validate([
            'mot_de_passe_actuel' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:mot_de_passe_actuel', Password::min(8)->letters()->numbers()],
        ], [], [
            'mot_de_passe_actuel' => 'mot de passe actuel',
            'password' => 'nouveau mot de passe',
        ]);

        $user = $request->user();
        $user->forceFill([
            'password' => $request->string('password'),
            'doit_changer_mot_de_passe' => false,
        ])->save();

        $journal->enregistrer('compte.mot_de_passe_modifie', $user, 'Mot de passe modifié par l\'utilisateur');

        return redirect()->route('tableau-de-bord')->with('succes', 'Votre mot de passe a été modifié.');
    }
}
