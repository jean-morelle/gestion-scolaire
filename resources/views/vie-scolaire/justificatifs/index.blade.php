<x-layouts.app titre="Justificatifs">
    <x-entete titre="Justificatifs d'absence" sous-titre="Un justificatif excuse toutes les absences de sa période, y compris celles saisies plus tard.">
        <x-bouton icone="plus" :href="route('justificatifs.create')">Nouveau justificatif</x-bouton>
    </x-entete>

    <form method="GET" class="mb-4 flex flex-wrap items-end gap-2">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Nom de l'élève" class="champ w-56" aria-label="Rechercher">
        <select name="classe" class="champ w-44" aria-label="Classe">
            <option value="">Toutes les classes</option>
            @foreach ($classes as $c)
                <option value="{{ $c->id }}" @selected(request('classe') == $c->id)>{{ $c->nom }}</option>
            @endforeach
        </select>
        <x-bouton variante="secondaire" icone="recherche">Filtrer</x-bouton>
    </form>

    <div class="grid gap-6 lg:grid-cols-5">
        <x-carte titre="Absences non justifiées récentes" sans-marge class="lg:col-span-2">
            @if ($aJustifier->isEmpty())
                <x-vide message="Aucune absence à justifier." icone="coche" />
            @else
                <ul class="divide-y divide-slate-100">
                    @foreach ($aJustifier as $presence)
                        <li class="flex items-center justify-between gap-3 px-5 py-2.5 text-sm">
                            <span>
                                <span class="font-medium">{{ $presence->inscription->eleve->nom_complet }}</span>
                                <span class="block text-xs text-slate-500">{{ $presence->inscription->classe->nom }} · {{ date_fr($presence->appel->date_appel) }} {{ mb_strtolower($presence->appel->creneau->libelle()) }}</span>
                            </span>
                            <a href="{{ route('justificatifs.create', ['inscription' => $presence->inscription_id, 'date' => $presence->appel->date_appel->toDateString()]) }}" class="lien shrink-0">Justifier</a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-carte>

        <x-carte titre="Justificatifs enregistrés" sans-marge class="lg:col-span-3">
            @if ($justificatifs->isEmpty())
                <x-vide message="Aucun justificatif pour l'année en cours." icone="trombone" />
            @else
                <x-tableau>
                    <x-slot:entete><th>Élève</th><th>Période</th><th>Motif</th><th class="text-right">Absences</th><th></th></x-slot:entete>
                    @foreach ($justificatifs as $j)
                        <tr>
                            <td class="font-medium">{{ $j->inscription->eleve->nom_complet }}<span class="block text-xs text-slate-500">{{ $j->inscription->classe->nom }}</span></td>
                            <td class="whitespace-nowrap">{{ date_fr($j->date_debut) }}@unless ($j->date_debut->eq($j->date_fin)) → {{ date_fr($j->date_fin) }}@endunless</td>
                            <td>{{ $j->motif }}<span class="block text-xs text-slate-500">{{ $j->auteur?->name }}</span></td>
                            <td class="text-right">{{ $j->presences_count }}</td>
                            <td class="whitespace-nowrap text-right">
                                @if ($j->fichier_chemin)<a href="{{ route('justificatifs.fichier', $j) }}" class="lien">Pièce</a>@endif
                                <form method="POST" action="{{ route('justificatifs.destroy', $j) }}" class="ml-3 inline" data-confirm="Supprimer ce justificatif ? Les absences qu'il couvre redeviendront non justifiées.">
                                    @csrf @method('DELETE') <button class="text-sm font-medium text-red-600 hover:underline">Supprimer</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </x-tableau>
                <div class="p-4">{{ $justificatifs->links() }}</div>
            @endif
        </x-carte>
    </div>
</x-layouts.app>
