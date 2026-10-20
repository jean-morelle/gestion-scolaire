<x-layouts.app :titre="$eleve->exists ? $eleve->nom_complet : 'Nouvel élève'">
    <x-entete :titre="$eleve->exists ? 'Modifier le dossier de '.$eleve->nom_complet : 'Nouvel élève'"
              :sous-titre="$eleve->exists ? 'Matricule '.$eleve->matricule : 'Le matricule est attribué automatiquement à l\'enregistrement.'"
              :retour="$eleve->exists ? route('eleves.show', $eleve) : route('eleves.index')" />

    <form method="POST" enctype="multipart/form-data" action="{{ $eleve->exists ? route('eleves.update', $eleve) : route('eleves.store') }}" class="max-w-4xl space-y-6">
        @csrf
        @if ($eleve->exists) @method('PUT') @endif

        <x-carte titre="Identité de l'élève">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-champ nom="nom" label="Nom" :valeur="$eleve->nom" requis maxlength="100" autocomplete="off" />
                <x-champ nom="prenoms" label="Prénoms" :valeur="$eleve->prenoms" requis maxlength="150" autocomplete="off" />
                <x-selection nom="sexe" label="Sexe" :options="\App\Enums\Sexe::options()" :valeur="$eleve->sexe" vide="— Choisir —" requis />
                <x-champ nom="date_naissance" label="Date de naissance" type="date" :valeur="$eleve->date_naissance" requis />
                <x-champ nom="lieu_naissance" label="Lieu de naissance" :valeur="$eleve->lieu_naissance" maxlength="100" />
                <x-champ nom="nationalite" label="Nationalité" :valeur="$eleve->nationalite" maxlength="50" />
                <x-champ nom="adresse" label="Adresse" :valeur="$eleve->adresse" maxlength="255" class="sm:col-span-2" />
                <x-champ nom="matricule_national" label="Matricule national" :valeur="$eleve->matricule_national" maxlength="30" aide="Facultatif : identifiant attribué par le ministère, s'il existe." />
                <x-champ nom="photo" label="Photo" type="file" accept="image/jpeg,image/png" aide="JPG ou PNG, 2 Mo au maximum." />
                <x-zone-texte nom="observations" label="Observations" :valeur="$eleve->observations" class="sm:col-span-2" />
            </div>
        </x-carte>

        @unless ($eleve->exists)
            <x-carte titre="Responsable légal">
                <div x-data="{ mode: @js(old('responsable.mode', 'nouveau')) }" class="space-y-4">
                    <div class="flex flex-wrap gap-4 text-sm">
                        <label class="flex items-center gap-2"><input type="radio" name="responsable[mode]" value="nouveau" x-model="mode" class="text-marque-600"> Nouveau responsable</label>
                        <label class="flex items-center gap-2"><input type="radio" name="responsable[mode]" value="existant" x-model="mode" class="text-marque-600"> Responsable déjà enregistré (frère ou sœur dans l'établissement)</label>
                    </div>

                    <div x-show="mode === 'existant'" x-cloak>
                        <x-champ nom="responsable[telephone_existant]" label="Téléphone du responsable déjà enregistré" placeholder="Ex. 90000000" />
                    </div>

                    <div x-show="mode === 'nouveau'" class="grid gap-4 sm:grid-cols-2">
                        <x-champ nom="responsable[nom]" label="Nom" maxlength="100" />
                        <x-champ nom="responsable[prenoms]" label="Prénoms" maxlength="150" />
                        <x-champ nom="responsable[telephone]" label="Téléphone" placeholder="Ex. 90000000" aide="Obligatoire : sert de contact et d'identifiant du compte parent." />
                        <x-champ nom="responsable[telephone_2]" label="Second téléphone" />
                        <x-champ nom="responsable[email]" label="E-mail" type="email" />
                        <x-champ nom="responsable[profession]" label="Profession" maxlength="100" />
                        <x-champ nom="responsable[adresse]" label="Adresse" maxlength="255" class="sm:col-span-2" />
                    </div>

                    <x-selection nom="responsable[lien]" label="Lien avec l'élève" :options="\App\Enums\LienParente::options()" vide="— Choisir —" requis class="sm:w-1/2" />
                </div>
            </x-carte>

            <x-carte titre="Inscription (facultatif)">
                @if ($classes->isEmpty())
                    <p class="text-sm text-slate-500">Aucune classe ouverte : l'élève pourra être inscrit plus tard depuis son dossier.</p>
                @else
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-selection nom="classe_id" label="Classe" vide="— Ne pas inscrire maintenant —"
                            :options="$classes->mapWithKeys(fn ($c) => [$c->id => $c->nom.' ('.$c->anneeScolaire->libelle.')'])" />
                        <x-champ nom="date_inscription" label="Date d'inscription" type="date" :valeur="now()->toDateString()" />
                        <x-champ nom="categorie_frais" label="Catégorie de frais" list="categories-frais" maxlength="30"
                            aide="Facultatif : catégorie définie dans les grilles de frais (ex. affecté, non affecté)." />
                        <datalist id="categories-frais">@foreach ($categories as $categorie)<option value="{{ $categorie }}">@endforeach</datalist>
                        <x-case nom="redoublant" label="Redoublant" class="self-center" />
                    </div>
                @endif
            </x-carte>
        @endunless

        <div class="flex gap-2">
            <x-bouton>Enregistrer</x-bouton>
            <x-bouton variante="secondaire" :href="$eleve->exists ? route('eleves.show', $eleve) : route('eleves.index')">Annuler</x-bouton>
        </div>
    </form>
</x-layouts.app>
