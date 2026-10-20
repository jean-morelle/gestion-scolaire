<x-layouts.app titre="Annonces">
    <x-entete titre="Annonces" :sous-titre="$gestion ? 'Toutes les annonces, y compris programmées et expirées' : 'Informations de l\'établissement'">
        @can('annonces.gerer')
            @if ($gestion)
                <x-bouton variante="secondaire" :href="route('annonces.index')">Vue lecteur</x-bouton>
            @else
                <x-bouton variante="secondaire" icone="reglages" :href="route('annonces.index', ['vue' => 'gestion'])">Gérer</x-bouton>
            @endif
            <x-bouton icone="plus" :href="route('annonces.create')">Nouvelle annonce</x-bouton>
        @endcan
    </x-entete>

    @if ($annonces->isEmpty())
        <x-carte><x-vide message="Aucune annonce pour le moment." icone="megaphone" /></x-carte>
    @else
        <div class="space-y-4">
            @foreach ($annonces as $a)
                @php
                    $etat = $a->publiee_at->isFuture() ? ['Programmée le '.$a->publiee_at->format('d/m/Y H:i'), 'orange']
                        : ($a->expire_le && $a->expire_le->lt(today()) ? ['Expirée', 'gris'] : null);
                @endphp
                <a href="{{ route('annonces.show', $a) }}" class="block rounded-2xl border border-slate-200 bg-white p-5 shadow-xs transition hover:border-marque-300">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-base font-semibold text-slate-900">{{ $a->titre }}</h2>
                        <x-badge couleur="bleu">{{ $a->cible->libelle() }}{{ $a->classe ? ' · '.$a->classe->nom : '' }}</x-badge>
                        @if ($etat)<x-badge :couleur="$etat[1]">{{ $etat[0] }}</x-badge>@endif
                    </div>
                    <p class="mt-2 line-clamp-2 text-sm text-slate-600">{{ $a->contenu }}</p>
                    <p class="mt-2 text-xs text-slate-400">{{ $a->publiee_at->translatedFormat('j F Y') }} · {{ $a->auteur?->name }}</p>
                </a>
            @endforeach
        </div>
        <div class="mt-4">{{ $annonces->links() }}</div>
    @endif
</x-layouts.app>
