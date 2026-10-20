<x-layouts.app :titre="$espace === 'eleve' ? 'Mon espace' : $eleve->prenoms">
    @php
        $d = (int) etablissement()->parametre('decimales', 2);
        $lien = fn (string $s) => $espace === 'eleve'
            ? route(['resume' => 'eleve.accueil', 'resultats' => 'eleve.resultats', 'assiduite' => 'eleve.assiduite', 'emploi-du-temps' => 'eleve.emploi-du-temps'][$s])
            : route('famille.enfant', [$eleve, $s]);
        $onglets = ['resume' => 'Vue d\'ensemble', 'resultats' => 'Résultats', 'assiduite' => 'Absences et retards', 'emploi-du-temps' => 'Emploi du temps'];
        if ($avecFinances) { $onglets['finances'] = 'Frais et paiements'; }
    @endphp
    <x-entete :titre="$espace === 'eleve' ? 'Bonjour '.$eleve->prenoms : $eleve->prenoms.' '.mb_strtoupper($eleve->nom)"
              :sous-titre="$inscription ? $inscription->classe->nom.' · '.$inscription->anneeScolaire->libelle.' · matricule '.$eleve->matricule : 'Matricule '.$eleve->matricule"
              :retour="$espace === 'famille' ? route('famille.enfants') : null" />

    <nav class="no-print mb-6 flex gap-1 overflow-x-auto border-b border-slate-200" aria-label="Sections">
        @foreach ($onglets as $cle => $libelle)
            <a href="{{ $lien($cle) }}" @class(['whitespace-nowrap border-b-2 px-3 py-2 text-sm font-medium',
                'border-marque-600 text-marque-700' => $section === $cle, 'border-transparent text-slate-500 hover:text-slate-800' => $section !== $cle])
               @if ($section === $cle) aria-current="page" @endif>{{ $libelle }}</a>
        @endforeach
    </nav>

    @switch($section)
        @case('resume')
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <x-stat libelle="Dernier bulletin" :valeur="$dernierBulletin ? note_fr($dernierBulletin->moyenne_generale, $d).' / 20' : '—'"
                    :detail="$dernierBulletin ? $dernierBulletin->periode->libelle.' · rang '.($dernierBulletin->rang ?? '—').'/'.$dernierBulletin->effectif : 'Aucun bulletin publié'" icone="diplome" :href="$lien('resultats')" />
                <x-stat :libelle="'Absences'.($periode ? ' — '.$periode->libelle : '')" :valeur="$assiduite ? $assiduite['absences'].' demi-j.' : '—'"
                    :detail="$assiduite ? $assiduite['non_justifiees'].' non justifiée(s), '.$assiduite['retards'].' retard(s)' : null" icone="coche"
                    :couleur="$assiduite && $assiduite['non_justifiees'] ? 'orange' : 'vert'" :href="$lien('assiduite')" />
                @if ($avecFinances)
                    <x-stat libelle="Reste à payer" :valeur="$finances ? montant($finances['solde']) : '—'"
                        :detail="$finances && $finances['retard'] ? 'dont '.montant($finances['retard']).' en retard' : 'À jour'" icone="portefeuille"
                        :couleur="$finances && $finances['retard'] ? 'rouge' : 'vert'" :href="$lien('finances')" />
                @endif
                <x-stat libelle="Professeur principal" :valeur="$inscription?->classe->professeurPrincipal?->user->name ?? '—'" icone="enseignant" couleur="violet" />
            </div>

            <x-carte titre="Cours du jour" class="mt-6" sans-marge>
                @if ($coursDuJour->isEmpty())
                    <x-vide message="Pas de cours aujourd'hui dans l'emploi du temps." icone="calendrier" />
                @else
                    <ul class="divide-y divide-slate-100">
                        @foreach ($coursDuJour as $c)
                            <li class="flex items-center gap-3 px-5 py-2.5 text-sm">
                                <span class="w-28 shrink-0 font-medium text-slate-600">{{ $c->horaire() }}</span>
                                <span class="inline-block size-2.5 rounded-full" style="background-color: {{ $c->affectation->matiere->couleur ?? '#94a3b8' }}"></span>
                                <span class="font-medium">{{ $c->affectation->matiere->libelle }}</span>
                                <span class="text-slate-500">{{ $c->affectation->enseignant?->user->name }}{{ $c->salle ? ' · '.$c->salle : '' }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-carte>
            @break

        @case('resultats')
            <x-carte titre="Bulletins publiés" sans-marge>
                @if ($bulletins->isEmpty())
                    <x-vide message="Aucun bulletin n'a encore été publié." icone="diplome" />
                @else
                    <x-tableau>
                        <x-slot:entete><th>Période</th><th>Classe</th><th class="text-right">Moyenne</th><th class="text-right">Rang</th><th>Mention</th><th></th></x-slot:entete>
                        @foreach ($bulletins as $b)
                            <tr>
                                <td class="font-medium">{{ $b->periode->libelle }} <span class="text-xs text-slate-500">{{ $b->periode->anneeScolaire->libelle }}</span></td>
                                <td>{{ $b->inscription->classe->nom }}</td>
                                <td @class(['text-right font-semibold', 'text-red-700' => $b->moyenne_generale !== null && $b->moyenne_generale < 10])>{{ note_fr($b->moyenne_generale, $d) }}</td>
                                <td class="text-right">{{ $b->rang ?? '—' }} / {{ $b->effectif }}</td>
                                <td>{{ $b->mention ?? '—' }}@if ($b->distinction) · <span class="text-marque-700">{{ $b->distinction }}</span>@endif</td>
                                <td class="text-right"><a href="{{ route('bulletins.pdf', $b) }}" target="_blank" class="lien">Bulletin (PDF)</a></td>
                            </tr>
                        @endforeach
                    </x-tableau>
                @endif
            </x-carte>

            @foreach ($notes as $bloc)
                <x-carte :titre="'Notes — '.$bloc['periode']->libelle" class="mt-6" sans-marge>
                    <x-tableau>
                        <x-slot:entete><th>Matière</th><th>Évaluations</th></x-slot:entete>
                        @foreach ($bloc['matieres'] as $matiere => $lignes)
                            <tr>
                                <td class="font-medium">{{ $matiere }}</td>
                                <td>
                                    <div class="flex flex-wrap gap-2">
                                        @foreach ($lignes as $note)
                                            <span class="rounded-lg bg-slate-50 px-2 py-1 text-xs ring-1 ring-slate-200" title="{{ $note->evaluation->libelle }} du {{ date_fr($note->evaluation->date_evaluation) }}">
                                                {{ $note->evaluation->libelle }} :
                                                <strong>{{ $note->est_absent ? 'Abs.' : note_fr($note->valeur).'/'.note_fr($note->evaluation->bareme, 0) }}</strong>
                                            </span>
                                        @endforeach
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </x-tableau>
                </x-carte>
            @endforeach
            <p class="mt-3 text-xs text-slate-500">Seuls les résultats publiés par l'établissement apparaissent ici.</p>
            @break

        @case('assiduite')
            <div class="grid gap-6 lg:grid-cols-3">
                <x-carte titre="Par période" sans-marge>
                    @if ($periodes->isEmpty())
                        <x-vide message="Aucune période." icone="calendrier" />
                    @else
                        <x-tableau>
                            <x-slot:entete><th>Période</th><th class="text-right">Abs.</th><th class="text-right">Non just.</th><th class="text-right">Retards</th></x-slot:entete>
                            @foreach ($periodes as $p)
                                <tr>
                                    <td>{{ $p['periode']->libelle }}</td>
                                    <td class="text-right">{{ $p['absences'] }}</td>
                                    <td @class(['text-right', 'font-semibold text-red-700' => $p['non_justifiees'] > 0])>{{ $p['non_justifiees'] }}</td>
                                    <td class="text-right">{{ $p['retards'] }}</td>
                                </tr>
                            @endforeach
                        </x-tableau>
                        <p class="px-5 py-2 text-xs text-slate-500">En demi-journées.</p>
                    @endif
                </x-carte>
                <x-carte titre="Détail" sans-marge class="lg:col-span-2">
                    @if ($evenements->isEmpty())
                        <x-vide message="Aucune absence ni aucun retard enregistré." icone="coche" />
                    @else
                        <ul class="divide-y divide-slate-100">
                            @foreach ($evenements as $p)
                                <li class="flex flex-wrap items-center justify-between gap-2 px-5 py-2.5 text-sm">
                                    <span>{{ $p->appel->date_appel->translatedFormat('l j F Y') }} · {{ mb_strtolower($p->appel->creneau->libelle()) }}
                                        @if ($p->minutes_retard)<span class="text-slate-500">({{ $p->minutes_retard }} min)</span>@endif
                                        @if ($p->remarque)<span class="block text-xs text-slate-500">{{ $p->remarque }}</span>@endif</span>
                                    <x-badge :couleur="match ($p->statut) { \App\Enums\StatutPresence::Absent => 'rouge', \App\Enums\StatutPresence::Retard => 'orange', default => 'vert' }">{{ $p->statut->libelle() }}</x-badge>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-carte>
            </div>
            <p class="mt-3 text-xs text-slate-500">Pour justifier une absence, remettez le justificatif à la vie scolaire de l'établissement.</p>
            @break

        @case('emploi-du-temps')
            <x-carte>
                @if (collect($jours)->flatten()->isEmpty())
                    <x-vide message="L'emploi du temps n'est pas encore disponible." icone="calendrier" />
                @else
                    @include('pedagogie.emplois-du-temps._semaine', ['jours' => $jours, 'vue' => 'classe', 'modifiable' => false])
                @endif
            </x-carte>
            @break

        @case('finances')
            @if (! isset($situation))
                <x-carte><x-vide message="Aucune inscription : pas de situation financière." icone="portefeuille" /></x-carte>
                @break
            @endif
            @php $s = $situation; @endphp
            <div class="mb-6 grid gap-4 sm:grid-cols-3">
                <x-stat libelle="Total dû" :valeur="montant($s['du'])" :detail="$s['remises'] ? 'remises accordées : '.montant($s['remises']) : null" icone="portefeuille" />
                <x-stat libelle="Déjà payé" :valeur="montant($s['paye'])" icone="billet" couleur="vert" />
                <x-stat libelle="Reste à payer" :valeur="montant($s['solde'])" :detail="$s['retard'] ? 'dont '.montant($s['retard']).' en retard' : 'À jour'" icone="recu" :couleur="$s['retard'] ? 'rouge' : 'vert'" />
            </div>
            <div class="grid gap-6 lg:grid-cols-2">
                <x-carte titre="Échéancier" sans-marge>
                    @if ($s['lignes']->isEmpty())
                        <x-vide message="Aucun frais facturé." icone="portefeuille" />
                    @else
                        <x-tableau>
                            <x-slot:entete><th>Frais</th><th>Échéance</th><th class="text-right">Montant</th><th class="text-right">Reste</th></x-slot:entete>
                            @foreach ($s['lignes'] as $l)
                                <tr>
                                    <td>{{ $l['frais']->libelle }}</td>
                                    <td @class(['font-medium text-red-700' => $l['echu'] && $l['reste'] > 0])>{{ date_fr($l['frais']->date_limite) }}</td>
                                    <td class="text-right">{{ montant($l['du'] - $l['remises'], false) }}</td>
                                    <td class="text-right">@if ($l['reste'] <= 0)<x-badge couleur="vert">Réglé</x-badge>@else{{ montant($l['reste'], false) }}@endif</td>
                                </tr>
                            @endforeach
                        </x-tableau>
                    @endif
                </x-carte>
                <x-carte titre="Paiements et reçus" sans-marge>
                    @if ($paiements->isEmpty())
                        <x-vide message="Aucun paiement enregistré." icone="billet" />
                    @else
                        <x-tableau>
                            <x-slot:entete><th>Date</th><th>Reçu</th><th class="text-right">Montant</th><th></th></x-slot:entete>
                            @foreach ($paiements as $p)
                                <tr @class(['opacity-70' => $p->estAnnule()])>
                                    <td>{{ date_fr($p->date_paiement) }}</td>
                                    <td class="font-mono text-xs">{{ $p->numero_recu }}@if ($p->estAnnule()) <x-badge couleur="rouge">Annulé</x-badge>@endif</td>
                                    <td @class(['text-right font-medium', 'line-through' => $p->estAnnule()])>{{ montant($p->montant, false) }}</td>
                                    <td class="text-right"><a href="{{ route('paiements.recu', $p) }}" target="_blank" class="lien">Reçu (PDF)</a></td>
                                </tr>
                            @endforeach
                        </x-tableau>
                    @endif
                </x-carte>
            </div>
            <p class="mt-3 text-xs text-slate-500">Les paiements se font auprès de la caisse de l'établissement. Montants en {{ etablissement()->devise }}.</p>
            @break
    @endswitch
</x-layouts.app>
