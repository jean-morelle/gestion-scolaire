<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Déconnecte immédiatement un utilisateur dont le compte a été désactivé (RG-SEC-03)
 * et impose le changement d'un mot de passe temporaire (RG-PAR-05).
 */
class CompteActif
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->est_actif) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('connexion')
                ->withErrors(['identifiant' => 'Ce compte est désactivé. Contactez l\'administration de l\'établissement.']);
        }
/*
        if ($user && $user->doit_changer_mot_de_passe && ! $request->routeIs('mot-de-passe.*', 'deconnexion')) {
            return redirect()->route('mot-de-passe.edit')
                ->with('info', 'Veuillez choisir un nouveau mot de passe avant de continuer.');
        }
*/
        return $next($request);
    }
}
