<x-layouts.app titre="Réinscription en lot">
    <x-entete titre="Réinscription en lot" sous-titre="Réinscrivez en une fois les élèves d'une classe dans une classe d'une année ouverte." :retour="route('inscriptions.index')" />

    <x-carte class="mb-6">
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <div class="w-72">
                <label for="source" class="mb-1 block text-sm font-medium text-slate-700">Classe d'origine</label>
                <select id="source" name="source" class="champ" required>
                    <option value="">— Choisir —</option>
                    @foreach ($classesSource as $libelle => $classes)
                        <optgroup label="{{ $libelle }}">
                            @foreach ($classes as $c)
                                <option value="{{ $c->id }}" @selected($source?->id === $c->id)>{{ $c->nom }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>
            <x-bouton variante="secondaire">Afficher les élèves</x-bouton>
        </form>
    </x-carte>

    @if ($source)
        <form method="POST" action="{{ route('inscriptions.reinscriptions.store') }}" x-data="{ tous: false }">
            @csrf
            <x-carte :titre="'Élèves de '.$source->nom.' ('.$source->anneeScolaire->libelle.')'" sans-marge>
                @if ($inscriptionsSource->isEmpty())
                    <x-vide message="Aucun élève dans cette classe." icone="utilisateurs" />
                @else
                    <x-tableau>
                        <x-slot:entete>
                            <th class="w-10"><input type="checkbox" x-model="tous" @change="$root.querySelectorAll('[data-eleve]:not(:disabled)').forEach(c => c.checked = tous)" class="size-4 rounded border-slate-300" aria-label="Tout cocher"></th>
                            <th>Élève</th><th>Situation</th><th>Redouble</th>
                        </x-slot:entete>
                        @foreach ($inscriptionsSource as $inscription)
                            @php
                                $eleve = $inscription->eleve;
                                $dejaInscrit = $eleve->inscriptions->first(fn ($i) => $i->anneeScolaire->date_debut->gt($source->anneeScolaire->date_debut));
                                $bloque = $dejaInscrit || $eleve->statut === \App\Enums\StatutEleve::Archive;
                            @endphp
                            <tr @class(['opacity-60' => $bloque])>
                                <td><input type="checkbox" name="eleves[]" value="{{ $eleve->id }}" data-eleve @disabled($bloque) @checked(in_array($eleve->id, old('eleves', [])))
                                    class="size-4 rounded border-slate-300 text-marque-600" aria-label="Réinscrire {{ $eleve->nom_complet }}"></td>
                                <td class="font-medium">{{ $eleve->nom_complet }} <span class="font-mono text-xs text-slate-500">{{ $eleve->matricule }}</span></td>
                                <td>
                                    @if ($dejaInscrit)
                                        <x-badge couleur="vert">Déjà inscrit : {{ $dejaInscrit->classe->nom }}</x-badge>
                                    @elseif ($eleve->statut === \App\Enums\StatutEleve::Archive)
                                        <x-badge>Dossier archivé</x-badge>
                                    @elseif (! $inscription->estActive())
                                        <x-badge couleur="rouge">Parti le {{ date_fr($inscription->date_sortie) }}</x-badge>
                                    @else
                                        <x-badge couleur="orange">À réinscrire</x-badge>
                                    @endif
                                </td>
                                <td><input type="checkbox" name="redoublants[]" value="{{ $eleve->id }}" @disabled($bloque) class="size-4 rounded border-slate-300 text-amber-600" aria-label="{{ $eleve->nom_complet }} redouble"></td>
                            </tr>
                        @endforeach
                    </x-tableau>
                    <div class="grid gap-4 border-t border-slate-100 p-4 sm:grid-cols-3 sm:items-end">
                        <div>
                            <label for="classe_id" class="mb-1 block text-sm font-medium text-slate-700">Classe de destination <span class="text-red-500">*</span></label>
                            <select id="classe_id" name="classe_id" class="champ" required>
                                <option value="">— Choisir —</option>
                                @foreach ($classesCible as $libelle => $classes)
                                    <optgroup label="{{ $libelle }}">
                                        @foreach ($classes as $c)
                                            <option value="{{ $c->id }}" @selected(old('classe_id') == $c->id)>{{ $c->nom }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                            @error('classe_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <x-champ nom="date_inscription" label="Date de réinscription" type="date" :valeur="now()->toDateString()" requis />
                        <div><x-bouton icone="inscription">Réinscrire les élèves cochés</x-bouton></div>
                    </div>
                @endif
            </x-carte>
        </form>
    @endif
</x-layouts.app>
