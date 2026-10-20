<x-layouts.app titre="Emplois du temps">
    <x-entete titre="Emplois du temps" :sous-titre="$annee ? 'Année scolaire '.$annee->libelle : 'Aucune année scolaire active'" />

    <div class="grid gap-6 lg:grid-cols-2">
        <x-carte titre="Par classe" sans-marge>
            @if ($classes->isEmpty())
                <x-vide message="Aucune classe pour l'année active." icone="classe" />
            @else
                <x-tableau>
                    <x-slot:entete><th>Classe</th><th>Niveau</th><th class="text-right">Créneaux</th><th></th></x-slot:entete>
                    @foreach ($classes as $classe)
                        <tr>
                            <td class="font-medium">{{ $classe->nom }}</td>
                            <td>{{ $classe->niveau->libelle }}</td>
                            <td class="text-right">@if ($classe->nb_creneaux){{ $classe->nb_creneaux }}@else<x-badge couleur="orange">À construire</x-badge>@endif</td>
                            <td class="text-right"><a href="{{ route('emplois-du-temps.classe', $classe) }}" class="lien">{{ auth()->user()->can('emplois_du_temps.gerer') ? 'Construire' : 'Voir' }}</a></td>
                        </tr>
                    @endforeach
                </x-tableau>
            @endif
        </x-carte>

        <x-carte titre="Par enseignant" sans-marge>
            @if ($enseignants->isEmpty())
                <x-vide message="Aucun enseignant actif." icone="enseignant" />
            @else
                <ul class="divide-y divide-slate-100">
                    @foreach ($enseignants as $enseignant)
                        <li class="flex items-center justify-between px-5 py-2.5 text-sm">
                            <span>{{ $enseignant->user->name }} <span class="text-slate-500">{{ $enseignant->specialite }}</span></span>
                            <a href="{{ route('emplois-du-temps.enseignant', $enseignant) }}" class="lien">Voir</a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-carte>
    </div>
</x-layouts.app>
