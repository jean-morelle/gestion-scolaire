<x-layouts.app :titre="'Appel '.$classe->nom">
    <x-entete :titre="'Appel — '.$classe->nom" :sous-titre="\Carbon\Carbon::parse($date)->translatedFormat('l j F Y').' · '.$creneau->libelle()"
              :retour="route('appels.index', ['date' => $date, 'creneau' => $creneau->value])">
        <x-bouton variante="secondaire" icone="imprimer" type="button" onclick="window.print()">Imprimer</x-bouton>
    </x-entete>

    @if ($appel)
        <p class="mb-4 text-sm text-slate-500">Appel saisi par {{ $appel->auteur?->name ?? '—' }} le {{ $appel->created_at->format('d/m/Y à H:i') }}@if ($appel->updated_at->gt($appel->created_at->addMinute())), modifié le {{ $appel->updated_at->format('d/m/Y à H:i') }}@endif.</p>
    @endif
    @if ($blocage)
        <div class="mb-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">{{ $blocage }}</div>
    @endif

    @if ($eleves->isEmpty())
        <x-carte><x-vide message="Aucun élève attendu à cet appel." icone="utilisateurs" /></x-carte>
    @else
        @php
            $statutInitial = fn ($p) => match ($p?->statut) {
                \App\Enums\StatutPresence::Retard => 'retard',
                \App\Enums\StatutPresence::Absent, \App\Enums\StatutPresence::AbsentExcuse => 'absent',
                default => 'present',
            };
            $initial = $eleves->mapWithKeys(fn ($i) => [$i->id => old("lignes.{$i->id}.statut", $statutInitial($presences->get($i->id)))]);
        @endphp
        <form method="POST" action="{{ route('appels.store', $classe) }}" x-data="{ s: @js($initial), tous(v) { Object.keys(this.s).forEach(k => this.s[k] = v) } }">
            @csrf
            <input type="hidden" name="date" value="{{ $date }}">
            <input type="hidden" name="creneau" value="{{ $creneau->value }}">

            <x-carte sans-marge>
                <x-slot:titre>{{ $eleves->count() }} élève(s)</x-slot:titre>
                @unless ($blocage)
                    <x-slot:actions>
                        <span class="text-sm text-slate-500" x-text="Object.values(s).filter(v => v === 'absent').length + ' absent(s), ' + Object.values(s).filter(v => v === 'retard').length + ' retard(s)'"></span>
                        <x-bouton petit variante="secondaire" type="button" x-on:click="tous('present')">Tous présents</x-bouton>
                    </x-slot:actions>
                @endunless
                <ul class="divide-y divide-slate-100">
                    @foreach ($eleves as $n => $inscription)
                        @php $presence = $presences->get($inscription->id); @endphp
                        <li class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center" :class="{ 'bg-red-50/60': s[{{ $inscription->id }}] === 'absent', 'bg-amber-50/60': s[{{ $inscription->id }}] === 'retard' }">
                            <div class="min-w-0 flex-1">
                                <p class="font-medium text-slate-900"><span class="mr-1 text-xs text-slate-400">{{ $n + 1 }}.</span> {{ $inscription->eleve->nom_complet }}</p>
                                @if ($presence?->statut === \App\Enums\StatutPresence::AbsentExcuse)
                                    <p class="text-xs text-emerald-700">Absence excusée (justificatif enregistré)</p>
                                @endif
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                @foreach (['present' => ['Présent', 'peer-checked:bg-emerald-600 peer-checked:text-white peer-checked:ring-emerald-600'], 'absent' => ['Absent', 'peer-checked:bg-red-600 peer-checked:text-white peer-checked:ring-red-600'], 'retard' => ['Retard', 'peer-checked:bg-amber-500 peer-checked:text-white peer-checked:ring-amber-500']] as $valeur => [$libelle, $classes])
                                    <label class="cursor-pointer">
                                        <input type="radio" name="lignes[{{ $inscription->id }}][statut]" value="{{ $valeur }}" x-model="s[{{ $inscription->id }}]" class="peer sr-only" @checked($initial[$inscription->id] === $valeur) @disabled($blocage)>
                                        <span class="inline-block rounded-lg px-3 py-1.5 text-sm font-medium text-slate-700 ring-1 ring-slate-300 ring-inset {{ $classes }} peer-focus-visible:outline-2 peer-focus-visible:outline-marque-600 peer-disabled:cursor-not-allowed peer-disabled:opacity-70">{{ $libelle }}</span>
                                    </label>
                                @endforeach
                                <input type="number" name="lignes[{{ $inscription->id }}][minutes_retard]" min="1" max="240" placeholder="min"
                                       value="{{ old("lignes.{$inscription->id}.minutes_retard", $presence?->minutes_retard) }}"
                                       x-show="s[{{ $inscription->id }}] === 'retard'" class="champ w-20 py-1.5" aria-label="Minutes de retard" @disabled($blocage)>
                                <input type="text" name="lignes[{{ $inscription->id }}][remarque]" maxlength="255" placeholder="Remarque"
                                       value="{{ old("lignes.{$inscription->id}.remarque", $presence?->remarque) }}" class="champ w-40 py-1.5" aria-label="Remarque" @disabled($blocage)>
                            </div>
                        </li>
                    @endforeach
                </ul>
                @unless ($blocage)
                    <div class="sticky bottom-0 flex flex-wrap items-center gap-3 border-t border-slate-100 bg-white p-4">
                        <x-bouton icone="coche">{{ $appel ? 'Enregistrer les modifications' : 'Valider l\'appel' }}</x-bouton>
                        <span class="text-xs text-slate-500">Les absences couvertes par un justificatif sont automatiquement excusées.</span>
                    </div>
                @endunless
            </x-carte>
        </form>
    @endif
</x-layouts.app>
