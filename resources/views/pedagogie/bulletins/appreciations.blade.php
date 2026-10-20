<x-layouts.app :titre="'Appréciations '.$classe->nom">
    @php $d = (int) etablissement()->parametre('decimales', 2); @endphp
    <x-entete :titre="'Appréciations — '.$classe->nom" :sous-titre="$periode->libelle.' · conseil de classe'" :retour="route('bulletins.classe', [$classe, $periode])" />

    @if ($publiee)
        <div class="mb-5 rounded-xl border border-slate-200 bg-slate-100 px-4 py-3 text-sm text-slate-700">Bulletins publiés : les appréciations ne sont plus modifiables.</div>
    @endif

    <form method="POST" action="{{ route('bulletins.appreciations.update', [$classe, $periode]) }}">
        @csrf @method('PUT')
        <datalist id="distinctions">@foreach ($distinctions as $dist)<option value="{{ $dist }}">@endforeach</datalist>
        <x-carte sans-marge>
            <x-tableau>
                <x-slot:entete><th>Élève</th><th class="text-right">Moyenne</th><th class="text-right">Rang</th><th class="w-1/2">Appréciation générale</th><th>Décision du conseil</th></x-slot:entete>
                @foreach ($resultats['eleves']->sortBy(fn ($e) => $e['inscription']->eleve->nom_complet) as $id => $e)
                    @php $a = $appreciations->get($id); @endphp
                    <tr>
                        <td class="font-medium">{{ $e['inscription']->eleve->nom_complet }}</td>
                        <td class="text-right">{{ note_fr($e['moyenne'], $d) }}</td>
                        <td class="text-right">{{ $e['rang'] ?? '—' }}</td>
                        <td><textarea name="lignes[{{ $id }}][appreciation_generale]" rows="2" maxlength="500" class="champ" @disabled($publiee)
                                      aria-label="Appréciation de {{ $e['inscription']->eleve->nom_complet }}">{{ old("lignes.$id.appreciation_generale", $a?->appreciation_generale) }}</textarea></td>
                        <td><input type="text" name="lignes[{{ $id }}][mention]" list="distinctions" maxlength="100" value="{{ old("lignes.$id.mention", $a?->mention) }}" class="champ" @disabled($publiee)
                                   aria-label="Décision du conseil"></td>
                    </tr>
                @endforeach
            </x-tableau>
            @unless ($publiee)
                <div class="flex flex-wrap items-center gap-3 border-t border-slate-100 p-4">
                    <x-bouton>Enregistrer</x-bouton>
                    <span class="text-xs text-slate-500">Moyennes provisoires, toutes évaluations comprises. Les appréciations sont reprises sur les bulletins à la publication.</span>
                </div>
            @endunless
        </x-carte>
    </form>
</x-layouts.app>
