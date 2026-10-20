<x-layouts.app :titre="$annee->exists ? 'Modifier l\'année' : 'Nouvelle année'">
    <x-entete :titre="$annee->exists ? 'Modifier l\'année '.$annee->libelle : 'Nouvelle année scolaire'" :retour="route('annees.index')" />

    <x-carte class="max-w-2xl">
        <form method="POST" action="{{ $annee->exists ? route('annees.update', $annee) : route('annees.store') }}" class="space-y-4">
            @csrf
            @if ($annee->exists) @method('PUT') @endif
            <x-champ nom="libelle" label="Libellé" :valeur="$annee->libelle" requis placeholder="2026-2027" />
            <div class="grid gap-4 sm:grid-cols-2">
                <x-champ nom="date_debut" label="Date de rentrée" type="date" :valeur="$annee->date_debut" requis />
                <x-champ nom="date_fin" label="Date de fin" type="date" :valeur="$annee->date_fin" requis />
            </div>
            @unless ($annee->exists)
                <x-selection nom="decoupage" label="Découpage de l'année" requis :valeur="'trimestres'"
                    :options="['trimestres' => '3 trimestres', 'semestres' => '2 semestres', 'aucun' => 'Je créerai les périodes moi-même']"
                    aide="Les dates des périodes sont proposées automatiquement et restent modifiables." />
            @endunless
            <div class="flex gap-2">
                <x-bouton>Enregistrer</x-bouton>
                <x-bouton variante="secondaire" :href="route('annees.index')">Annuler</x-bouton>
            </div>
        </form>
    </x-carte>
</x-layouts.app>
