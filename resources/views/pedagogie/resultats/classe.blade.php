<x-layouts.app titre="Résultats des classes">
    @php $d = (int) etablissement()->parametre('decimales', 2); @endphp
    <x-entete titre="Résultats des classes" :sous-titre="$classe && $periode ? $classe->nom.' · '.$periode->libelle.' · calcul provisoire avant publication des bulletins' : null">
        @if ($resultats)
            <x-bouton variante="secondaire" icone="imprimer" type="button" onclick="window.print()">Imprimer</x-bouton>
            <x-bouton variante="secondaire" icone="telecharger" :href="route('resultats.classe', ['classe' => $classe->id, 'periode' => $periode->id, 'format' => 'csv'])">Exporter (CSV)</x-bouton>
        @endif
    </x-entete>

    <form method="GET" class="no-print mb-6 flex flex-wrap items-end gap-3">
        <x-selection nom="classe" label="Classe" :options="$classes->pluck('nom', 'id')" :valeur="$classe?->id" class="w-44" />
        <x-selection nom="periode" label="Période" :options="$periodes->pluck('libelle', 'id')" :valeur="$periode?->id" class="w-48" />
        <x-bouton variante="secondaire">Afficher</x-bouton>
    </form>

    @if (! $resultats)
        <x-carte><x-vide message="Choisissez une classe et une période (une année active avec des périodes est nécessaire)." icone="graphique" /></x-carte>
    @else
        <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-stat libelle="Moyenne de la classe" :valeur="note_fr($resultats['moyenne'], $d)" :detail="'de '.note_fr($resultats['min'], $d).' à '.note_fr($resultats['max'], $d)" icone="graphique" />
            <x-stat libelle="Taux de réussite" :valeur="$resultats['taux_reussite'] === null ? '—' : note_fr($resultats['taux_reussite'], 0).' %'" detail="moyenne ≥ 10" icone="diplome" couleur="vert" />
            <x-stat libelle="Élèves classés" :valeur="$resultats['classes'].' / '.$resultats['effectif']" icone="utilisateurs" couleur="violet" />
            <x-stat libelle="Évaluations en brouillon" :valeur="$resultats['evaluations_non_validees']" icone="crayon" :couleur="$resultats['evaluations_non_validees'] ? 'orange' : 'vert'" />
        </div>

        <x-carte sans-marge>
            @if ($resultats['eleves']->isEmpty())
                <x-vide message="Aucun élève dans la classe pour cette période." icone="utilisateurs" />
            @else
                <x-tableau>
                    <x-slot:entete>
                        <th>Rang</th><th>Élève</th>
                        @foreach ($resultats['matieres'] as $m)
                            <th class="text-right" title="{{ $m['affectation']->matiere->libelle }}">{{ $m['affectation']->matiere->code }}<span class="block font-normal normal-case">coef. {{ note_fr($m['affectation']->coefficient, 1) }}</span></th>
                        @endforeach
                        <th class="text-right">Moyenne</th><th>Mention</th>
                    </x-slot:entete>
                    @foreach ($resultats['eleves'] as $e)
                        <tr>
                            <td class="font-semibold">{{ $e['rang'] ?? '—' }}</td>
                            <td class="whitespace-nowrap font-medium">{{ $e['inscription']->eleve->nom_complet }}</td>
                            @foreach ($resultats['matieres'] as $matiereId => $m)
                                @php $moy = $e['matieres'][$matiereId]['moyenne'] ?? null; @endphp
                                <td @class(['text-right', 'text-red-700' => $moy !== null && $moy < 10])>{{ note_fr($moy, $d) }}</td>
                            @endforeach
                            <td @class(['text-right font-semibold', 'text-red-700' => $e['moyenne'] !== null && $e['moyenne'] < 10])>{{ note_fr($e['moyenne'], $d) }}</td>
                            <td>{{ $e['mention'] ?? '—' }}</td>
                        </tr>
                    @endforeach
                    <tr class="bg-slate-50 font-medium">
                        <td></td><td>Moyenne de la classe</td>
                        @foreach ($resultats['matieres'] as $m)
                            <td class="text-right">{{ note_fr($m['moyenne'], $d) }}</td>
                        @endforeach
                        <td class="text-right">{{ note_fr($resultats['moyenne'], $d) }}</td><td></td>
                    </tr>
                </x-tableau>
            @endif
        </x-carte>
    @endif
</x-layouts.app>
