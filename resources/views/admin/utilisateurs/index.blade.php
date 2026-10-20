<x-layouts.app titre="Utilisateurs">
    <x-entete titre="Comptes utilisateurs" sous-titre="Comptes du personnel. Les comptes parents et élèves se créent depuis les fiches élèves.">
        <x-bouton :href="route('utilisateurs.create')" icone="plus">Nouveau compte</x-bouton>
    </x-entete>

    <x-carte sans-marge>
        <form method="GET" class="grid gap-3 border-b border-slate-100 p-4 sm:grid-cols-4">
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Nom, e-mail ou téléphone" class="champ sm:col-span-2" aria-label="Rechercher">
            <select name="role" class="champ" aria-label="Rôle">
                <option value="">Tous les rôles</option>
                @foreach ($roles as $valeur => $libelle)
                    <option value="{{ $valeur }}" @selected(request('role') === $valeur)>{{ $libelle }}</option>
                @endforeach
            </select>
            <div class="flex gap-2">
                <select name="statut" class="champ" aria-label="Statut">
                    <option value="">Tous</option>
                    <option value="actif" @selected(request('statut') === 'actif')>Actifs</option>
                    <option value="inactif" @selected(request('statut') === 'inactif')>Désactivés</option>
                </select>
                <x-bouton variante="secondaire" icone="recherche">Filtrer</x-bouton>
            </div>
        </form>

        @if ($utilisateurs->isEmpty())
            <x-vide message="Aucun compte ne correspond à la recherche." icone="cle" />
        @else
            <x-tableau>
                <x-slot:entete><th>Nom</th><th>Identifiant</th><th>Rôles</th><th>Dernière connexion</th><th>Statut</th><th></th></x-slot:entete>
                @foreach ($utilisateurs as $u)
                    <tr>
                        <td class="font-medium">{{ $u->name }}</td>
                        <td>{{ $u->identifiant() }}</td>
                        <td class="space-x-1">
                            @foreach ($u->roles as $role)
                                <x-badge couleur="bleu">{{ \App\Enums\Role::from($role->name)->libelle() }}</x-badge>
                            @endforeach
                        </td>
                        <td>{{ date_fr($u->derniere_connexion_at, true) }}</td>
                        <td>
                            @if ($u->est_actif)<x-badge couleur="vert">Actif</x-badge>@else<x-badge couleur="rouge">Désactivé</x-badge>@endif
                            @if ($u->doit_changer_mot_de_passe)<x-badge couleur="orange">Mot de passe temporaire</x-badge>@endif
                        </td>
                        <td class="text-right"><a href="{{ route('utilisateurs.edit', $u) }}" class="lien">Gérer</a></td>
                    </tr>
                @endforeach
            </x-tableau>
            <div class="p-4">{{ $utilisateurs->links() }}</div>
        @endif
    </x-carte>
</x-layouts.app>
