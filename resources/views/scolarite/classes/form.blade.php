<x-layouts.app :titre="$classe->exists ? 'Classe '.$classe->nom : 'Nouvelle classe'">
    <x-entete :titre="$classe->exists ? 'Modifier la classe '.$classe->nom : 'Nouvelle classe'"
              :retour="$classe->exists ? route('classes.show', $classe) : route('classes.index', ['annee' => $classe->annee_scolaire_id])">
        @if ($classe->exists)
            <form method="POST" action="{{ route('classes.destroy', $classe) }}" data-confirm="Supprimer définitivement la classe {{ $classe->nom }} ?">
                @csrf @method('DELETE')
                <x-bouton variante="danger">Supprimer</x-bouton>
            </form>
        @endif
    </x-entete>

    <x-carte class="max-w-3xl">
        <form method="POST" action="{{ $classe->exists ? route('classes.update', $classe) : route('classes.store') }}" class="space-y-5">
            @csrf
            @if ($classe->exists) @method('PUT') @endif
            <div class="grid gap-4 sm:grid-cols-2">
                <x-selection nom="annee_scolaire_id" label="Année scolaire" :options="$annees" :valeur="$classe->annee_scolaire_id" vide="— Choisir —" requis
                    :disabled="$classe->exists && $classe->inscriptions()->exists()" />
                <x-selection nom="niveau_id" label="Niveau" :options="$niveaux" :valeur="$classe->niveau_id" vide="— Choisir —" requis />
                <x-champ nom="nom" label="Nom de la classe" :valeur="$classe->nom" placeholder="Ex. 6e A" requis maxlength="30" />
                <x-champ nom="serie" label="Série (lycée)" :valeur="$classe->serie" placeholder="Ex. C, D, A4" maxlength="10" />
                <x-champ nom="capacite" label="Capacité" type="number" min="1" max="500" :valeur="$classe->capacite"
                    aide="Au-delà, un avertissement s'affiche à l'inscription (non bloquant)." />
                <x-selection nom="professeur_principal_id" label="Professeur principal" :options="$enseignants" :valeur="$classe->professeur_principal_id" vide="— Aucun —" />
            </div>
            @if ($classe->exists && $classe->inscriptions()->exists())
                <input type="hidden" name="annee_scolaire_id" value="{{ $classe->annee_scolaire_id }}">
                <p class="text-xs text-slate-500">L'année ne peut plus être changée : des élèves sont inscrits dans cette classe.</p>
            @endif
            <div class="flex gap-2">
                <x-bouton>Enregistrer</x-bouton>
                <x-bouton variante="secondaire" :href="route('classes.index', ['annee' => $classe->annee_scolaire_id])">Annuler</x-bouton>
            </div>
        </form>
    </x-carte>
</x-layouts.app>
