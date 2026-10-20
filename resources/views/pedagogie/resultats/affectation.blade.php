<x-layouts.app :titre="'Relevé '.$affectation->libelle()">
    <x-entete :titre="'Relevé — '.$affectation->libelle()" :sous-titre="$periode->libelle.' · '.($affectation->enseignant?->user->name ?? 'enseignant non désigné')" :retour="route('evaluations.index')">
        <x-bouton variante="secondaire" icone="imprimer" type="button" onclick="window.print()">Imprimer</x-bouton>
        @can('create', [\App\Models\Evaluation::class, $affectation])
            <x-bouton icone="plus" :href="route('evaluations.create', ['affectation' => $affectation->id])">Nouvelle évaluation</x-bouton>
        @endcan
    </x-entete>

    <form method="GET" class="no-print mb-4">
        <x-selection nom="periode" label="Période" :options="$periodes->pluck('libelle', 'id')" :valeur="$periode->id" class="w-56" onchange="this.form.submit()" />
    </form>

    <x-carte sans-marge>
        @if ($lignes->isEmpty())
            <x-vide message="Aucun élève dans la classe pour cette période." icone="utilisateurs" />
        @else
            <x-tableau>
                <x-slot:entete>
                    <th>Élève</th>
                    @foreach ($evaluations as $ev)
                        <th class="text-right"><a href="{{ route('evaluations.show', $ev) }}" class="hover:underline">{{ $ev->libelle }}</a>
                            <span class="block font-normal normal-case">/{{ note_fr($ev->bareme, 0) }} · {{ date_fr($ev->date_evaluation) }}@unless ($ev->estValidee()) · brouillon @endunless</span></th>
                    @endforeach
                    <th class="text-right">Moyenne /20</th><th class="text-right">Rang</th>
                </x-slot:entete>
                @foreach ($lignes as $id => $ligne)
                    <tr>
                        <td class="font-medium">{{ $ligne['inscription']->eleve->nom_complet }}</td>
                        @foreach ($evaluations as $ev)
                            @php $note = $ligne['notes']->get($ev->id); @endphp
                            <td class="text-right">{{ $note?->est_absent ? 'Abs.' : ($note ? note_fr($note->valeur) : '—') }}</td>
                        @endforeach
                        <td @class(['text-right font-semibold', 'text-red-700' => $ligne['moyenne'] !== null && $ligne['moyenne'] < 10])>{{ note_fr($ligne['moyenne'], etablissement()->parametre('decimales', 2)) }}</td>
                        <td class="text-right">{{ $rangs[$id] ?? '—' }}</td>
                    </tr>
                @endforeach
            </x-tableau>
        @endif
    </x-carte>
    @if ($evaluations->contains(fn ($e) => ! $e->estValidee()))
        <p class="mt-3 text-xs text-slate-500">Les évaluations en brouillon sont incluses dans ce relevé provisoire.</p>
    @endif
</x-layouts.app>
