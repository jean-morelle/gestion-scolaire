<x-layouts.app :titre="$devoir->titre">
    <x-entete :titre="$devoir->titre" :sous-titre="$devoir->affectation->matiere->libelle.' · '.($devoir->affectation->enseignant?->user->name ?? '').' · à rendre le '.$devoir->date_limite->translatedFormat('l j F Y à H\hi')" :retour="route('eleve.devoirs')" />

    <div class="grid gap-6 lg:grid-cols-3">
        <x-carte titre="Consignes" class="lg:col-span-2">
            <p class="whitespace-pre-line text-sm text-slate-700">{{ $devoir->consignes }}</p>
            @if ($devoir->fichier_chemin)
                <a href="{{ route('devoirs.sujet', $devoir) }}" class="lien mt-3 inline-flex items-center gap-1 text-sm"><x-icone nom="telecharger" class="size-4" />{{ $devoir->fichier_nom }}</a>
            @endif
            @if ($devoir->cours)<p class="mt-3 text-xs text-slate-500">Cours associé : <a href="{{ route('eleve.cours.show', $devoir->cours) }}" class="lien">{{ $devoir->cours->titre }}</a></p>@endif
        </x-carte>

        <x-carte titre="Mon travail">
            @if ($remise?->corrige_at)
                <div class="mb-4 rounded-xl bg-violet-50 p-4">
                    <p class="text-sm text-violet-900">Corrigé le {{ $remise->corrige_at->format('d/m/Y') }}</p>
                    <p class="text-2xl font-semibold text-violet-900">{{ $remise->note !== null ? note_fr($remise->note).' / '.note_fr($devoir->bareme, 0) : 'Sans note' }}</p>
                    @if ($remise->correction)<p class="mt-2 whitespace-pre-line text-sm text-violet-900">{{ $remise->correction }}</p>@endif
                </div>
            @endif

            @if ($remise && ($remise->fichier_chemin || $remise->commentaire))
                <p class="text-sm"><x-badge :couleur="$remise->en_retard ? 'orange' : 'vert'">{{ $remise->en_retard ? 'Rendu en retard' : 'Rendu' }}</x-badge>
                    le {{ $remise->remis_at->format('d/m/Y à H:i') }}</p>
                @if ($remise->fichier_chemin)<a href="{{ route('devoirs.copie', [$devoir, $remise]) }}" class="lien mt-2 block break-all text-sm">{{ $remise->fichier_nom }}</a>@endif
                @if ($remise->commentaire)<p class="mt-2 whitespace-pre-line text-sm text-slate-600">{{ $remise->commentaire }}</p>@endif
            @endif

            @if (! $enLigne)
                <p class="text-sm text-slate-600">Ce devoir se rend en classe, à l'enseignant.</p>
            @elseif (! $remise?->corrige_at)
                @if ($devoir->accepteLesRemises())
                    <form method="POST" action="{{ route('eleve.devoirs.remettre', $devoir) }}" enctype="multipart/form-data" class="mt-4 space-y-3"
                          data-confirm="{{ $remise ? 'Remplacer votre travail déjà déposé ?' : 'Déposer votre travail ?' }}">
                        @csrf
                        <x-champ nom="fichier" label="Fichier" type="file" accept=".pdf,.doc,.docx,.odt,.txt,image/jpeg,image/png"
                            :aide="'PDF, Word, texte ou photo — '.intdiv($tailleMaxKo, 1024).' Mo au maximum.'" />
                        <x-zone-texte nom="commentaire" label="Réponse ou message (facultatif)" :lignes="4" :valeur="$remise?->commentaire" />
                        <x-bouton icone="telecharger" class="w-full">{{ $remise ? 'Remplacer mon travail' : 'Déposer mon travail' }}</x-bouton>
                        @if ($devoir->estEchu())<p class="text-xs text-amber-700">La date limite est passée : votre dépôt sera marqué « en retard ».</p>@endif
                    </form>
                @elseif (! $remise)
                    <p class="text-sm font-medium text-red-700">La date limite est dépassée : le dépôt n'est plus possible.</p>
                @endif
            @endif
        </x-carte>
    </div>
</x-layouts.app>
