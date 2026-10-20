<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UtilisateurRequest;
use App\Models\User;
use App\Services\CompteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UtilisateurController extends Controller
{
    public function __construct(private CompteService $comptes) {}

    public function index(Request $request): View
    {
        $utilisateurs = User::query()
            ->with('roles')
            ->when($request->filled('q'), function ($q) use ($request) {
                $terme = '%'.$request->string('q').'%';
                $q->where(fn ($w) => $w->where('name', 'like', $terme)->orWhere('email', 'like', $terme)->orWhere('telephone', 'like', $terme));
            })
            ->when($request->filled('role'), fn ($q) => $q->role($request->string('role')))
            ->when($request->filled('statut'), fn ($q) => $q->where('est_actif', $request->input('statut') === 'actif'))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.utilisateurs.index', ['utilisateurs' => $utilisateurs, 'roles' => Role::options()]);
    }

    public function create(): View
    {
        return view('admin.utilisateurs.form', ['utilisateur' => new User(['est_actif' => true]), 'roles' => $this->rolesPersonnel()]);
    }

    public function store(UtilisateurRequest $request): RedirectResponse
    {
        [$user, $motDePasse] = $this->comptes->creer(
            $request->validated('name'), $request->validated('email'), $request->validated('telephone'),
            $request->validated('roles'), $request->boolean('est_actif', true),
        );
        if ($request->filled('specialite') && $user->enseignant) {
            $user->enseignant->update(['specialite' => $request->validated('specialite')]);
        }

        return redirect()->route('utilisateurs.edit', $user)
            ->with('succes', "Compte créé. Mot de passe temporaire à communiquer : {$motDePasse}");
    }

    public function edit(User $utilisateur): View
    {
        return view('admin.utilisateurs.form', ['utilisateur' => $utilisateur, 'roles' => $this->rolesPersonnel()]);
    }

    public function update(UtilisateurRequest $request, User $utilisateur): RedirectResponse
    {
        $roles = $request->validated('roles');
        // Les rôles parent/élève sont gérés depuis les fiches : ils sont conservés.
        $roles = array_values(array_unique(array_merge($roles, $utilisateur->getRoleNames()
            ->intersect([Role::Parent->value, Role::Eleve->value])->all())));

        if ($utilisateur->is($request->user())) {
            if (! in_array(Role::Administrateur->value, $roles, true) || ! $request->boolean('est_actif')) {
                return back()->withInput()->with('erreur', 'Vous ne pouvez pas retirer votre propre rôle d\'administrateur ni désactiver votre compte.');
            }
        }

        $this->comptes->modifier($utilisateur, [
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'telephone' => $request->validated('telephone'),
            'est_actif' => $request->boolean('est_actif'),
        ], $roles);

        return redirect()->route('utilisateurs.index')->with('succes', "Le compte de {$utilisateur->name} a été mis à jour.");
    }

    public function reinitialiser(User $utilisateur): RedirectResponse
    {
        $motDePasse = $this->comptes->reinitialiserMotDePasse($utilisateur);

        return back()->with('succes', "Nouveau mot de passe temporaire de {$utilisateur->name} : {$motDePasse}");
    }

    /**
     * @return array<string, string>
     */
    private function rolesPersonnel(): array
    {
        return collect(Role::personnel())->mapWithKeys(fn (Role $r) => [$r->value => $r->libelle()])->all();
    }
}
