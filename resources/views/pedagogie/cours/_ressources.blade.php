{{-- Contenu d'un cours, partagé entre l'enseignant et l'élève. $lienFichier(ressource) donne l'URL protégée. --}}
<x-carte titre="Contenu du cours" sans-marge>
    @if ($cours->ressources->isEmpty())
        <x-vide message="Aucune ressource pour l'instant." icone="livre" />
    @else
        <ol class="divide-y divide-slate-100">
            @foreach ($cours->ressources as $r)
                <li class="px-5 py-4" x-data="{ ouvert: {{ $loop->first ? 'true' : 'false' }} }">
                    <div class="flex items-start justify-between gap-3">
                        <button type="button" class="flex items-center gap-2 text-left" @click="ouvert = ! ouvert" :aria-expanded="ouvert">
                            <x-badge :couleur="match ($r->type) { \App\Enums\TypeRessource::Chapitre => 'bleu', \App\Enums\TypeRessource::Exercice => 'violet', \App\Enums\TypeRessource::Lien => 'orange', default => 'gris' }">{{ $r->type->libelle() }}</x-badge>
                            <span class="font-medium text-slate-900">{{ $r->titre }}</span>
                        </button>
                        @if ($suppression)
                            <form method="POST" action="{{ route('cours.ressources.destroy', [$cours, $r]) }}" data-confirm="Retirer « {{ $r->titre }} » du cours ?">
                                @csrf @method('DELETE') <button class="text-sm text-red-600 hover:underline">Retirer</button>
                            </form>
                        @endif
                    </div>
                    <div x-show="ouvert" class="mt-3 space-y-2 text-sm">
                        @if ($r->contenu)<div class="whitespace-pre-line rounded-lg bg-slate-50 p-4 text-slate-700">{{ $r->contenu }}</div>@endif
                        @if ($r->url)<a href="{{ $r->url }}" target="_blank" rel="noopener noreferrer nofollow" class="lien break-all">{{ $r->url }}</a>@endif
                        @if ($r->fichier_chemin)
                            <a href="{{ $lienFichier($r) }}" class="lien inline-flex items-center gap-1"><x-icone nom="telecharger" class="size-4" />{{ $r->fichier_nom }}
                                <span class="text-xs text-slate-500">({{ number_format(($r->fichier_taille ?? 0) / 1024, 0, ',', ' ') }} Ko)</span></a>
                        @endif
                    </div>
                </li>
            @endforeach
        </ol>
    @endif
</x-carte>
