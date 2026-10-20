<x-layouts.app titre="Mes cours">
    <x-entete titre="Mes cours" sous-titre="Cours et ressources publiés par vos enseignants." />

    @if ($cours->isEmpty())
        <x-carte><x-vide message="Aucun cours publié pour votre classe pour l'instant." icone="livre" /></x-carte>
    @else
        <div class="space-y-6">
            @foreach ($cours as $matiere => $liste)
                <x-carte :titre="$matiere" sans-marge>
                    @foreach ($liste as $c)
                        <a href="{{ route('eleve.cours.show', $c) }}" class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-3 text-sm last:border-0 hover:bg-slate-50">
                            <span><span class="font-medium text-slate-900">{{ $c->titre }}</span>
                                <span class="block text-xs text-slate-500">{{ $c->affectation->enseignant?->user->name }} · publié le {{ $c->publie_at?->format('d/m/Y') }}</span></span>
                            <span class="text-xs text-slate-500">{{ $c->ressources_count }} ressource(s)</span>
                        </a>
                    @endforeach
                </x-carte>
            @endforeach
        </div>
    @endif
</x-layouts.app>
