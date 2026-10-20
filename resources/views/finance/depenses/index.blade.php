<x-layouts.app titre="Dépenses">
    <x-entete titre="Dépenses" :sous-titre="'Du '.date_fr($du).' au '.date_fr($au)">
        <x-bouton variante="secondaire" icone="telecharger" :href="route('depenses.index', array_merge(request()->query(), ['format' => 'csv']))">Exporter (CSV)</x-bouton>
        @can('depenses.gerer')<x-bouton icone="plus" :href="route('depenses.create')">Nouvelle dépense</x-bouton>@endcan
    </x-entete>

    <div class="grid gap-6 lg:grid-cols-4">
        <div class="lg:col-span-3">
            <x-carte sans-marge>
                <form method="GET" class="grid gap-3 border-b border-slate-100 p-4 sm:grid-cols-3 lg:grid-cols-6">
                    <input type="date" name="du" value="{{ $du->toDateString() }}" class="champ" aria-label="Du">
                    <input type="date" name="au" value="{{ $au->toDateString() }}" class="champ" aria-label="Au">
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="Libellé, n°, bénéficiaire" class="champ lg:col-span-2" aria-label="Rechercher">
                    <select name="categorie" class="champ" aria-label="Catégorie">
                        <option value="">Toutes catégories</option>
                        @foreach ($categories as $c)<option value="{{ $c->id }}" @selected(request('categorie') == $c->id)>{{ $c->libelle }}</option>@endforeach
                    </select>
                    <div class="flex gap-2">
                        <select name="statut" class="champ" aria-label="Statut">
                            <option value="">Tous</option>
                            @foreach (\App\Enums\StatutDepense::options() as $v => $l)<option value="{{ $v }}" @selected(request('statut') === $v)>{{ $l }}</option>@endforeach
                        </select>
                        <x-bouton variante="secondaire" icone="recherche" aria-label="Filtrer"></x-bouton>
                    </div>
                </form>
                @if ($depenses->isEmpty())
                    <x-vide message="Aucune dépense sur cette période." icone="recu" />
                @else
                    <x-tableau>
                        <x-slot:entete><th>N°</th><th>Date</th><th>Libellé</th><th>Catégorie</th><th class="text-right">Montant</th><th>Statut</th><th></th></x-slot:entete>
                        @foreach ($depenses as $d)
                            <tr @class(['opacity-70' => $d->estAnnulee()])>
                                <td class="font-mono text-xs">{{ $d->numero }}</td>
                                <td>{{ date_fr($d->date_depense) }}</td>
                                <td class="font-medium">{{ $d->libelle }}<span class="block text-xs text-slate-500">{{ collect([$d->beneficiaire, $d->mode->libelle(), $d->reference_piece])->filter()->join(' · ') }}</span></td>
                                <td>{{ $d->categorie->libelle }}</td>
                                <td @class(['text-right font-semibold', 'line-through' => $d->estAnnulee()])>{{ montant($d->montant, false) }}</td>
                                <td>
                                    @if ($d->estAnnulee())<x-badge couleur="rouge">Annulée</x-badge><span class="block text-xs text-slate-500">{{ $d->motif_annulation }}</span>
                                    @else<x-badge couleur="vert">Valide</x-badge>@endif
                                </td>
                                <td class="whitespace-nowrap text-right">
                                    @if ($d->justificatif_chemin)<a href="{{ route('depenses.justificatif', $d) }}" class="lien">Pièce</a>@endif
                                    @can('depenses.annuler')
                                        @unless ($d->estAnnulee())
                                            <details class="relative ml-3 inline-block text-left">
                                                <summary class="cursor-pointer list-none text-sm font-medium text-red-600 hover:underline">Annuler</summary>
                                                <form method="POST" action="{{ route('depenses.annuler', $d) }}" class="absolute right-0 z-10 mt-2 w-72 space-y-3 rounded-xl border bg-white p-4 shadow-lg">
                                                    @csrf
                                                    <x-champ nom="motif_annulation" label="Motif (obligatoire)" requis maxlength="255" />
                                                    <x-bouton variante="danger" petit>Annuler la dépense</x-bouton>
                                                </form>
                                            </details>
                                        @endunless
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </x-tableau>
                    <div class="p-4">{{ $depenses->links() }}</div>
                @endif
            </x-carte>
        </div>

        <div class="space-y-6">
            <x-stat libelle="Total des dépenses valides" :valeur="montant($total)" icone="recu" couleur="rouge" />
            <x-carte titre="Par catégorie" sans-marge>
                @forelse ($parCategorie as $c)
                    <div class="flex justify-between px-5 py-2 text-sm"><span>{{ $c->libelle }}</span><span class="font-medium">{{ montant($c->total, false) }}</span></div>
                @empty
                    <p class="px-5 py-3 text-sm text-slate-500">—</p>
                @endforelse
            </x-carte>
            @can('depenses.gerer')
                <x-carte titre="Nouvelle catégorie">
                    <form method="POST" action="{{ route('categories-depenses.store') }}" class="flex gap-2">
                        @csrf
                        <input type="text" name="libelle" class="champ" maxlength="100" required aria-label="Catégorie" placeholder="Ex. Transport">
                        <x-bouton variante="secondaire" icone="plus" aria-label="Ajouter"></x-bouton>
                    </form>
                </x-carte>
            @endcan
        </div>
    </div>
</x-layouts.app>
