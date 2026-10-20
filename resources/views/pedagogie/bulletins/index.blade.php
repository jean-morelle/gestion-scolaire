<x-layouts.app titre="Bulletins">
    <x-entete titre="Bulletins" :sous-titre="$annee ? 'Publication des résultats par classe et par période · '.$annee->libelle : 'Aucune année scolaire'" />

    @if ($annees->isNotEmpty())
        <form method="GET" class="mb-4">
            <x-selection nom="annee" label="Année scolaire" :options="$annees->pluck('libelle', 'id')" :valeur="$annee?->id" class="w-48" onchange="this.form.submit()" />
        </form>
    @endif

    <x-carte sans-marge>
        @if ($classes->isEmpty() || $periodes->isEmpty())
            <x-vide message="Il faut des classes et des périodes pour cette année." icone="diplome" />
        @else
            <x-tableau>
                <x-slot:entete><th>Classe</th>@foreach ($periodes as $p)<th>{{ $p->libelle }}</th>@endforeach</x-slot:entete>
                @foreach ($classes as $classe)
                    <tr>
                        <td class="font-medium">{{ $classe->nom }}</td>
                        @foreach ($periodes as $periode)
                            @php
                                $pub = $publications->get($classe->id.'-'.$periode->id);
                                $aValider = $brouillons->get($classe->id.'-'.$periode->id)?->total ?? 0;
                            @endphp
                            <td>
                                <a href="{{ route('bulletins.classe', [$classe, $periode]) }}" class="group inline-flex flex-col">
                                    @if ($pub && ! $pub->depubliee_at)
                                        <x-badge couleur="vert">Publiés le {{ $pub->publiee_at->format('d/m') }}</x-badge>
                                    @elseif ($pub)
                                        <x-badge couleur="violet">Retirés pour correction</x-badge>
                                    @else
                                        <x-badge>Non publiés</x-badge>
                                    @endif
                                    @if ($aValider && ! ($pub && ! $pub->depubliee_at))
                                        <span class="mt-0.5 text-xs text-amber-700">{{ $aValider }} évaluation(s) à valider</span>
                                    @endif
                                    <span class="mt-0.5 text-xs text-marque-700 group-hover:underline">Ouvrir</span>
                                </a>
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </x-tableau>
        @endif
    </x-carte>
</x-layouts.app>
