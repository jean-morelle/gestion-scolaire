<x-layouts.invite titre="Connexion">
    <h2 class="text-2xl font-semibold text-slate-900">Connexion</h2>
    <p class="mb-6 text-sm text-slate-500">Accédez à votre espace.</p>

    <form method="POST" action="{{ route('connexion') }}" class="space-y-4" data-sans-blocage x-data="{ voir: false }">
        @csrf
        <x-champ nom="identifiant" label="E-mail, téléphone ou matricule" placeholder="votre@email.com" aide="Les élèves se connectent avec leur matricule."
            requis autofocus autocomplete="username" />
        <div>
            <label for="champ-password" class="mb-1 block text-sm font-medium text-slate-700">Mot de passe <span class="text-red-500" aria-hidden="true">*</span></label>
            <div class="relative">
                <input id="champ-password" name="password" :type="voir ? 'text' : 'password'" type="password" required autocomplete="current-password"
                       placeholder="Mot de passe" @class(['champ pr-10', 'champ-erreur' => $errors->has('password')])>
                <button type="button" @click="voir = ! voir" class="absolute inset-y-0 right-0 grid w-10 place-items-center text-slate-400 hover:text-slate-600"
                        :aria-label="voir ? 'Masquer le mot de passe' : 'Afficher le mot de passe'"><x-icone nom="oeil" class="size-5" /></button>
            </div>
            @error('password')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
            <label class="flex items-center gap-2 text-slate-600">
                <input type="checkbox" name="remember" value="1" class="size-4 rounded border-slate-300 text-marque-600">
                Se souvenir de moi
            </label>
            <details class="relative">
                <summary class="lien cursor-pointer list-none">Mot de passe oublié ?</summary>
                <p class="absolute right-0 z-10 mt-2 w-64 rounded-lg border bg-white p-3 text-xs text-slate-600 shadow-lg">
                    Adressez-vous à l'administration de l'établissement : elle vous remettra un mot de passe temporaire.
                </p>
            </details>
        </div>
        <x-bouton class="w-full py-2.5">Se connecter</x-bouton>
    </form>
</x-layouts.invite>
