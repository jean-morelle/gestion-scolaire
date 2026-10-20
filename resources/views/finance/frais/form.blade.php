<x-layouts.app :titre="$grille->exists ? 'Modifier la grille' : 'Nouvelle grille de frais'">
    <x-entete :titre="$grille->exists ? 'Modifier la grille' : 'Nouvelle grille de frais'" :retour="route('grilles-frais.index', ['annee' => $grille->annee_scolaire_id])">
        @if ($grille->exists)
            <form method="POST" action="{{ route('grilles-frais.destroy', $grille) }}" data-confirm="Supprimer cette grille ? Les frais déjà facturés aux élèves sont conservés.">
                @csrf @method('DELETE') <x-bouton variante="danger">Supprimer</x-bouton>
            </form>
        @endif
    </x-entete>

    @php
        $tranches = old('tranches', $grille->exists ? $grille->tranches->map(fn ($t) => ['libelle' => $t->libelle, 'montant' => $t->montant, 'date_limite' => $t->date_limite->format('Y-m-d')])->all() : []);
    @endphp
    <form method="POST" action="{{ $grille->exists ? route('grilles-frais.update', $grille) : route('grilles-frais.store') }}" class="max-w-3xl space-y-6"
          x-data="{ montant: @js((int) old('montant', $grille->montant)), tranches: @js(array_values($tranches)),
                    get somme() { return this.tranches.reduce((s, t) => s + (parseInt(String(t.montant).replace(/\D/g, '')) || 0), 0) } }">
        @csrf
        @if ($grille->exists) @method('PUT') @endif

        <x-carte titre="Frais">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-selection nom="annee_scolaire_id" label="Année scolaire" :options="$annees" :valeur="$grille->annee_scolaire_id" requis />
                <x-selection nom="niveau_id" label="Niveau" :options="$niveaux" :valeur="$grille->niveau_id" vide="— Choisir —" requis />
                <x-selection nom="type_frais_id" label="Type de frais" :options="$types" :valeur="$grille->type_frais_id" vide="— Choisir —" requis />
                <x-champ nom="categorie" label="Catégorie d'élèves" :valeur="$grille->categorie" list="categories" maxlength="30"
                    aide="Vide = tous les élèves. Ex. « affecte » pour un tarif propre à une catégorie." />
                <datalist id="categories">@foreach ($categories as $c)<option value="{{ $c }}">@endforeach</datalist>
                <div>
                    <label for="montant" class="mb-1 block text-sm font-medium text-slate-700">Montant total ({{ etablissement()->devise }}) <span class="text-red-500">*</span></label>
                    <input id="montant" type="number" name="montant" min="1" step="1" x-model.number="montant" required @class(['champ', 'champ-erreur' => $errors->has('montant')])>
                    @error('montant')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>
        </x-carte>

        <x-carte titre="Échéancier (tranches)">
            <p class="mb-3 text-sm text-slate-600">Sans tranche, le montant est dû à la date d'inscription. Avec des tranches, leur somme doit égaler le montant total.</p>
            <template x-for="(t, i) in tranches" :key="i">
                <div class="mb-2 grid grid-cols-12 gap-2">
                    <input type="text" :name="`tranches[${i}][libelle]`" x-model="t.libelle" placeholder="Ex. 1re tranche" class="champ col-span-4" required aria-label="Libellé de la tranche">
                    <input type="number" :name="`tranches[${i}][montant]`" x-model="t.montant" min="1" placeholder="Montant" class="champ col-span-3" required aria-label="Montant de la tranche">
                    <input type="date" :name="`tranches[${i}][date_limite]`" x-model="t.date_limite" class="champ col-span-4" required aria-label="Date limite">
                    <button type="button" @click="tranches.splice(i, 1)" class="col-span-1 text-slate-400 hover:text-red-600" aria-label="Retirer la tranche"><x-icone nom="fermer" class="size-5" /></button>
                </div>
            </template>
            <div class="mt-3 flex flex-wrap items-center gap-3">
                <x-bouton type="button" variante="secondaire" petit icone="plus" x-on:click="tranches.push({ libelle: (tranches.length + 1) + (tranches.length ? 'e' : 're') + ' tranche', montant: '', date_limite: '' })">Ajouter une tranche</x-bouton>
                <span x-show="tranches.length" class="text-sm" :class="somme === montant ? 'text-emerald-700' : 'text-red-700'"
                      x-text="'Somme des tranches : ' + somme.toLocaleString('fr-FR') + ' / ' + (montant || 0).toLocaleString('fr-FR')"></span>
            </div>
            @error('tranches')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
        </x-carte>

        <p class="text-xs text-slate-500">Modifier une grille ne change pas les frais déjà facturés aux élèves (copie au moment de l'attribution). Utilisez une remise pour un cas individuel.</p>
        <div class="flex gap-2">
            <x-bouton>Enregistrer</x-bouton>
            <x-bouton variante="secondaire" :href="route('grilles-frais.index', ['annee' => $grille->annee_scolaire_id])">Annuler</x-bouton>
        </div>
    </form>
</x-layouts.app>
