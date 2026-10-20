<x-layouts.app titre="Nouveau justificatif">
    <x-entete titre="Nouveau justificatif d'absence" :retour="route('justificatifs.index')" />

    <x-carte class="max-w-2xl">
        <form method="GET" class="mb-5 flex flex-wrap items-end gap-2 border-b border-slate-100 pb-5">
            <x-selection nom="classe" label="Classe" :options="$classes->pluck('nom', 'id')" :valeur="$classe?->id" vide="— Choisir —" class="w-48" onchange="this.form.submit()" />
            <noscript><x-bouton variante="secondaire">Choisir</x-bouton></noscript>
        </form>

        @if ($classe)
            <form method="POST" action="{{ route('justificatifs.store') }}" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <x-selection nom="inscription_id" label="Élève" :options="$eleves" :valeur="$inscription?->id" vide="— Choisir —" requis />
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-champ nom="date_debut" label="Du" type="date" :valeur="$date" requis />
                    <x-champ nom="date_fin" label="Au" type="date" :valeur="$date" requis />
                </div>
                <x-champ nom="motif" label="Motif" placeholder="Ex. maladie (certificat médical)" requis maxlength="255" />
                <x-champ nom="fichier" label="Pièce justificative (facultatif)" type="file" accept=".pdf,image/jpeg,image/png"
                    :aide="'PDF, JPG ou PNG, '.intdiv($tailleMaxKo, 1024).' Mo au maximum. Stockée de façon privée.'" />
                <div class="flex gap-2">
                    <x-bouton>Enregistrer</x-bouton>
                    <x-bouton variante="secondaire" :href="route('justificatifs.index')">Annuler</x-bouton>
                </div>
            </form>
        @else
            <p class="text-sm text-slate-500">Choisissez d'abord la classe de l'élève.</p>
        @endif
    </x-carte>
</x-layouts.app>
