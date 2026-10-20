<x-layouts.app titre="Mot de passe">
    <x-entete titre="Changer mon mot de passe"
        :sous-titre="$obligatoire ? 'Votre mot de passe est temporaire : choisissez-en un nouveau pour continuer.' : null" />

    <x-carte class="max-w-xl">
        <form method="POST" action="{{ route('mot-de-passe.update') }}" class="space-y-4">
            @csrf
            @method('PUT')
            <x-champ nom="mot_de_passe_actuel" label="Mot de passe actuel" type="password" requis autocomplete="current-password" />
            <x-champ nom="password" label="Nouveau mot de passe" type="password" requis autocomplete="new-password"
                aide="Au moins 8 caractères, avec des lettres et des chiffres." />
            <x-champ nom="password_confirmation" label="Confirmation" type="password" requis autocomplete="new-password" />
            <div class="flex gap-2">
                <x-bouton>Enregistrer</x-bouton>
                @unless ($obligatoire)
                    <x-bouton variante="secondaire" :href="route('tableau-de-bord')">Annuler</x-bouton>
                @endunless
            </div>
        </form>
    </x-carte>
</x-layouts.app>
