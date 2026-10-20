<x-layouts.app :titre="$cours->titre">
    <x-entete :titre="$cours->titre" :sous-titre="$cours->affectation->classe->nom.' — '.$cours->affectation->matiere->libelle.' · '.($cours->affectation->enseignant?->user->name ?? '')" :retour="route('cours.index')">
        @if ($peutModifier)
            <form method="POST" action="{{ route('cours.publier', $cours) }}">
                @csrf <input type="hidden" name="publier" value="{{ $cours->est_publie ? 0 : 1 }}">
                <x-bouton :variante="$cours->est_publie ? 'secondaire' : 'succes'">{{ $cours->est_publie ? 'Retirer' : 'Publier' }}</x-bouton>
            </form>
            <x-bouton variante="secondaire" icone="document" :href="route('devoirs.create', ['affectation' => $cours->affectation_id, 'cours' => $cours->id])">Devoir lié</x-bouton>
            <x-bouton variante="secondaire" icone="crayon" :href="route('cours.edit', $cours)">Modifier</x-bouton>
            <form method="POST" action="{{ route('cours.destroy', $cours) }}" data-confirm="Supprimer ce cours et toutes ses ressources ?">
                @csrf @method('DELETE') <x-bouton variante="danger">Supprimer</x-bouton>
            </form>
        @endif
    </x-entete>

    <div class="mb-5 flex flex-wrap items-center gap-3 text-sm">
        <x-badge :couleur="$cours->est_publie ? 'vert' : 'gris'">{{ $cours->est_publie ? 'Publié le '.$cours->publie_at?->format('d/m/Y') : 'Brouillon : invisible des élèves' }}</x-badge>
        @if ($cours->description)<p class="w-full whitespace-pre-line text-slate-600">{{ $cours->description }}</p>@endif
    </div>

    @include('pedagogie.cours._ressources', ['cours' => $cours, 'lienFichier' => fn ($r) => route('cours.ressources.fichier', [$cours, $r]), 'suppression' => $peutModifier])

    @if ($peutModifier)
        <x-carte titre="Ajouter une ressource" class="mt-6 max-w-3xl">
            <form method="POST" action="{{ route('cours.ressources.store', $cours) }}" enctype="multipart/form-data" class="space-y-4" x-data="{ type: @js(old('type', 'chapitre')) }">
                @csrf
                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <label for="type" class="mb-1 block text-sm font-medium text-slate-700">Type</label>
                        <select id="type" name="type" x-model="type" class="champ">
                            @foreach (\App\Enums\TypeRessource::options() as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach
                        </select>
                    </div>
                    <x-champ nom="titre" label="Titre" requis maxlength="150" class="sm:col-span-2" />
                </div>
                <div x-show="type === 'chapitre' || type === 'exercice'">
                    <x-zone-texte nom="contenu" label="Contenu" :lignes="8" aide="Texte du chapitre ou énoncé de l'exercice." />
                </div>
                <div x-show="type === 'lien'" x-cloak>
                    <x-champ nom="url" label="Adresse (https://…)" type="url" maxlength="255" />
                </div>
                <div x-show="type !== 'lien'">
                    <x-champ nom="fichier" label="Fichier" type="file" :aide="'PDF, Word, Excel, PowerPoint, OpenDocument, image, audio ou vidéo — '.intdiv($tailleMaxKo, 1024).' Mo au maximum. Obligatoire pour un document.'" />
                </div>
                <x-bouton icone="plus">Ajouter</x-bouton>
            </form>
        </x-carte>
    @endif

    @if ($cours->devoirs->isNotEmpty())
        <x-carte titre="Devoirs liés" sans-marge class="mt-6">
            @foreach ($cours->devoirs as $d)
                <a href="{{ route('devoirs.show', $d) }}" class="flex justify-between border-b border-slate-100 px-5 py-3 text-sm last:border-0 hover:bg-slate-50">
                    <span class="font-medium">{{ $d->titre }}</span><span class="text-slate-500">pour le {{ $d->date_limite->format('d/m/Y H:i') }}</span>
                </a>
            @endforeach
        </x-carte>
    @endif
</x-layouts.app>
