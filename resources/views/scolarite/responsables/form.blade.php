<x-layouts.app :titre="$responsable->exists ? $responsable->nom_complet : 'Ajouter un responsable'">
    <x-entete :titre="$responsable->exists ? 'Responsable : '.$responsable->nom_complet : 'Ajouter un responsable légal'"
              :sous-titre="'Élève : '.$eleve->nom_complet.' ('.$eleve->matricule.')'" :retour="route('eleves.show', $eleve)" />

    <form method="POST" action="{{ $responsable->exists ? route('responsables.update', [$eleve, $responsable]) : route('responsables.store', $eleve) }}" class="max-w-3xl space-y-6">
        @csrf
        @if ($responsable->exists) @method('PUT') @endif

        <x-carte titre="Fiche du responsable">
            <div x-data="{ mode: @js(old('mode', 'nouveau')) }" class="space-y-4">
                @unless ($responsable->exists)
                    <div class="flex flex-wrap gap-4 text-sm">
                        <label class="flex items-center gap-2"><input type="radio" name="mode" value="nouveau" x-model="mode" class="text-marque-600"> Nouveau responsable</label>
                        <label class="flex items-center gap-2"><input type="radio" name="mode" value="existant" x-model="mode" class="text-marque-600"> Responsable déjà enregistré</label>
                    </div>
                    <div x-show="mode === 'existant'" x-cloak>
                        <x-champ nom="telephone_existant" label="Téléphone du responsable déjà enregistré" placeholder="Ex. 90000000"
                            aide="Le responsable d'un frère ou d'une sœur est retrouvé par son numéro de téléphone." />
                    </div>
                @else
                    <input type="hidden" name="mode" value="nouveau">
                @endunless

                <div x-show="mode === 'nouveau'" class="grid gap-4 sm:grid-cols-2">
                    <x-champ nom="nom" label="Nom" :valeur="$responsable->nom" maxlength="100" :requis="$responsable->exists" />
                    <x-champ nom="prenoms" label="Prénoms" :valeur="$responsable->prenoms" maxlength="150" :requis="$responsable->exists" />
                    <x-champ nom="telephone" label="Téléphone" :valeur="$responsable->telephone" :requis="$responsable->exists" placeholder="Ex. 90000000" />
                    <x-champ nom="telephone_2" label="Second téléphone" :valeur="$responsable->telephone_2" />
                    <x-champ nom="email" label="E-mail" type="email" :valeur="$responsable->email" />
                    <x-champ nom="profession" label="Profession" :valeur="$responsable->profession" maxlength="100" />
                    <x-champ nom="adresse" label="Adresse" :valeur="$responsable->adresse" maxlength="255" class="sm:col-span-2" />
                </div>
                @if ($responsable->exists && $responsable->user)
                    <p class="rounded-lg bg-marque-50 px-3 py-2 text-sm text-marque-900">Ce responsable a un compte parent ({{ $responsable->user->identifiant() }}). Modifier le téléphone ici ne change pas l'identifiant de connexion.</p>
                @endif
            </div>
        </x-carte>

        <x-carte titre="Lien avec {{ $eleve->prenoms }}">
            <div class="grid gap-4 sm:grid-cols-3">
                <x-selection nom="lien" label="Lien de parenté" :options="$liens" :valeur="$pivot['lien']" vide="— Choisir —" requis />
                <x-case nom="est_contact_principal" label="Contact principal" :coche="(bool) $pivot['est_contact_principal']" class="self-center" />
                <x-case nom="est_payeur" label="Payeur des frais" :coche="(bool) $pivot['est_payeur']" class="self-center" />
            </div>
            @if (! empty($autresEnfants) && $autresEnfants->isNotEmpty())
                <p class="mt-4 text-sm text-slate-500">Également responsable de : {{ $autresEnfants->map->nom_complet->join(', ') }}. Les modifications de la fiche s'appliquent à tous ses enfants.</p>
            @endif
        </x-carte>

        <div class="flex gap-2">
            <x-bouton>Enregistrer</x-bouton>
            <x-bouton variante="secondaire" :href="route('eleves.show', $eleve)">Annuler</x-bouton>
        </div>
    </form>
</x-layouts.app>
