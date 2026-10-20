<x-layouts.app :titre="$utilisateur->exists ? $utilisateur->name : 'Nouveau compte'">
    <x-entete :titre="$utilisateur->exists ? 'Compte de '.$utilisateur->name : 'Nouveau compte du personnel'" :retour="route('utilisateurs.index')">
        @if ($utilisateur->exists)
            <form method="POST" action="{{ route('utilisateurs.reinitialiser', $utilisateur) }}" data-confirm="Générer un nouveau mot de passe temporaire pour {{ $utilisateur->name }} ?">
                @csrf
                <x-bouton variante="secondaire" icone="cle">Réinitialiser le mot de passe</x-bouton>
            </form>
        @endif
    </x-entete>

    <x-carte class="max-w-3xl">
        <form method="POST" action="{{ $utilisateur->exists ? route('utilisateurs.update', $utilisateur) : route('utilisateurs.store') }}" class="space-y-5">
            @csrf
            @if ($utilisateur->exists) @method('PUT') @endif
            <x-champ nom="name" label="Nom complet" :valeur="$utilisateur->name" requis />
            <div class="grid gap-4 sm:grid-cols-2">
                <x-champ nom="email" label="E-mail" type="email" :valeur="$utilisateur->email" aide="E-mail ou téléphone : au moins l'un des deux." />
                <x-champ nom="telephone" label="Téléphone" :valeur="$utilisateur->telephone" placeholder="Ex. 90000000" />
            </div>

            <fieldset>
                <legend class="mb-2 text-sm font-medium text-slate-700">Rôles <span class="text-red-500">*</span></legend>
                @php $actuels = old('roles', $utilisateur->exists ? $utilisateur->getRoleNames()->all() : []); @endphp
                <div class="grid gap-2 sm:grid-cols-3">
                    @foreach ($roles as $valeur => $libelle)
                        <label class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm hover:bg-slate-50">
                            <input type="checkbox" name="roles[]" value="{{ $valeur }}" @checked(in_array($valeur, $actuels, true)) class="size-4 rounded border-slate-300 text-marque-600">
                            {{ $libelle }}
                        </label>
                    @endforeach
                </div>
                @error('roles')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                <p class="mt-1 text-xs text-slate-500">Les rôles sont cumulables. Les droits de chaque rôle sont décrits dans docs/roles-permissions.md.</p>
            </fieldset>

            @unless ($utilisateur->exists)
                <x-champ nom="specialite" label="Spécialité (si enseignant)" placeholder="Ex. Mathématiques" />
            @endunless

            <x-case nom="est_actif" label="Compte actif" :coche="$utilisateur->est_actif" aide="Un compte désactivé ne peut plus se connecter ; son historique est conservé." />

            @unless ($utilisateur->exists)
                <p class="rounded-lg bg-marque-50 px-3 py-2 text-sm text-marque-900">Un mot de passe temporaire sera généré et affiché une seule fois. L'utilisateur devra le changer à sa première connexion.</p>
            @endunless

            <div class="flex gap-2">
                <x-bouton>Enregistrer</x-bouton>
                <x-bouton variante="secondaire" :href="route('utilisateurs.index')">Annuler</x-bouton>
            </div>
        </form>
    </x-carte>
</x-layouts.app>
