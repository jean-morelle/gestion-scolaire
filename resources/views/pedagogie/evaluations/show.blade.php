<x-layouts.app :titre="$evaluation->libelle">
    @php $saisie = $peutSaisir && ! $blocage; @endphp
    <x-entete :titre="$evaluation->libelle"
              :sous-titre="$evaluation->affectation->classe->nom.' — '.$evaluation->affectation->matiere->libelle.' · '.$evaluation->periode->libelle.' · '.date_fr($evaluation->date_evaluation).' · '.$evaluation->type->libelle().' sur '.note_fr($evaluation->bareme, 0).', poids '.note_fr($evaluation->poids, 1)"
              :retour="route('evaluations.index')">
        <x-bouton variante="secondaire" icone="imprimer" type="button" onclick="window.print()">Imprimer</x-bouton>
        @if ($saisie)
            <x-bouton variante="secondaire" icone="crayon" :href="route('evaluations.edit', $evaluation)">Modifier</x-bouton>
            @if ($notes->isEmpty())
                <form method="POST" action="{{ route('evaluations.destroy', $evaluation) }}" data-confirm="Supprimer l'évaluation « {{ $evaluation->libelle }} » ?">
                    @csrf @method('DELETE') <x-bouton variante="danger">Supprimer</x-bouton>
                </form>
            @endif
        @endif
    </x-entete>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat libelle="Moyenne de la classe" :valeur="$stats['moyenne'] === null ? '—' : note_fr($stats['moyenne']).' / 20'" icone="graphique" />
        <x-stat libelle="Note la plus haute" :valeur="note_fr($stats['max'])" :detail="'note la plus basse : '.note_fr($stats['min'])" icone="diplome" couleur="vert" />
        <x-stat libelle="Élèves notés" :valeur="$stats['notees'].' / '.$eleves->count()" :detail="$stats['absents'].' absent(s)'" icone="utilisateurs" couleur="violet" />
        <x-stat libelle="Sous la moyenne" :valeur="$stats['sous_moyenne']" icone="alerte" couleur="orange" />
    </div>

    <div class="mb-5 flex flex-wrap items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm">
        @if ($evaluation->estValidee())
            <x-badge couleur="vert">Validée</x-badge>
            <span class="text-slate-600">par {{ $evaluation->validateur?->name ?? '—' }} le {{ $evaluation->validee_at?->format('d/m/Y à H:i') }} — notes verrouillées.</span>
            @can('deverrouiller', $evaluation)
                <details class="relative ml-auto">
                    <summary class="lien cursor-pointer list-none">Déverrouiller</summary>
                    <form method="POST" action="{{ route('evaluations.deverrouiller', $evaluation) }}" class="absolute right-0 z-10 mt-2 w-80 space-y-3 rounded-xl border bg-white p-4 shadow-lg">
                        @csrf
                        <x-champ nom="motif" label="Motif (journalisé)" placeholder="Ex. erreur de saisie signalée" requis maxlength="255" />
                        <p class="text-xs text-slate-500">Toute modification ultérieure de note conservera l'ancienne valeur dans le journal.</p>
                        <x-bouton variante="danger" petit>Déverrouiller</x-bouton>
                    </form>
                </details>
            @endcan
        @else
            <x-badge couleur="orange">Brouillon</x-badge>
            <span class="text-slate-600">Modifiable par l'enseignant jusqu'à la validation.</span>
            @if ($evaluation->deverrouillee_at)<x-badge couleur="violet">Déverrouillée le {{ $evaluation->deverrouillee_at->format('d/m/Y') }} : modifications historisées</x-badge>@endif
        @endif
        @if ($blocage && ! $evaluation->estValidee())
            <span class="text-amber-800">{{ $blocage }}</span>
        @endif
    </div>

    @if ($eleves->isEmpty())
        <x-carte><x-vide message="Aucun élève dans cette classe à la date de l'évaluation." icone="utilisateurs" /></x-carte>
    @else
        <form method="POST" action="{{ route('evaluations.notes', $evaluation) }}">
            @csrf @method('PUT')
            <x-carte sans-marge>
                <x-tableau>
                    <x-slot:entete><th>N°</th><th>Élève</th><th class="w-32">Note / {{ note_fr($evaluation->bareme, 0) }}</th><th>Absent</th><th>Observation</th></x-slot:entete>
                    @foreach ($eleves as $n => $inscription)
                        @php
                            $note = $notes->get($inscription->id);
                            $cle = "notes.{$inscription->id}";
                            $valeur = old("$cle.valeur", $note && $note->valeur !== null ? rtrim(rtrim(number_format((float) $note->valeur, 2, ',', ''), '0'), ',') : '');
                        @endphp
                        <tr x-data="{ absent: @js((bool) old("$cle.est_absent", $note?->est_absent)) }">
                            <td>{{ $n + 1 }}</td>
                            <td class="font-medium">{{ $inscription->eleve->nom_complet }}
                                @unless ($inscription->estActive())<x-badge couleur="rouge" class="ml-1">Parti</x-badge>@endunless</td>
                            <td>
                                @if ($saisie)
                                    <input type="text" inputmode="decimal" name="notes[{{ $inscription->id }}][valeur]" value="{{ $valeur }}" :disabled="absent" maxlength="6"
                                           @class(['champ text-right', 'champ-erreur' => $errors->has("$cle.valeur")]) aria-label="Note de {{ $inscription->eleve->nom_complet }}">
                                    @error("$cle.valeur")<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                @else
                                    <span class="font-medium">{{ $note?->est_absent ? 'Abs.' : ($note ? note_fr($note->valeur) : '—') }}</span>
                                @endif
                            </td>
                            <td>
                                @if ($saisie)
                                    <input type="hidden" name="notes[{{ $inscription->id }}][est_absent]" value="0">
                                    <input type="checkbox" name="notes[{{ $inscription->id }}][est_absent]" value="1" x-model="absent" @checked(old("$cle.est_absent", $note?->est_absent))
                                           class="size-4 rounded border-slate-300 text-marque-600" aria-label="{{ $inscription->eleve->nom_complet }} absent">
                                @elseif ($note?->est_absent) Oui @endif
                            </td>
                            <td>
                                @if ($saisie)
                                    <input type="text" name="notes[{{ $inscription->id }}][observation]" value="{{ old("$cle.observation", $note?->observation) }}" maxlength="255" class="champ" aria-label="Observation">
                                @else
                                    <span class="text-slate-600">{{ $note?->observation }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </x-tableau>
                @if ($saisie)
                    <div class="sticky bottom-0 flex flex-wrap items-center gap-3 border-t border-slate-100 bg-white p-4">
                        <x-bouton>Enregistrer les notes</x-bouton>
                        <button type="submit" name="valider" value="1" class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3.5 py-2 text-sm font-medium text-white hover:bg-emerald-700"
                                onclick="return confirm('Enregistrer et valider ? Les notes seront verrouillées ; seule la direction pourra les déverrouiller.')">
                            <x-icone nom="verrou" class="size-4" /> Enregistrer et valider
                        </button>
                        <span class="text-xs text-slate-500">Virgule ou point acceptés (ex. 12,5). {{ etablissement()->parametre('absence_evaluation') === 'zero' ? 'Une absence compte 0 dans la moyenne (paramètre de l\'établissement).' : 'Une absence n\'est pas comptée dans la moyenne (différent de 0).' }}</span>
                    </div>
                @endif
            </x-carte>
        </form>
    @endif
</x-layouts.app>
