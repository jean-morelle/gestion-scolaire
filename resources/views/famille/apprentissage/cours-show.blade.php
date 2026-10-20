<x-layouts.app :titre="$cours->titre">
    <x-entete :titre="$cours->titre" :sous-titre="$cours->affectation->matiere->libelle.' · '.($cours->affectation->enseignant?->user->name ?? '')" :retour="route('eleve.cours')" />

    @if ($cours->description)<p class="mb-5 whitespace-pre-line text-sm text-slate-600">{{ $cours->description }}</p>@endif

    @include('pedagogie.cours._ressources', ['cours' => $cours, 'lienFichier' => fn ($r) => route('cours.ressources.fichier', [$cours, $r]), 'suppression' => false])

    @if ($cours->devoirs->isNotEmpty())
        <x-carte titre="Devoirs de ce cours" sans-marge class="mt-6">
            @foreach ($cours->devoirs as $d)
                <a href="{{ route('eleve.devoirs.show', $d) }}" class="flex justify-between border-b border-slate-100 px-5 py-3 text-sm last:border-0 hover:bg-slate-50">
                    <span class="font-medium">{{ $d->titre }}</span><span class="text-slate-500">pour le {{ $d->date_limite->format('d/m/Y à H:i') }}</span>
                </a>
            @endforeach
        </x-carte>
    @endif
</x-layouts.app>
