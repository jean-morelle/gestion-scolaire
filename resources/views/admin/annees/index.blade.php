<x-layouts.app titre="Années scolaires">
    <x-entete titre="Années scolaires" sous-titre="Une seule année est active : c'est elle qui est utilisée par défaut dans toute l'application.">
        <x-bouton :href="route('annees.create')" icone="plus">Nouvelle année</x-bouton>
    </x-entete>

    <x-carte sans-marge>
        @if ($annees->isEmpty())
            <x-vide message="Aucune année scolaire. Commencez par en créer une." icone="calendrier">
                <x-bouton :href="route('annees.create')" icone="plus">Créer l'année scolaire</x-bouton>
            </x-vide>
        @else
            <x-tableau>
                <x-slot:entete>
                    <th>Année</th><th>Dates</th><th>Périodes</th><th>Classes</th><th>Inscriptions</th><th>Statut</th><th></th>
                </x-slot:entete>
                @foreach ($annees as $annee)
                    <tr>
                        <td class="font-semibold">
                            <a href="{{ route('annees.show', $annee) }}" class="lien">{{ $annee->libelle }}</a>
                            @if ($annee->est_active)<x-badge couleur="vert" class="ml-1">Active</x-badge>@endif
                        </td>
                        <td>{{ date_fr($annee->date_debut) }} → {{ date_fr($annee->date_fin) }}</td>
                        <td>{{ $annee->periodes_count }}</td>
                        <td>{{ $annee->classes_count }}</td>
                        <td>{{ $annee->inscriptions_count }}</td>
                        <td><x-badge :couleur="$annee->estCloturee() ? 'gris' : 'bleu'">{{ $annee->statut->libelle() }}</x-badge></td>
                        <td class="text-right"><a href="{{ route('annees.show', $annee) }}" class="lien">Gérer</a></td>
                    </tr>
                @endforeach
            </x-tableau>
        @endif
    </x-carte>
</x-layouts.app>
