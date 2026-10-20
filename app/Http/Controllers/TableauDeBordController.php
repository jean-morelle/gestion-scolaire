<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Services\TableauDeBordService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TableauDeBordController extends Controller
{
    public function __invoke(Request $request, TableauDeBordService $tableau): View|RedirectResponse
    {
        $user = $request->user();

        // Parents et élèves ont leur propre accueil (un membre du personnel qui est aussi parent garde le sien).
        if (! $user->estPersonnel()) {
            if ($user->aRole(Role::Parent)) {
                return redirect()->route('famille.enfants');
            }
            if ($user->aRole(Role::Eleve)) {
                return redirect()->route('eleve.accueil');
            }
        }

        return view('tableau-de-bord.index', ['user' => $user, 'blocs' => $tableau->pour($user), 'annee' => annee_active()]);
    }
}
