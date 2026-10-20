<x-layouts.app :titre="$annonce->exists ? 'Modifier l\'annonce' : 'Nouvelle annonce'">
    <x-entete :titre="$annonce->exists ? 'Modifier l\'annonce' : 'Nouvelle annonce'" :retour="$annonce->exists ? route('annonces.show', $annonce) : route('annonces.index')" />

    <x-carte class="max-w-3xl">
        <form method="POST" action="{{ $annonce->exists ? route('annonces.update', $annonce) : route('annonces.store') }}" class="space-y-5" x-data="{ cible: @js(old('cible', $annonce->cible?->value)) }">
            @csrf
            @if ($annonce->exists) @method('PUT') @endif
            <x-champ nom="titre" label="Titre" :valeur="$annonce->titre" requis maxlength="150" />
            <x-zone-texte nom="contenu" label="Message" :valeur="$annonce->contenu" :lignes="8" requis />
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="cible" class="mb-1 block text-sm font-medium text-slate-700">Destinataires <span class="text-red-500">*</span></label>
                    <select id="cible" name="cible" x-model="cible" class="champ">
                        @foreach (\App\Enums\CibleAnnonce::options() as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach
                    </select>
                </div>
                <div x-show="cible !== 'personnel'">
                    <x-selection nom="classe_id" label="Limiter à une classe" :options="$classes" :valeur="$annonce->classe_id" vide="— Toutes les classes —"
                        aide="Les familles et élèves de cette classe seulement." />
                </div>
                <x-champ nom="publiee_at" label="Publier le" type="datetime-local" :valeur="old('publiee_at', $annonce->publiee_at?->format('Y-m-d\TH:i'))"
                    aide="Vide = immédiatement. Une date future programme l'annonce." />
                <x-champ nom="expire_le" label="Visible jusqu'au" type="date" :valeur="$annonce->expire_le" aide="Facultatif." />
            </div>
            @unless ($annonce->exists)
                <p class="rounded-lg bg-marque-50 px-3 py-2 text-sm text-marque-900">Les destinataires disposant d'un compte recevront une notification dans l'application. Aucun SMS ni e-mail n'est envoyé.</p>
            @endunless
            <div class="flex gap-2">
                <x-bouton>{{ $annonce->exists ? 'Enregistrer' : 'Publier' }}</x-bouton>
                <x-bouton variante="secondaire" :href="$annonce->exists ? route('annonces.show', $annonce) : route('annonces.index')">Annuler</x-bouton>
            </div>
        </form>
    </x-carte>
</x-layouts.app>
