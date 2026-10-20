<x-layouts.app titre="Statistiques scolaires">
    @php
        $total = $effectifs->sum('effectif');
        $filles = $effectifs->sum('filles');
        $releves = $assiduite->sum('releves');
        $exporter = fn ($type) => route('rapports.scolaires', array_merge(request()->query(), ['export' => $type]));
    @endphp
    <x-entete titre="Statistiques scolaires" :sous-titre="$annee ? 'Année '.$annee->libelle.' · résultats issus des bulletins publiés' : 'Aucune année scolaire'">
        <x-bouton variante="secondaire" icone="imprimer" type="button" onclick="window.print()">Imprimer</x-bouton>
    </x-entete>

    @if ($annees->isNotEmpty())
        <form method="GET" class="no-print mb-6">
            <x-selection nom="annee" label="Année scolaire" :options="$annees->pluck('libelle', 'id')" :valeur="$annee?->id" class="w-48" onchange="this.form.submit()" />
        </form>
    @endif

    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat libelle="Élèves inscrits" :valeur="$total" :detail="$filles.' filles · '.($total - $filles).' garçons'" icone="utilisateurs" />
        <x-stat libelle="Redoublants" :valeur="$effectifs->sum('redoublants')" :detail="$total ? note_fr($effectifs->sum('redoublants') / $total * 100, 1).' %' : null" icone="inscription" couleur="orange" />
        <x-stat libelle="Départs en cours d'année" :valeur="$effectifs->sum('partis')" icone="deconnexion" couleur="rouge" />
        <x-stat libelle="Taux d'absence" :valeur="$releves ? note_fr($assiduite->sum('absences') / $releves * 100, 1).' %' : '—'" :detail="$assiduite->sum('non_justifiees').' absence(s) non justifiée(s)'" icone="coche" couleur="violet" />
    </div>

    <x-carte titre="Effectifs par classe" sans-marge class="mb-6">
        <x-slot:actions><a href="{{ $exporter('effectifs') }}" class="lien text-sm no-print">CSV</a></x-slot:actions>
        @if ($effectifs->isEmpty())
            <x-vide message="Aucune classe." icone="classe" />
        @else
            <x-tableau>
                <x-slot:entete><th>Classe</th><th>Niveau</th><th class="text-right">Effectif</th><th class="text-right">Filles</th><th class="text-right">Garçons</th><th class="text-right">Redoublants</th><th class="text-right">Départs</th><th class="text-right">Remplissage</th></x-slot:entete>
                @foreach ($effectifs as $e)
                    <tr>
                        <td class="font-medium">{{ $e['classe'] }}</td><td>{{ $e['niveau'] }}</td>
                        <td class="text-right font-semibold">{{ $e['effectif'] }}</td><td class="text-right">{{ $e['filles'] }}</td>
                        <td class="text-right">{{ $e['effectif'] - $e['filles'] }}</td><td class="text-right">{{ $e['redoublants'] }}</td>
                        <td class="text-right">{{ $e['partis'] }}</td>
                        <td class="text-right">{{ $e['capacite'] ? note_fr($e['effectif'] / $e['capacite'] * 100, 0).' %' : '—' }}</td>
                    </tr>
                @endforeach
            </x-tableau>
        @endif
    </x-carte>

    <x-carte titre="Résultats par classe (bulletins publiés)" sans-marge class="mb-6">
        <x-slot:actions><a href="{{ $exporter('resultats') }}" class="lien text-sm no-print">CSV</a></x-slot:actions>
        @if ($periodes->isEmpty())
            <x-vide message="Aucune période définie." icone="diplome" />
        @else
            <x-tableau>
                <x-slot:entete><th>Classe</th>@foreach ($periodes as $p)<th class="text-right">{{ $p->libelle }}<span class="block font-normal normal-case">moy. · réussite</span></th>@endforeach</x-slot:entete>
                @foreach ($resultats as $r)
                    <tr>
                        <td class="font-medium">{{ $r['classe'] }}</td>
                        @foreach ($periodes as $p)
                            @php $s = $r['periodes'][$p->libelle] ?? null; @endphp
                            <td class="text-right">
                                @if ($s)
                                    <span @class(['font-semibold', 'text-red-700' => $s['moyenne'] < 10])>{{ note_fr($s['moyenne']) }}</span>
                                    <span class="text-slate-500">· {{ note_fr($s['reussite'], 0) }} %</span>
                                @else
                                    <span class="text-slate-400">non publié</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </x-tableau>
        @endif
    </x-carte>

    <x-carte titre="Assiduité par classe" sans-marge>
        <x-slot:actions><a href="{{ $exporter('assiduite') }}" class="lien text-sm no-print">CSV</a></x-slot:actions>
        <x-tableau>
            <x-slot:entete><th>Classe</th><th class="text-right">Demi-journées relevées</th><th class="text-right">Absences</th><th class="text-right">Non justifiées</th><th class="text-right">Retards</th><th class="text-right">Taux d'absence</th></x-slot:entete>
            @foreach ($assiduite as $a)
                <tr>
                    <td class="font-medium">{{ $a['classe'] }}</td>
                    <td class="text-right">{{ $a['releves'] }}</td><td class="text-right">{{ $a['absences'] }}</td>
                    <td @class(['text-right', 'font-semibold text-red-700' => $a['non_justifiees'] > 0])>{{ $a['non_justifiees'] }}</td>
                    <td class="text-right">{{ $a['retards'] }}</td>
                    <td class="text-right">{{ $a['taux'] === null ? '—' : note_fr($a['taux'], 1).' %' }}</td>
                </tr>
            @endforeach
        </x-tableau>
        <p class="px-5 py-2 text-xs text-slate-500">Une demi-journée relevée = un élève présent à un appel. Taux d'absence = absences / demi-journées relevées.</p>
    </x-carte>
</x-layouts.app>
