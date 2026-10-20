<x-layouts.app titre="Mes devoirs">
    <x-entete titre="Mes devoirs" />

    <x-carte titre="À rendre" sans-marge class="mb-6">
        @forelse ($aRendre as $d)
            <a href="{{ route('eleve.devoirs.show', $d) }}" class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-3 text-sm last:border-0 hover:bg-slate-50">
                <span><span class="font-medium text-slate-900">{{ $d->titre }}</span>
                    <span class="block text-xs text-slate-500">{{ $d->affectation->matiere->libelle }}</span></span>
                <span @class(['text-right text-xs', 'font-semibold text-red-700' => $d->date_limite->lt(now()->addDays(2)), 'text-slate-600' => ! $d->date_limite->lt(now()->addDays(2))])>
                    {{ $d->date_limite->translatedFormat('l j F, H\hi') }}<span class="block font-normal text-slate-500">{{ $d->date_limite->diffForHumans() }}</span>
                </span>
            </a>
        @empty
            <x-vide message="Aucun devoir à rendre pour le moment." icone="coche" />
        @endforelse
    </x-carte>

    <x-carte titre="Rendus et passés" sans-marge>
        @if ($autres->isEmpty())
            <x-vide message="Aucun devoir pour l'instant." icone="document" />
        @else
            <x-tableau>
                <x-slot:entete><th>Devoir</th><th>Échéance</th><th>Statut</th><th class="text-right">Note</th></x-slot:entete>
                @foreach ($autres as $d)
                    @php $r = $d->remises->first(); @endphp
                    <tr>
                        <td class="font-medium"><a href="{{ route('eleve.devoirs.show', $d) }}" class="hover:underline">{{ $d->titre }}</a>
                            <span class="block text-xs text-slate-500">{{ $d->affectation->matiere->libelle }}</span></td>
                        <td>{{ $d->date_limite->format('d/m/Y') }}</td>
                        <td>
                            @if ($r?->corrige_at)<x-badge couleur="violet">Corrigé</x-badge>
                            @elseif ($r && ($r->fichier_chemin || $r->commentaire))<x-badge :couleur="$r->en_retard ? 'orange' : 'vert'">{{ $r->en_retard ? 'Rendu en retard' : 'Rendu' }}</x-badge>
                            @elseif ($d->remise_en_ligne)<x-badge couleur="rouge">Non rendu</x-badge>
                            @else<x-badge>À rendre en classe</x-badge>@endif
                        </td>
                        <td class="text-right font-semibold">{{ $r?->corrige_at && $r->note !== null ? note_fr($r->note).' / '.note_fr($d->bareme, 0) : '—' }}</td>
                    </tr>
                @endforeach
            </x-tableau>
        @endif
    </x-carte>
</x-layouts.app>
