<x-layouts.app :titre="$enseignant->exists ? $enseignant->user->name : 'Nouvel enseignant'">
    <x-entete :titre="$enseignant->exists ? 'Modifier : '.$enseignant->user->name : 'Nouvel enseignant'"
              :retour="$enseignant->exists ? route('enseignants.show', $enseignant) : route('enseignants.index')" />

    <form method="POST" action="{{ $enseignant->exists ? route('enseignants.update', $enseignant) : route('enseignants.store') }}" class="max-w-3xl space-y-6">
        @csrf
        @if ($enseignant->exists) @method('PUT') @endif

        <x-carte titre="Compte de connexion">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-champ nom="name" label="Nom complet" :valeur="$enseignant->user?->name" requis class="sm:col-span-2" />
                <x-champ nom="telephone" label="Téléphone" :valeur="$enseignant->user?->telephone" placeholder="Ex. 90000000" />
                <x-champ nom="email" label="E-mail" type="email" :valeur="$enseignant->user?->email" aide="Téléphone ou e-mail : au moins l'un des deux (identifiant de connexion)." />
            </div>
            @unless ($enseignant->exists)
                <p class="mt-4 rounded-lg bg-marque-50 px-3 py-2 text-sm text-marque-900">Un mot de passe temporaire sera généré et affiché une seule fois ; l'enseignant devra le changer à sa première connexion.</p>
            @endunless
        </x-carte>

        <x-carte titre="Fiche professionnelle">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-champ nom="matricule" label="Matricule" :valeur="$enseignant->matricule" maxlength="30" />
                <x-champ nom="specialite" label="Spécialité" :valeur="$enseignant->specialite" maxlength="100" placeholder="Ex. Mathématiques" />
                <x-champ nom="diplome" label="Diplôme" :valeur="$enseignant->diplome" maxlength="100" placeholder="Ex. CAPES, Licence" />
                <x-champ nom="date_embauche" label="Date d'embauche" type="date" :valeur="$enseignant->date_embauche" />
            </div>

            <fieldset class="mt-5">
                <legend class="mb-2 text-sm font-medium text-slate-700">Matières enseignées</legend>
                @php $choisies = array_map('intval', old('matieres', $enseignant->exists ? $enseignant->matieres->pluck('id')->all() : [])); @endphp
                <div class="grid gap-2 sm:grid-cols-3">
                    @foreach ($matieres as $matiere)
                        <label class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm hover:bg-slate-50">
                            <input type="checkbox" name="matieres[]" value="{{ $matiere->id }}" @checked(in_array($matiere->id, $choisies, true)) class="size-4 rounded border-slate-300 text-marque-600">
                            {{ $matiere->libelle }}
                        </label>
                    @endforeach
                </div>
                <p class="mt-1 text-xs text-slate-500">Sert à guider les affectations : une affectation hors de ces matières reste possible, avec un avertissement.</p>
            </fieldset>

            <x-case nom="est_actif" label="Enseignant en activité" :coche="$enseignant->est_actif" class="mt-5"
                aide="Un enseignant inactif ne peut plus recevoir de nouvelle affectation. Son compte de connexion est géré par l'administration." />
        </x-carte>

        <div class="flex gap-2">
            <x-bouton>Enregistrer</x-bouton>
            <x-bouton variante="secondaire" :href="$enseignant->exists ? route('enseignants.show', $enseignant) : route('enseignants.index')">Annuler</x-bouton>
        </div>
    </form>
</x-layouts.app>
