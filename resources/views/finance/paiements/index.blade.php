<x-layouts.app titre="Paiements">
    <x-entete titre="Journal des paiements" :sous-titre="'Du '.date_fr($du).' au '.date_fr($au)">
        <x-bouton variante="secondaire" icone="telecharger" :href="route('paiements.index', array_merge(request()->query(), ['format' => 'csv']))">Exporter (CSV)</x-bouton>
    </x-entete>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat libelle="Encaissé (paiements valides)" :valeur="montant($total)" icone="billet" couleur="vert" />
        @foreach ($parMode->take(2) as $m)
            <x-stat :libelle="\App\Enums\ModePaiement::from($m->mode instanceof \BackedEnum ? $m->mode->value : $m->mode)->libelle()" :valeur="montant($m->total)" :detail="$m->nombre.' paiement(s)'" icone="portefeuille" />
        @endforeach
        <x-stat libelle="Annulations" :valeur="$annules" icone="alerte" :couleur="$annules ? 'orange' : 'vert'" />
    </div>

    <x-carte sans-marge>
        <form method="GET" class="grid gap-3 border-b border-slate-100 p-4 sm:grid-cols-3 lg:grid-cols-7">
            <input type="date" name="du" value="{{ $du->toDateString() }}" class="champ" aria-label="Du">
            <input type="date" name="au" value="{{ $au->toDateString() }}" class="champ" aria-label="Au">
            <input type="search" name="q" value="{{ request('q') }}" placeholder="N° de reçu ou élève" class="champ lg:col-span-2" aria-label="Rechercher">
            <select name="mode" class="champ" aria-label="Mode">
                <option value="">Tous modes</option>
                @foreach (\App\Enums\ModePaiement::options() as $v => $l)<option value="{{ $v }}" @selected(request('mode') === $v)>{{ $l }}</option>@endforeach
            </select>
            <select name="encaisseur" class="champ" aria-label="Encaissé par">
                <option value="">Tous caissiers</option>
                @foreach ($encaisseurs as $id => $nom)<option value="{{ $id }}" @selected(request('encaisseur') == $id)>{{ $nom }}</option>@endforeach
            </select>
            <div class="flex gap-2">
                <select name="statut" class="champ" aria-label="Statut">
                    <option value="">Tous</option>
                    @foreach (\App\Enums\StatutPaiement::options() as $v => $l)<option value="{{ $v }}" @selected(request('statut') === $v)>{{ $l }}</option>@endforeach
                </select>
                <x-bouton variante="secondaire" icone="recherche" aria-label="Filtrer"></x-bouton>
            </div>
        </form>

        @if ($paiements->isEmpty())
            <x-vide message="Aucun paiement sur cette période." icone="billet" />
        @else
            <x-tableau>
                <x-slot:entete><th>Date</th><th>Élève</th><th class="text-right">Montant</th><th>Mode</th><th>Statut</th><th>Reçu</th><th>Caissier</th><th class="text-right">Actions</th></x-slot:entete>
                @foreach ($paiements as $p)
                    <tr @class(['opacity-70' => $p->estAnnule()])>
                        <td class="whitespace-nowrap">{{ date_fr($p->date_paiement) }}</td>
                        <td><a href="{{ route('finances.show', $p->inscription) }}" class="flex items-center gap-3"><x-avatar :eleve="$p->inscription->eleve" taille="size-8" />
                            <span><span class="block font-medium text-slate-900 hover:underline">{{ $p->inscription->eleve->nom_complet }}</span><span class="block text-xs text-slate-500">{{ $p->inscription->classe->nom }}</span></span></a></td>
                        <td @class(['text-right font-semibold', 'line-through' => $p->estAnnule()])>{{ montant($p->montant, false) }}</td>
                        <td>{{ $p->mode->libelle() }}</td>
                        <td>@if ($p->estAnnule())<x-badge couleur="rouge">Annulé</x-badge>@else<x-badge couleur="vert">Payé</x-badge>@endif</td>
                        <td class="font-mono text-xs">{{ $p->numero_recu }}</td>
                        <td>{{ $p->encaisseur->name }}</td>
                        <td class="whitespace-nowrap text-right">
                            <x-action-icone :href="route('paiements.recu', $p)" icone="imprimer" :libelle="'Reçu '.$p->numero_recu" target="_blank" />
                            <x-action-icone :href="route('finances.show', $p->inscription)" icone="portefeuille" :libelle="'Situation de '.$p->inscription->eleve->nom_complet" couleur="gris" />
                        </td>
                    </tr>
                @endforeach
            </x-tableau>
            <div class="p-4">{{ $paiements->links() }}</div>
        @endif
    </x-carte>
</x-layouts.app>
