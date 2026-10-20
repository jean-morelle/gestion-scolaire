<x-layouts.app titre="Recherche">
    <x-entete titre="Recherche" :sous-titre="mb_strlen($q) >= 2 ? 'Résultats pour « '.$q.' »' : 'Saisissez au moins deux caractères.'" />

    <form method="GET" action="{{ route('recherche') }}" class="mb-6 flex max-w-xl gap-2">
        <input type="search" name="q" value="{{ $q }}" class="champ" placeholder="Nom, prénom, matricule, classe, n° de reçu…" aria-label="Rechercher" autofocus>
        <x-bouton icone="recherche">Rechercher</x-bouton>
    </form>

    @if (mb_strlen($q) >= 2)
        @php $groupes = collect($resultats)->groupBy('categorie'); @endphp
        @forelse ($groupes as $categorie => $liste)
            <x-carte :titre="$categorie.' ('.$liste->count().')'" sans-marge class="mb-6">
                @foreach ($liste as $r)
                    <a href="{{ $r['url'] }}" class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-3 text-sm last:border-0 hover:bg-slate-50">
                        <span class="font-medium text-slate-900">{{ $r['titre'] }}</span>
                        <span class="text-slate-500">{{ $r['detail'] }}</span>
                    </a>
                @endforeach
            </x-carte>
        @empty
            <x-carte><x-vide message="Aucun résultat." icone="recherche" /></x-carte>
        @endforelse
    @endif
</x-layouts.app>
