<x-layouts.app titre="Journal d'audit">
    <x-entete titre="Journal d'audit" sous-titre="Opérations sensibles enregistrées automatiquement. Ce journal ne peut être ni modifié ni supprimé." />

    <x-carte sans-marge>
        <form method="GET" class="grid gap-3 border-b border-slate-100 p-4 sm:grid-cols-5">
            <select name="action" class="champ" aria-label="Domaine">
                <option value="">Tous les domaines</option>
                @foreach ($familles as $famille)
                    <option value="{{ $famille }}" @selected(request('action') === $famille)>{{ ucfirst($famille) }}</option>
                @endforeach
            </select>
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Rechercher dans la description" class="champ sm:col-span-2" aria-label="Rechercher">
            <input type="date" name="du" value="{{ request('du') }}" class="champ" aria-label="Du">
            <div class="flex gap-2">
                <input type="date" name="au" value="{{ request('au') }}" class="champ" aria-label="Au">
                <x-bouton variante="secondaire" icone="recherche" aria-label="Filtrer" />
            </div>
        </form>

        @if ($entrees->isEmpty())
            <x-vide message="Aucune entrée pour ces critères." icone="bouclier" />
        @else
            <x-tableau>
                <x-slot:entete><th>Date</th><th>Utilisateur</th><th>Action</th><th>Description</th><th>Détails</th></x-slot:entete>
                @foreach ($entrees as $entree)
                    <tr>
                        <td class="whitespace-nowrap">{{ date_fr($entree->created_at, true) }}</td>
                        <td>{{ $entree->user?->name ?? 'Système' }}</td>
                        <td><code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs">{{ $entree->action }}</code></td>
                        <td>{{ $entree->description }}</td>
                        <td>
                            @if ($entree->anciennes_valeurs || $entree->nouvelles_valeurs)
                                <details>
                                    <summary class="lien cursor-pointer text-xs">Voir</summary>
                                    <div class="mt-2 grid max-w-md gap-2 text-xs">
                                        @if ($entree->anciennes_valeurs)
                                            <pre class="overflow-x-auto rounded bg-red-50 p-2 text-red-900">{{ json_encode($entree->anciennes_valeurs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                        @endif
                                        @if ($entree->nouvelles_valeurs)
                                            <pre class="overflow-x-auto rounded bg-emerald-50 p-2 text-emerald-900">{{ json_encode($entree->nouvelles_valeurs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                        @endif
                                    </div>
                                </details>
                            @endif
                            <span class="block text-xs text-slate-400">{{ $entree->ip }}</span>
                        </td>
                    </tr>
                @endforeach
            </x-tableau>
            <div class="p-4">{{ $entrees->links() }}</div>
        @endif
    </x-carte>
</x-layouts.app>
