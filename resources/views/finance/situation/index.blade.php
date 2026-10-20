<x-layouts.app titre="Situation des élèves">
    <x-entete titre="Situation financière des élèves" :sous-titre="$annee ? 'Année '.$annee->libelle.' · '.$lignes->total().' élève(s)' : 'Aucune année scolaire'">
        <x-bouton variante="secondaire" icone="telecharger" :href="route('finances.index', array_merge(request()->query(), ['format' => 'csv']))">Exporter (CSV)</x-bouton>
    </x-entete>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat libelle="Total facturé" :valeur="montant($totaux['du'])" :detail="'dont remises : '.montant($totaux['remises'])" icone="portefeuille" />
        <x-stat libelle="Encaissé" :valeur="montant($totaux['paye'])" :detail="$totaux['du'] ? note_fr($totaux['paye'] / max(1, $totaux['du'] - $totaux['remises']) * 100, 0).' % du net' : null" icone="billet" couleur="vert" />
        <x-stat libelle="Reste à encaisser" :valeur="montant($totaux['solde'])" icone="recu" couleur="violet" />
        <x-stat libelle="En retard" :valeur="montant($totaux['retard'])" :detail="$enRetard.' élève(s)'" icone="alerte" couleur="rouge"
            :href="route('finances.index', array_merge(request()->query(), ['situation' => 'retard']))" />
    </div>

    <x-carte sans-marge>
        <form method="GET" class="grid gap-3 border-b border-slate-100 p-4 sm:grid-cols-2 lg:grid-cols-5">
            <select name="annee" class="champ" aria-label="Année scolaire">
                @foreach ($annees as $a)<option value="{{ $a->id }}" @selected($annee?->id === $a->id)>{{ $a->libelle }}</option>@endforeach
            </select>
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Nom ou matricule" class="champ lg:col-span-2" aria-label="Rechercher">
            <select name="classe" class="champ" aria-label="Classe">
                <option value="">Toutes les classes</option>
                @foreach ($classes as $id => $nom)<option value="{{ $id }}" @selected(request('classe') == $id)>{{ $nom }}</option>@endforeach
            </select>
            <div class="flex gap-2">
                <select name="situation" class="champ" aria-label="Situation">
                    <option value="">Toutes situations</option>
                    @foreach (['retard' => 'En retard', 'solde' => 'Reste à payer', 'regle' => 'Soldés', 'sans_frais' => 'Sans frais'] as $v => $l)
                        <option value="{{ $v }}" @selected(request('situation') === $v)>{{ $l }}</option>
                    @endforeach
                </select>
                <x-bouton variante="secondaire" icone="recherche" aria-label="Filtrer"></x-bouton>
            </div>
        </form>

        @if ($lignes->isEmpty())
            <x-vide message="Aucun élève ne correspond." icone="portefeuille" />
        @else
            <x-tableau>
                <x-slot:entete><th>Élève</th><th>Classe</th><th class="text-right">Dû</th><th class="text-right">Remises</th><th class="text-right">Payé</th><th class="text-right">Reste</th><th class="text-right">En retard</th><th></th></x-slot:entete>
                @foreach ($lignes as $l)
                    <tr>
                        <td class="font-medium">{{ $l['inscription']->eleve->nom_complet }}<span class="block font-mono text-xs text-slate-500">{{ $l['inscription']->eleve->matricule }}</span></td>
                        <td>{{ $l['inscription']->classe->nom }}</td>
                        <td class="text-right">{{ montant($l['du'], false) }}</td>
                        <td class="text-right">{{ $l['remises'] ? montant($l['remises'], false) : '—' }}</td>
                        <td class="text-right">{{ montant($l['paye'], false) }}</td>
                        <td class="text-right font-semibold">
                            @if ($l['du'] === 0)<x-badge>Sans frais</x-badge>@elseif ($l['solde'] <= 0)<x-badge couleur="vert">Soldé</x-badge>@else{{ montant($l['solde'], false) }}@endif
                        </td>
                        <td class="text-right">@if ($l['retard'] > 0)<span class="font-semibold text-red-700">{{ montant($l['retard'], false) }}</span>@else — @endif</td>
                        <td class="text-right"><a href="{{ route('finances.show', $l['inscription']) }}" class="lien">{{ auth()->user()->can('paiements.enregistrer') ? 'Encaisser' : 'Détail' }}</a></td>
                    </tr>
                @endforeach
            </x-tableau>
            <div class="p-4">{{ $lignes->links() }}</div>
        @endif
    </x-carte>
</x-layouts.app>
