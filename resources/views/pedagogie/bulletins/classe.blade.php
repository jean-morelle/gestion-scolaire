<x-layouts.app :titre="'Bulletins '.$classe->nom">
    @php $d = (int) etablissement()->parametre('decimales', 2); @endphp
    <x-entete :titre="'Bulletins — '.$classe->nom" :sous-titre="$periode->libelle.' · '.$classe->anneeScolaire->libelle.' · Professeur principal : '.($classe->professeurPrincipal?->user->name ?? 'non désigné')"
              :retour="route('bulletins.index', ['annee' => $classe->annee_scolaire_id])">
        @if ($bulletins->isNotEmpty())
            <x-bouton variante="secondaire" icone="imprimer" :href="route('bulletins.pdf-classe', [$classe, $periode])" target="_blank">Tous les bulletins (PDF)</x-bouton>
        @endif
        @if ($peutApprecier && ! $publiee)
            <x-bouton variante="secondaire" icone="crayon" :href="route('bulletins.appreciations', [$classe, $periode])">Appréciations ({{ $nbAppreciations }})</x-bouton>
        @endif
    </x-entete>

    {{-- État et actions de publication --}}
    <x-carte class="mb-6">
        @if ($publiee)
            <div class="flex flex-wrap items-center gap-3">
                <x-badge couleur="vert">Publiés</x-badge>
                <span class="text-sm text-slate-600">le {{ $publication->publiee_at->format('d/m/Y à H:i') }} par {{ $publication->auteur?->name ?? '—' }}
                    · version {{ $bulletins->max('version') }} · visibles des familles · notes figées.</span>
                @if ($peutPublier)
                    <details class="relative ml-auto">
                        <summary class="cursor-pointer list-none font-medium text-red-600 hover:underline">Retirer pour correction</summary>
                        <form method="POST" action="{{ route('bulletins.depublier', [$classe, $periode]) }}" class="absolute right-0 z-10 mt-2 w-80 space-y-3 rounded-xl border bg-white p-4 shadow-lg">
                            @csrf
                            <x-champ nom="motif" label="Motif (journalisé)" placeholder="Ex. erreur de note en mathématiques" requis maxlength="255" />
                            <p class="text-xs text-slate-500">Les familles ne verront plus ces bulletins jusqu'à leur republication, qui créera une nouvelle version.</p>
                            <x-bouton variante="danger" petit>Retirer la publication</x-bouton>
                        </form>
                    </details>
                @endif
            </div>
        @else
            <div class="space-y-3">
                <div class="flex flex-wrap items-center gap-3">
                    @if ($publication)
                        <x-badge couleur="violet">Retirés pour correction</x-badge>
                        <span class="text-sm text-slate-600">depuis le {{ $publication->depubliee_at->format('d/m/Y à H:i') }} — les bulletins ne sont plus visibles des familles.</span>
                    @else
                        <x-badge>Non publiés</x-badge>
                        <span class="text-sm text-slate-600">Rien n'est visible des familles avant la publication.</span>
                    @endif
                </div>
                @if ($apercu['evaluations_non_validees'])
                    <p class="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900">{{ $apercu['evaluations_non_validees'] }} évaluation(s) encore en brouillon : elles ne sont pas comptées ci-dessous ni dans les bulletins.</p>
                @endif
                @if ($peutPublier)
                    <form method="POST" action="{{ route('bulletins.publier', [$classe, $periode]) }}" class="flex flex-wrap items-center gap-3"
                          data-confirm="Publier les bulletins de {{ $classe->nom }} pour le {{ $periode->libelle }} ? Ils seront figés et visibles des familles.">
                        @csrf
                        @if ($apercu['evaluations_non_validees'])
                            <x-case nom="forcer" label="Publier quand même (évaluations en brouillon exclues)" />
                        @endif
                        <x-bouton variante="succes" icone="diplome">{{ $publication ? 'Republier (nouvelle version)' : 'Publier les bulletins' }}</x-bouton>
                    </form>
                @endif
            </div>
        @endif
    </x-carte>

    @if ($publiee)
        <x-carte titre="Bulletins publiés" sans-marge>
            <x-tableau>
                <x-slot:entete><th>Rang</th><th>Élève</th><th class="text-right">Moyenne</th><th>Mention</th><th>Décision</th><th class="text-right">Abs. (non just.)</th><th></th></x-slot:entete>
                @foreach ($bulletins as $b)
                    <tr>
                        <td class="font-semibold">{{ $b->rang ?? '—' }}</td>
                        <td class="font-medium">{{ $b->inscription->eleve->nom_complet }}</td>
                        <td class="text-right font-semibold">{{ note_fr($b->moyenne_generale, $d) }}</td>
                        <td>{{ $b->mention ?? '—' }}</td>
                        <td>{{ $b->distinction ?? '—' }}</td>
                        <td class="text-right">{{ $b->assiduite['absences'] ?? 0 }} ({{ $b->assiduite['non_justifiees'] ?? 0 }})</td>
                        <td class="text-right"><a href="{{ route('bulletins.pdf', $b) }}" target="_blank" class="lien">PDF</a></td>
                    </tr>
                @endforeach
            </x-tableau>
        </x-carte>
    @else
        <x-carte titre="Aperçu (évaluations validées)" sans-marge>
            @if ($apercu['eleves']->isEmpty())
                <x-vide message="Aucun élève dans la classe pour cette période." icone="utilisateurs" />
            @else
                <x-tableau>
                    <x-slot:entete>
                        <th>Rang</th><th>Élève</th>
                        @foreach ($apercu['matieres'] as $m)
                            <th class="text-right" title="{{ $m['affectation']->matiere->libelle }}">{{ $m['affectation']->matiere->code }}</th>
                        @endforeach
                        <th class="text-right">Moyenne</th><th>Mention</th>
                    </x-slot:entete>
                    @foreach ($apercu['eleves'] as $e)
                        <tr>
                            <td class="font-semibold">{{ $e['rang'] ?? '—' }}</td>
                            <td class="whitespace-nowrap font-medium">{{ $e['inscription']->eleve->nom_complet }}</td>
                            @foreach ($apercu['matieres'] as $matiereId => $m)
                                <td class="text-right">{{ note_fr($e['matieres'][$matiereId]['moyenne'] ?? null, $d) }}</td>
                            @endforeach
                            <td class="text-right font-semibold">{{ note_fr($e['moyenne'], $d) }}</td>
                            <td>{{ $e['mention'] ?? '—' }}</td>
                        </tr>
                    @endforeach
                </x-tableau>
            @endif
        </x-carte>
        @if ($bulletins->isNotEmpty())
            <p class="mt-3 text-xs text-slate-500">La version précédente reste consultable par le personnel (PDF ci-dessus) jusqu'à la republication.</p>
        @endif
    @endif
</x-layouts.app>
