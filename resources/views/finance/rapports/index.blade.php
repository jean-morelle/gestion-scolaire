<x-layouts.app titre="Rapports financiers">
    <x-entete titre="Rapports financiers" :sous-titre="'Du '.date_fr($du).' au '.date_fr($au).' · montants en '.etablissement()->devise">
        <x-bouton variante="secondaire" icone="imprimer" type="button" onclick="window.print()">Imprimer</x-bouton>
    </x-entete>

    <form method="GET" class="no-print mb-6 flex flex-wrap items-end gap-3">
        <x-champ nom="du" label="Du" type="date" :valeur="$du->toDateString()" class="w-44" />
        <x-champ nom="au" label="Au" type="date" :valeur="$au->toDateString()" class="w-44" />
        <x-bouton variante="secondaire">Afficher</x-bouton>
    </form>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat libelle="Recettes encaissées" :valeur="montant($totalRecettes)" icone="billet" couleur="vert" />
        <x-stat libelle="Dépenses" :valeur="montant($totalDepenses)" icone="recu" couleur="rouge" />
        <x-stat libelle="Solde de la période" :valeur="montant($totalRecettes - $totalDepenses)" icone="portefeuille" :couleur="$totalRecettes >= $totalDepenses ? 'marque' : 'orange'" />
        <x-stat libelle="Paiements annulés" :valeur="$annulations" detail="sur la période" icone="alerte" :couleur="$annulations ? 'orange' : 'vert'" />
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <x-carte titre="Recettes et dépenses par mois" sans-marge>
            <x-slot:actions><a href="{{ route('rapports.financiers', array_merge(request()->query(), ['export' => 'mensuel'])) }}" class="lien text-sm">CSV</a></x-slot:actions>
            @if ($parMois->isEmpty())
                <x-vide message="Aucun mouvement sur la période." icone="graphique" />
            @else
                <x-tableau>
                    <x-slot:entete><th>Mois</th><th class="text-right">Recettes</th><th class="text-right">Dépenses</th><th class="text-right">Solde</th></x-slot:entete>
                    @foreach ($parMois as $mois => $m)
                        <tr>
                            <td>{{ \Carbon\Carbon::createFromFormat('Y-m', $mois)->translatedFormat('F Y') }}</td>
                            <td class="text-right">{{ montant($m['recettes'], false) }}</td>
                            <td class="text-right">{{ montant($m['depenses'], false) }}</td>
                            <td @class(['text-right font-semibold', 'text-red-700' => $m['recettes'] < $m['depenses']])>{{ montant($m['recettes'] - $m['depenses'], false) }}</td>
                        </tr>
                    @endforeach
                </x-tableau>
            @endif
        </x-carte>

        <div class="space-y-6">
            <x-carte titre="Recettes par type de frais" sans-marge>
                @forelse ($recettesParType as $r)
                    <div class="flex justify-between px-5 py-2 text-sm"><span>{{ $r->libelle }}</span>
                        <span class="font-medium">{{ montant($r->total, false) }} <span class="text-xs text-slate-500">({{ note_fr($totalRecettes ? $r->total / $totalRecettes * 100 : 0, 0) }} %)</span></span></div>
                @empty
                    <p class="px-5 py-3 text-sm text-slate-500">Aucune recette.</p>
                @endforelse
            </x-carte>
            <x-carte titre="Recettes par mode de paiement" sans-marge>
                @forelse ($parMode as $m)
                    <div class="flex justify-between px-5 py-2 text-sm"><span>{{ $m->mode->libelle() }} <span class="text-xs text-slate-500">({{ $m->nombre }})</span></span><span class="font-medium">{{ montant($m->total, false) }}</span></div>
                @empty
                    <p class="px-5 py-3 text-sm text-slate-500">Aucune recette.</p>
                @endforelse
            </x-carte>
            <x-carte titre="Dépenses par catégorie" sans-marge>
                @forelse ($depensesParCategorie as $c)
                    <div class="flex justify-between px-5 py-2 text-sm"><span>{{ $c->libelle }}</span><span class="font-medium">{{ montant($c->total, false) }}</span></div>
                @empty
                    <p class="px-5 py-3 text-sm text-slate-500">Aucune dépense.</p>
                @endforelse
            </x-carte>
        </div>
    </div>

    <x-carte :titre="'Impayés par classe'.($annee ? ' — '.$annee->libelle.' (à ce jour)' : '')" sans-marge class="mt-6">
        <x-slot:actions><a href="{{ route('rapports.financiers', array_merge(request()->query(), ['export' => 'impayes'])) }}" class="lien text-sm">CSV</a></x-slot:actions>
        @if ($impayes->isEmpty())
            <x-vide message="Aucune donnée pour l'année active." icone="portefeuille" />
        @else
            <x-tableau>
                <x-slot:entete><th>Classe</th><th class="text-right">Élèves</th><th class="text-right">Dû</th><th class="text-right">Remises</th><th class="text-right">Payé</th><th class="text-right">Reste</th><th class="text-right">En retard</th><th class="text-right">Taux de recouvrement</th></x-slot:entete>
                @foreach ($impayes as $c)
                    @php $net = $c['du'] - $c['remises']; @endphp
                    <tr>
                        <td class="font-medium">{{ $c['classe'] }}</td>
                        <td class="text-right">{{ $c['eleves'] }}</td>
                        <td class="text-right">{{ montant($c['du'], false) }}</td>
                        <td class="text-right">{{ montant($c['remises'], false) }}</td>
                        <td class="text-right">{{ montant($c['paye'], false) }}</td>
                        <td class="text-right font-semibold">{{ montant($c['solde'], false) }}</td>
                        <td class="text-right">@if ($c['retard'])<span class="text-red-700">{{ montant($c['retard'], false) }}</span> <span class="text-xs text-slate-500">({{ $c['en_retard'] }} él.)</span>@else — @endif</td>
                        <td class="text-right">{{ $net > 0 ? note_fr($c['paye'] / $net * 100, 0).' %' : '—' }}</td>
                    </tr>
                @endforeach
                @php $t = ['du' => $impayes->sum('du'), 'remises' => $impayes->sum('remises'), 'paye' => $impayes->sum('paye'), 'solde' => $impayes->sum('solde'), 'retard' => $impayes->sum('retard')]; @endphp
                <tr class="bg-slate-50 font-semibold">
                    <td>Total</td><td class="text-right">{{ $impayes->sum('eleves') }}</td>
                    <td class="text-right">{{ montant($t['du'], false) }}</td><td class="text-right">{{ montant($t['remises'], false) }}</td>
                    <td class="text-right">{{ montant($t['paye'], false) }}</td><td class="text-right">{{ montant($t['solde'], false) }}</td>
                    <td class="text-right">{{ montant($t['retard'], false) }}</td>
                    <td class="text-right">{{ $t['du'] - $t['remises'] > 0 ? note_fr($t['paye'] / ($t['du'] - $t['remises']) * 100, 0).' %' : '—' }}</td>
                </tr>
            </x-tableau>
        @endif
    </x-carte>
</x-layouts.app>
