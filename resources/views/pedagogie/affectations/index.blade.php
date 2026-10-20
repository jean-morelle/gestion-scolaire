<x-layouts.app titre="Affectations">
    <x-entete titre="Affectations" :sous-titre="$annee ? 'Matières, enseignants et coefficients par classe · '.$annee->libelle : 'Aucune année scolaire'" />

    @if ($annees->isNotEmpty())
        <form method="GET" class="mb-4 flex flex-wrap items-end gap-2">
            <x-selection nom="annee" label="Année scolaire" :options="$annees->pluck('libelle', 'id')" :valeur="$annee?->id" class="w-48" onchange="this.form.submit()" />
            <noscript><x-bouton variante="secondaire">Afficher</x-bouton></noscript>
        </form>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <x-carte titre="Classes" sans-marge class="lg:col-span-2">
            @if ($classes->isEmpty())
                <x-vide message="Aucune classe pour cette année." icone="classe" />
            @else
                <x-tableau>
                    <x-slot:entete><th>Classe</th><th class="text-right">Matières</th><th class="text-right">Total coef.</th><th>Postes</th><th></th></x-slot:entete>
                    @foreach ($classes as $classe)
                        <tr>
                            <td class="font-medium">{{ $classe->nom }} <span class="text-xs text-slate-500">{{ $classe->niveau->libelle }}</span></td>
                            <td class="text-right">{{ $classe->affectations_count }}</td>
                            <td class="text-right">{{ note_fr($classe->affectations->sum(fn ($a) => (float) $a->coefficient)) }}</td>
                            <td>
                                @if ($classe->affectations_count === 0)
                                    <x-badge couleur="orange">À configurer</x-badge>
                                @elseif ($classe->non_pourvues)
                                    <x-badge couleur="rouge">{{ $classe->non_pourvues }} non pourvu(s)</x-badge>
                                @else
                                    <x-badge couleur="vert">Complet</x-badge>
                                @endif
                            </td>
                            <td class="text-right"><a href="{{ route('affectations.classe', $classe) }}" class="lien">Gérer</a></td>
                        </tr>
                    @endforeach
                </x-tableau>
            @endif
        </x-carte>

        <x-carte titre="Enseignants actifs" sans-marge>
            @if ($enseignants->isEmpty())
                <x-vide message="Aucun enseignant actif." icone="enseignant" />
            @else
                <ul class="divide-y divide-slate-100">
                    @foreach ($enseignants as $enseignant)
                        <li class="flex items-center justify-between px-5 py-2.5 text-sm">
                            @can('enseignants.gerer')
                                <a href="{{ route('enseignants.show', $enseignant) }}" class="hover:underline">{{ $enseignant->user->name }}</a>
                            @else
                                <span>{{ $enseignant->user->name }}</span>
                            @endcan
                            @if ($enseignant->nb_affectations)
                                <x-badge couleur="bleu">{{ $enseignant->nb_affectations }} affectation(s)</x-badge>
                            @else
                                <x-badge couleur="orange">Sans affectation</x-badge>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-carte>
    </div>
</x-layouts.app>
