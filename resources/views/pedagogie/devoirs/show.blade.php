<x-layouts.app :titre="$devoir->titre">
    @php
        $remis = $remises->filter(fn ($r) => $r->fichier_chemin || $r->commentaire)->count();
        $corriges = $remises->whereNotNull('corrige_at')->count();
    @endphp
    <x-entete :titre="$devoir->titre" :sous-titre="$devoir->affectation->classe->nom.' — '.$devoir->affectation->matiere->libelle.' · à rendre le '.$devoir->date_limite->format('d/m/Y à H:i').' · sur '.note_fr($devoir->bareme, 0)" :retour="route('cours.index')">
        @if ($peutModifier)
            @unless ($devoir->est_publie)
                <form method="POST" action="{{ route('devoirs.publier', $devoir) }}" data-confirm="Publier ce devoir ? Les élèves et leurs parents seront notifiés.">
                    @csrf <x-bouton variante="succes" icone="megaphone">Publier</x-bouton>
                </form>
            @endunless
            <x-bouton variante="secondaire" icone="crayon" :href="route('devoirs.edit', $devoir)">Modifier</x-bouton>
            @if ($remises->isEmpty())
                <form method="POST" action="{{ route('devoirs.destroy', $devoir) }}" data-confirm="Supprimer ce devoir ?">
                    @csrf @method('DELETE') <x-bouton variante="danger">Supprimer</x-bouton>
                </form>
            @endif
        @endif
    </x-entete>

    <div class="mb-6 grid gap-4 sm:grid-cols-4">
        <x-stat libelle="Statut" :valeur="$devoir->est_publie ? 'Publié' : 'Non publié'" :detail="$devoir->est_publie ? 'le '.$devoir->publie_at?->format('d/m/Y') : 'invisible des élèves'" icone="megaphone" :couleur="$devoir->est_publie ? 'vert' : 'orange'" />
        <x-stat libelle="Travaux remis" :valeur="$enLigne ? $remis.' / '.$eleves->count() : 'En classe'" :detail="$devoir->estEchu() ? 'date limite passée' : 'échéance '.$devoir->date_limite->diffForHumans()" icone="document" />
        <x-stat libelle="Corrigés" :valeur="$corriges" icone="coche" couleur="violet" />
        <x-stat libelle="Moyenne" :valeur="$remises->whereNotNull('note')->isEmpty() ? '—' : note_fr($remises->whereNotNull('note')->avg(fn ($r) => (float) $r->note)).' / '.note_fr($devoir->bareme, 0)" icone="graphique" />
    </div>

    <x-carte titre="Consignes" class="mb-6">
        <p class="whitespace-pre-line text-sm text-slate-700">{{ $devoir->consignes }}</p>
        @if ($devoir->fichier_chemin)
            <a href="{{ route('devoirs.sujet', $devoir) }}" class="lien mt-3 inline-flex items-center gap-1 text-sm"><x-icone nom="telecharger" class="size-4" />{{ $devoir->fichier_nom }}</a>
        @endif
        @if ($devoir->cours)<p class="mt-2 text-xs text-slate-500">Cours associé : <a href="{{ route('cours.show', $devoir->cours) }}" class="lien">{{ $devoir->cours->titre }}</a></p>@endif
    </x-carte>

    <x-carte titre="Suivi des remises et correction" sans-marge>
        @if ($eleves->isEmpty())
            <x-vide message="Aucun élève dans la classe." icone="utilisateurs" />
        @else
            <x-tableau>
                <x-slot:entete><th>Élève</th><th>Remise</th><th>Travail</th><th class="w-72">Correction</th></x-slot:entete>
                @foreach ($eleves as $inscription)
                    @php $r = $remises->get($inscription->id); $depose = $r && ($r->fichier_chemin || $r->commentaire); @endphp
                    <tr class="align-top">
                        <td class="font-medium">{{ $inscription->eleve->nom_complet }}</td>
                        <td>
                            @if ($depose)
                                <x-badge :couleur="$r->en_retard ? 'orange' : 'vert'">{{ $r->en_retard ? 'En retard' : 'Remis' }}</x-badge>
                                <span class="block text-xs text-slate-500">{{ $r->remis_at->format('d/m/Y H:i') }}</span>
                            @elseif ($enLigne)
                                <x-badge :couleur="$devoir->estEchu() ? 'rouge' : 'gris'">{{ $devoir->estEchu() ? 'Non remis' : 'En attente' }}</x-badge>
                            @else
                                <span class="text-xs text-slate-500">En classe</span>
                            @endif
                        </td>
                        <td class="max-w-xs text-sm">
                            @if ($r?->fichier_chemin)<a href="{{ route('devoirs.copie', [$devoir, $r]) }}" class="lien break-all">{{ $r->fichier_nom }}</a>@endif
                            @if ($r?->commentaire)<p class="mt-1 line-clamp-3 whitespace-pre-line text-slate-600">{{ $r->commentaire }}</p>@endif
                        </td>
                        <td>
                            @if ($peutModifier && ($depose || ! $enLigne || $devoir->estEchu()))
                                <form method="POST" action="{{ route('devoirs.corriger', [$devoir, $inscription]) }}" class="space-y-2">
                                    @csrf
                                    <div class="flex items-center gap-2">
                                        <input type="text" inputmode="decimal" name="note" value="{{ $r?->note !== null ? rtrim(rtrim(number_format((float) $r->note, 2, ',', ''), '0'), ',') : '' }}" class="champ w-20 py-1 text-right" aria-label="Note">
                                        <span class="text-xs text-slate-500">/ {{ note_fr($devoir->bareme, 0) }}</span>
                                        <x-bouton petit variante="secondaire">OK</x-bouton>
                                    </div>
                                    <textarea name="correction" rows="2" maxlength="2000" class="champ text-xs" placeholder="Commentaire de correction" aria-label="Commentaire">{{ $r?->correction }}</textarea>
                                </form>
                                @if ($r?->corrige_at)<p class="mt-1 text-xs text-emerald-700">Corrigé le {{ $r->corrige_at->format('d/m/Y') }}</p>@endif
                            @elseif ($r?->corrige_at)
                                <span class="font-semibold">{{ $r->note !== null ? note_fr($r->note).' / '.note_fr($devoir->bareme, 0) : '—' }}</span>
                                @if ($r->correction)<p class="text-xs text-slate-600">{{ $r->correction }}</p>@endif
                            @else
                                <span class="text-xs text-slate-400">—</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-tableau>
        @endif
    </x-carte>
    <p class="mt-3 text-xs text-slate-500">Les notes de devoirs sont un suivi pédagogique : elles ne comptent pas dans la moyenne du bulletin, sauf si vous les reportez dans une évaluation.</p>
</x-layouts.app>
