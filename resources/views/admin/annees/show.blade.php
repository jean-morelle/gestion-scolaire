<x-layouts.app :titre="'Année '.$annee->libelle">
    <x-entete :titre="'Année scolaire '.$annee->libelle" :sous-titre="date_fr($annee->date_debut).' → '.date_fr($annee->date_fin).' · '.$annee->statut->libelle()" :retour="route('annees.index')">
        @unless ($annee->estCloturee())
            <x-bouton variante="secondaire" :href="route('annees.edit', $annee)">Modifier</x-bouton>
            @unless ($annee->est_active)
                <form method="POST" action="{{ route('annees.activer', $annee) }}" data-confirm="Faire de {{ $annee->libelle }} l'année active ? L'année active actuelle sera désactivée.">
                    @csrf
                    <x-bouton variante="succes">Définir comme année active</x-bouton>
                </form>
            @endunless
            <form method="POST" action="{{ route('annees.cloturer', $annee) }}" data-confirm="Clôturer {{ $annee->libelle }} ? L'année et toutes ses périodes passeront en lecture seule.">
                @csrf
                <x-bouton variante="danger">Clôturer l'année</x-bouton>
            </form>
        @endunless
    </x-entete>

    <div class="grid gap-6 lg:grid-cols-3">
        <x-carte titre="Périodes" sans-marge class="lg:col-span-2">
            @if ($annee->periodes->isEmpty())
                <x-vide message="Aucune période. Ajoutez les trimestres ou semestres." icone="calendrier" />
            @else
                <x-tableau>
                    <x-slot:entete><th>#</th><th>Période</th><th>Dates</th><th>Statut</th><th></th></x-slot:entete>
                    @foreach ($annee->periodes as $periode)
                        <tr>
                            <td>{{ $periode->ordre }}</td>
                            <td class="font-medium">{{ $periode->libelle }}</td>
                            <td>{{ date_fr($periode->date_debut) }} → {{ date_fr($periode->date_fin) }}</td>
                            <td><x-badge :couleur="$periode->estOuverte() ? 'vert' : 'gris'">{{ $periode->statut->libelle() }}</x-badge></td>
                            <td class="text-right whitespace-nowrap">
                                @unless ($annee->estCloturee())
                                    <details class="inline-block text-left">
                                        <summary class="lien cursor-pointer list-none">Modifier</summary>
                                        <form method="POST" action="{{ route('periodes.update', [$annee, $periode]) }}" class="mt-2 w-80 space-y-3 rounded-xl border bg-white p-4 shadow-lg">
                                            @csrf @method('PUT')
                                            <x-champ nom="libelle" label="Libellé" :valeur="$periode->libelle" requis />
                                            <x-champ nom="ordre" label="Ordre" type="number" :valeur="$periode->ordre" requis />
                                            <x-champ nom="date_debut" label="Début" type="date" :valeur="$periode->date_debut" requis />
                                            <x-champ nom="date_fin" label="Fin" type="date" :valeur="$periode->date_fin" requis />
                                            <x-bouton petit>Enregistrer</x-bouton>
                                        </form>
                                    </details>
                                    @if ($periode->estOuverte())
                                        <form method="POST" action="{{ route('periodes.cloturer', [$annee, $periode]) }}" class="ml-3 inline" data-confirm="Clôturer {{ $periode->libelle }} ? Plus aucune note ni présence ne pourra y être saisie.">
                                            @csrf <button class="text-sm font-medium text-red-600 hover:underline">Clôturer</button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('periodes.rouvrir', [$annee, $periode]) }}" class="ml-3 inline" data-confirm="Rouvrir {{ $periode->libelle }} ?">
                                            @csrf <button class="text-sm font-medium text-marque-700 hover:underline">Rouvrir</button>
                                        </form>
                                    @endif
                                @endunless
                            </td>
                        </tr>
                    @endforeach
                </x-tableau>
            @endif
        </x-carte>

        @unless ($annee->estCloturee())
            <x-carte titre="Ajouter une période">
                <form method="POST" action="{{ route('periodes.store', $annee) }}" class="space-y-3">
                    @csrf
                    <x-champ nom="libelle" label="Libellé" requis placeholder="1er trimestre" />
                    <x-champ nom="ordre" label="Ordre" type="number" :valeur="$annee->periodes->count() + 1" requis />
                    <x-champ nom="date_debut" label="Début" type="date" requis />
                    <x-champ nom="date_fin" label="Fin" type="date" requis />
                    <x-bouton icone="plus">Ajouter</x-bouton>
                </form>
            </x-carte>
        @endunless
    </div>
</x-layouts.app>
