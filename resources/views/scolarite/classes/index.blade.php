<x-layouts.app titre="Classes">
    <x-entete titre="Classes" :sous-titre="$annee ? 'Année scolaire '.$annee->libelle.' · '.$classes->count().' classe(s) · '.$classes->sum('effectif').' élève(s)' : 'Aucune année scolaire'">
        @can('classes.gerer')
            @if ($annee && ! $annee->estCloturee())
                <x-bouton :href="route('classes.create', ['annee' => $annee->id])" icone="plus">Nouvelle classe</x-bouton>
            @endif
        @endcan
    </x-entete>

    @if ($annees->isEmpty())
        <x-carte>
            <x-vide message="Créez d'abord une année scolaire dans l'administration." icone="calendrier" />
        </x-carte>
    @else
        <form method="GET" class="mb-4 flex flex-wrap items-end gap-2">
            <x-selection nom="annee" label="Année scolaire" :options="$annees->pluck('libelle', 'id')" :valeur="$annee?->id" class="w-48" onchange="this.form.submit()" />
            <noscript><x-bouton variante="secondaire">Afficher</x-bouton></noscript>
        </form>

        <x-carte sans-marge>
            @if ($classes->isEmpty())
                <x-vide message="Aucune classe pour cette année." icone="classe">
                    @can('classes.gerer')
                        @if ($anneesSource->isNotEmpty() && ! $annee->estCloturee())
                            <p class="mb-2 text-sm text-slate-500">Vous pouvez recopier les classes d'une autre année ci-dessous.</p>
                        @endif
                    @endcan
                </x-vide>
            @else
                <x-tableau>
                    <x-slot:entete><th>Classe</th><th>Niveau</th><th>Professeur principal</th><th class="text-right">Effectif</th><th class="text-right">Filles / Garçons</th><th>Capacité</th><th></th></x-slot:entete>
                    @foreach ($classes as $classe)
                        <tr>
                            <td class="font-medium"><a href="{{ route('classes.show', $classe) }}" class="lien">{{ $classe->nom }}</a>
                                @if ($classe->serie)<x-badge class="ml-1">Série {{ $classe->serie }}</x-badge>@endif</td>
                            <td>{{ $classe->niveau->libelle }}</td>
                            <td>{{ $classe->professeurPrincipal?->user->name ?? '—' }}</td>
                            <td class="text-right font-medium">{{ $classe->effectif }}</td>
                            <td class="text-right">{{ $classe->filles }} / {{ $classe->effectif - $classe->filles }}</td>
                            <td>
                                @if ($classe->capacite)
                                    @php $taux = $classe->effectif / $classe->capacite; @endphp
                                    <x-badge :couleur="$taux > 1 ? 'rouge' : ($taux >= 0.9 ? 'orange' : 'vert')">{{ $classe->effectif }}/{{ $classe->capacite }}</x-badge>
                                @else — @endif
                            </td>
                            <td class="text-right whitespace-nowrap">
                                <a href="{{ route('classes.show', $classe) }}" class="lien">Liste</a>
                                @can('classes.gerer')
                                    @unless ($annee->estCloturee())
                                        <a href="{{ route('classes.edit', $classe) }}" class="lien ml-3">Modifier</a>
                                    @endunless
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </x-tableau>
            @endif
        </x-carte>

        @can('classes.gerer')
            @if ($anneesSource->isNotEmpty() && ! $annee->estCloturee())
                <x-carte titre="Recopier les classes d'une autre année" class="mt-6 max-w-2xl">
                    <form method="POST" action="{{ route('classes.dupliquer') }}" class="flex flex-wrap items-end gap-3"
                          data-confirm="Recopier les classes (nom, niveau, série, capacité) vers {{ $annee->libelle }} ? Les classes déjà présentes sont ignorées.">
                        @csrf
                        <input type="hidden" name="cible_id" value="{{ $annee->id }}">
                        <x-selection nom="source_id" label="Année source" :options="$anneesSource->pluck('libelle', 'id')" class="w-48" requis />
                        <x-bouton variante="secondaire">Recopier vers {{ $annee->libelle }}</x-bouton>
                    </form>
                    <p class="mt-2 text-xs text-slate-500">Les élèves ne sont pas recopiés : utilisez la réinscription en lot.</p>
                </x-carte>
            @endif
        @endcan
    @endif
</x-layouts.app>
