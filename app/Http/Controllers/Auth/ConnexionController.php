<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ConnexionRequest;
use App\Services\JournalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ConnexionController extends Controller
{
    public function create(): View
    {
        return view('auth.connexion');
    }

    public function store(ConnexionRequest $request, JournalService $journal): RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();

        $user = $request->user();
        $user->forceFill(['derniere_connexion_at' => now()])->save();
        $journal->enregistrer('connexion', $user, 'Connexion réussie');

        return redirect()->intended(route('tableau-de-bord'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('connexion');
    }
}
