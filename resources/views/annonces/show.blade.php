<x-layouts.app :titre="$annonce->titre">
    <x-entete :titre="$annonce->titre" :sous-titre="'Publiée le '.$annonce->publiee_at->translatedFormat('j F Y à H\hi').' par '.($annonce->auteur?->name ?? '—')" :retour="route('annonces.index')">
        @can('annonces.gerer')
            <x-bouton variante="secondaire" icone="crayon" :href="route('annonces.edit', $annonce)">Modifier</x-bouton>
            <form method="POST" action="{{ route('annonces.destroy', $annonce) }}" data-confirm="Supprimer cette annonce ?">
                @csrf @method('DELETE') <x-bouton variante="danger">Supprimer</x-bouton>
            </form>
        @endcan
    </x-entete>

    <x-carte class="max-w-3xl">
        <div class="mb-4 flex flex-wrap gap-2">
            <x-badge couleur="bleu">{{ $annonce->cible->libelle() }}{{ $annonce->classe ? ' · '.$annonce->classe->nom : '' }}</x-badge>
            @if ($annonce->expire_le)<x-badge>Jusqu'au {{ date_fr($annonce->expire_le) }}</x-badge>@endif
        </div>
        <div class="whitespace-pre-line text-sm leading-relaxed text-slate-800">{{ $annonce->contenu }}</div>
    </x-carte>
</x-layouts.app>
