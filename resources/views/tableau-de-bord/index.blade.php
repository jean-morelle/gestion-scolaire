<x-layouts.app titre="Tableau de bord">
    <x-entete :titre="'Bonjour '.$user->name" :sous-titre="now()->translatedFormat('l j F Y').($annee ? ' · année scolaire '.$annee->libelle : ' · aucune année scolaire active')" />

    @if (! $annee && $user->can('etablissement.gerer'))
        <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            Aucune année scolaire n'est active. <a href="{{ route('annees.index') }}" class="font-medium underline">Créez ou activez une année</a> pour commencer.
        </div>
    @endif

    <div class="space-y-8">
        {{-- Pilotage (direction, censeur) --}}
        @isset($blocs['direction'])
            @php $b = $blocs['direction']; @endphp
            <section>
                <h2 class="mb-3 text-sm font-semibold tracking-wide text-slate-500 uppercase">Vue d'ensemble</h2>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <x-stat libelle="Élèves inscrits" :valeur="$b['effectif']" :detail="$b['filles'].' filles · '.($b['effectif'] - $b['filles']).' garçons · '.$b['classes'].' classes'" icone="utilisateurs" :href="Route::has('rapports.scolaires') ? route('rapports.scolaires') : null" />
                    <x-stat libelle="Absents aujourd'hui" :valeur="$b['absents_jour']" icone="coche" :couleur="$b['absents_jour'] ? 'orange' : 'vert'" :href="route('appels.index')" />
                    <x-stat :libelle="'Évaluations à valider'.($b['periode'] ? ' ('.$b['periode']->libelle.')' : '')" :valeur="$b['brouillons']" icone="crayon" :couleur="$b['brouillons'] ? 'orange' : 'vert'" />
                    <x-stat libelle="Bulletins publiés" :valeur="$b['classes_publiees'].' / '.$b['classes']" :detail="$b['periode'] ? 'classes, '.$b['periode']->libelle : null" icone="diplome" couleur="violet" :href="Route::has('bulletins.index') && auth()->user()->can('bulletins.consulter') ? route('bulletins.index') : null" />
                </div>
                <div class="mt-4 grid gap-6 lg:grid-cols-5">
                    <x-carte titre="Évolution des effectifs" class="lg:col-span-3">
                        <p class="-mt-2 mb-3 text-xs text-slate-500">Élèves présents à la fin de chaque mois, {{ $annee->libelle }}</p>
                        @if (count($b['evolution']) && $b['effectif'] > 0)
                            <x-graphique.colonnes unite="élèves" description="Évolution mensuelle des effectifs"
                                :points="collect($b['evolution'])->map(fn ($m) => ['libelle' => ucfirst($m['mois']->translatedFormat('M')), 'libelle_long' => ucfirst($m['mois']->translatedFormat('F Y')),
                                    'valeur' => $m['effectif'], 'details' => ['Arrivées' => $m['arrivees'], 'Départs' => $m['departs']]])->all()" />
                        @else
                            <x-vide message="Aucun élève inscrit pour l'instant." icone="graphique" />
                        @endif
                    </x-carte>
                    <x-carte titre="Répartition par niveau" class="lg:col-span-2">
                        @if ($b['par_niveau']->isEmpty())
                            <x-vide message="Aucun élève inscrit." icone="classe" />
                        @else
                            @php $parCycle = $b['par_niveau']->groupBy(fn ($n) => $n->cycle instanceof \BackedEnum ? $n->cycle->value : $n->cycle)->map->sum('total'); @endphp
                            <div class="mb-4 flex flex-wrap gap-x-5 gap-y-1 text-sm">
                                @foreach (['primaire' => 'Primaire', 'college' => 'Collège', 'lycee' => 'Lycée'] as $cle => $libelle)
                                    @if ($parCycle->has($cle))
                                        <span class="text-slate-600">{{ $libelle }} <span class="font-semibold text-slate-900">{{ note_fr($parCycle[$cle] / $b['effectif'] * 100, 0) }} %</span></span>
                                    @endif
                                @endforeach
                            </div>
                            <x-graphique.barres unite="élèves" description="Nombre d'élèves par niveau"
                                :lignes="$b['par_niveau']->map(fn ($n) => ['libelle' => $n->libelle, 'valeur' => (int) $n->total])->all()" />
                        @endif
                    </x-carte>
                </div>

                <div class="mt-4 grid gap-6 lg:grid-cols-2">
                    <x-carte titre="Dernières inscriptions" sans-marge>
                        @forelse ($b['dernieres_inscriptions'] as $i)
                            <div class="flex items-center gap-3 border-b border-slate-100 px-5 py-2.5 text-sm last:border-0">
                                <x-avatar :eleve="$i->eleve" />
                                <span class="min-w-0 flex-1"><span class="block truncate font-medium text-slate-900">{{ $i->eleve->nom_complet }}</span><span class="text-xs text-slate-500">{{ $i->classe->nom }}</span></span>
                                <span class="text-xs text-slate-500">{{ date_fr($i->date_inscription) }}</span>
                            </div>
                        @empty
                            <x-vide message="Aucune inscription." icone="inscription" />
                        @endforelse
                    </x-carte>
                    @isset($b['paiements_recents'])
                        <x-carte titre="Paiements récents" sans-marge>
                            @forelse ($b['paiements_recents'] as $p)
                                <div class="flex items-center gap-3 border-b border-slate-100 px-5 py-2.5 text-sm last:border-0">
                                    <span class="size-2 shrink-0 rounded-full bg-emerald-500" aria-hidden="true"></span>
                                    <span class="min-w-0 flex-1"><span class="block truncate font-medium text-slate-900">{{ $p->inscription->eleve->nom_complet }}</span><span class="text-xs text-slate-500">{{ $p->numero_recu }} · {{ date_fr($p->date_paiement) }}</span></span>
                                    <span class="font-medium text-slate-900">{{ montant($p->montant) }}</span>
                                </div>
                            @empty
                                <x-vide message="Aucun paiement." icone="billet" />
                            @endforelse
                        </x-carte>
                    @endisset
                </div>

                @isset($b['finances'])
                    <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <x-stat libelle="Encaissé ce mois" :valeur="montant($b['finances']['encaisse_mois'])" icone="billet" couleur="vert" />
                        <x-stat libelle="Reste à encaisser" :valeur="montant($b['finances']['reste'])" :detail="$b['finances']['taux'] !== null ? 'taux de recouvrement : '.note_fr($b['finances']['taux'], 0).' %' : null" icone="portefeuille" />
                        <x-stat libelle="Impayés échus" :valeur="montant($b['finances']['retard'])" :detail="$b['finances']['eleves_en_retard'].' élève(s) en retard'" icone="alerte" couleur="rouge" :href="route('finances.index', ['situation' => 'retard'])" />
                        @can('rapports.financiers')
                            <x-stat libelle="Rapports financiers" valeur="Consulter" icone="graphique" couleur="violet" :href="route('rapports.financiers')" />
                        @endcan
                    </div>
                @endisset
            </section>
        @endisset

        {{-- Vie scolaire --}}
        @isset($blocs['vie_scolaire'])
            @php $b = $blocs['vie_scolaire']; @endphp
            <section>
                <h2 class="mb-3 text-sm font-semibold tracking-wide text-slate-500 uppercase">Vie scolaire</h2>
                <div class="grid gap-4 sm:grid-cols-3">
                    <x-stat :libelle="'Appels faits ('.mb_strtolower($b['creneau']->libelle()).')'" :valeur="$b['appels_faits'].' / '.$b['appels_attendus']" icone="coche"
                        :couleur="$b['appels_faits'] < $b['appels_attendus'] ? 'orange' : 'vert'" :href="route('appels.index')" />
                    <x-stat libelle="Absences non justifiées" :valeur="$b['non_justifiees']" detail="cette année" icone="alerte" :couleur="$b['non_justifiees'] ? 'rouge' : 'vert'"
                        :href="auth()->user()->can('presences.justifier') ? route('justificatifs.index') : null" />
                    <x-stat libelle="Retards cette semaine" :valeur="$b['retards_semaine']" icone="calendrier" />
                </div>
            </section>
        @endisset

        {{-- Enseignant --}}
        @isset($blocs['enseignant'])
            @php $b = $blocs['enseignant']; @endphp
            <section>
                <h2 class="mb-3 text-sm font-semibold tracking-wide text-slate-500 uppercase">Mon enseignement</h2>
                <div class="grid gap-4 sm:grid-cols-3">
                    <x-stat libelle="Mes classes" :valeur="$b['classes']" icone="classe" :href="route('mes-classes.index')" />
                    <x-stat libelle="Évaluations à compléter" :valeur="$b['evaluations_brouillon']->count()" icone="crayon" :couleur="$b['evaluations_brouillon']->isNotEmpty() ? 'orange' : 'vert'" :href="route('evaluations.index', ['statut' => 'brouillon'])" />
                    <x-stat libelle="Copies à corriger" :valeur="$b['copies']" icone="document" :couleur="$b['copies'] ? 'orange' : 'vert'" :href="route('cours.index')" />
                </div>
                <div class="mt-4 grid gap-6 lg:grid-cols-2">
                    <x-carte titre="Mes cours aujourd'hui" sans-marge>
                        @forelse ($b['cours_du_jour'] as $c)
                            <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-2.5 text-sm last:border-0">
                                <span><span class="font-medium">{{ $c->horaire() }}</span> · {{ $c->affectation->classe->nom }} — {{ $c->affectation->matiere->libelle }}{{ $c->salle ? ' · '.$c->salle : '' }}</span>
                                <a href="{{ route('appels.saisie', [$c->affectation->classe, 'creneau' => substr($c->heure_debut, 0, 2) < 13 ? 'matin' : 'apres_midi']) }}" class="lien shrink-0">Appel</a>
                            </div>
                        @empty
                            <x-vide message="Pas de cours aujourd'hui dans votre emploi du temps." icone="calendrier" />
                        @endforelse
                    </x-carte>
                    <x-carte titre="Évaluations en brouillon" sans-marge>
                        @forelse ($b['evaluations_brouillon'] as $e)
                            <a href="{{ route('evaluations.show', $e) }}" class="flex justify-between gap-3 border-b border-slate-100 px-5 py-2.5 text-sm last:border-0 hover:bg-slate-50">
                                <span>{{ $e->libelle }} <span class="text-slate-500">· {{ $e->affectation->classe->nom }} — {{ $e->affectation->matiere->libelle }}</span></span>
                                <span class="text-slate-500">{{ date_fr($e->date_evaluation) }}</span>
                            </a>
                        @empty
                            <x-vide message="Toutes vos évaluations sont validées." icone="coche" />
                        @endforelse
                    </x-carte>
                </div>
            </section>
        @endisset

        {{-- Secrétariat --}}
        @isset($blocs['secretariat'])
            @php $b = $blocs['secretariat']; @endphp
            <section>
                <h2 class="mb-3 text-sm font-semibold tracking-wide text-slate-500 uppercase">Secrétariat</h2>
                <div class="grid gap-6 lg:grid-cols-2">
                    <x-carte titre="Inscriptions récentes" sans-marge>
                        <x-slot:actions><x-bouton petit icone="plus" :href="route('eleves.create')">Nouvel élève</x-bouton></x-slot:actions>
                        @forelse ($b['recentes'] as $i)
                            <a href="{{ route('eleves.show', $i->eleve) }}" class="flex justify-between gap-3 border-b border-slate-100 px-5 py-2.5 text-sm last:border-0 hover:bg-slate-50">
                                <span class="font-medium">{{ $i->eleve->nom_complet }}</span><span class="text-slate-500">{{ $i->classe->nom }} · {{ date_fr($i->date_inscription) }}</span>
                            </a>
                        @empty
                            <x-vide message="Aucune inscription pour l'année active." icone="inscription" />
                        @endforelse
                    </x-carte>
                    <x-carte :titre="'Dossiers incomplets ('.$b['nb_incomplets'].')'" sans-marge>
                        @forelse ($b['incomplets'] as $l)
                            <a href="{{ route('eleves.show', $l['eleve']) }}" class="block border-b border-slate-100 px-5 py-2.5 text-sm last:border-0 hover:bg-slate-50">
                                <span class="font-medium">{{ $l['eleve']->nom_complet }}</span>
                                <span class="block text-xs text-amber-700">Manque : {{ implode(', ', $l['manquants']) }}</span>
                            </a>
                        @empty
                            <x-vide message="Tous les dossiers sont complets." icone="coche" />
                        @endforelse
                        @if ($b['non_inscrits'])
                            <p class="border-t border-slate-100 px-5 py-2.5 text-sm"><a href="{{ route('eleves.index', ['inscription' => 'non_inscrit']) }}" class="lien">{{ $b['non_inscrits'] }} dossier(s) actif(s) sans inscription cette année</a></p>
                        @endif
                    </x-carte>
                </div>
            </section>
        @endisset

        {{-- Comptabilité --}}
        @isset($blocs['comptabilite'])
            @php $b = $blocs['comptabilite']; @endphp
            <section>
                <h2 class="mb-3 text-sm font-semibold tracking-wide text-slate-500 uppercase">Comptabilité</h2>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <x-stat libelle="Encaissé aujourd'hui" :valeur="montant($b['encaisse_jour'])" icone="billet" couleur="vert" :href="route('paiements.index', ['du' => today()->toDateString()])" />
                    <x-stat libelle="Encaissé ce mois" :valeur="montant($b['encaisse_mois'])" icone="portefeuille" :href="route('paiements.index')" />
                    <x-stat libelle="Dépenses du mois" :valeur="montant($b['depenses_mois'])" icone="recu" couleur="rouge" :href="Route::has('depenses.index') && auth()->user()->canAny(['depenses.gerer', 'depenses.annuler', 'rapports.financiers']) ? route('depenses.index') : null" />
                    <x-stat libelle="Impayés échus" :valeur="montant($b['total_retard'])" icone="alerte" couleur="orange" :href="route('finances.index', ['situation' => 'retard'])" />
                </div>
                <div class="mt-4 grid gap-6 lg:grid-cols-2">
                    <x-carte titre="Derniers reçus" sans-marge>
                        @forelse ($b['recus'] as $p)
                            <a href="{{ route('paiements.recu', $p) }}" target="_blank" class="flex justify-between gap-3 border-b border-slate-100 px-5 py-2.5 text-sm last:border-0 hover:bg-slate-50">
                                <span><span class="font-mono text-xs">{{ $p->numero_recu }}</span> · {{ $p->inscription->eleve->nom_complet }}</span>
                                <span @class(['font-medium', 'line-through text-slate-400' => $p->estAnnule()])>{{ montant($p->montant) }}</span>
                            </a>
                        @empty
                            <x-vide message="Aucun paiement enregistré." icone="billet" />
                        @endforelse
                    </x-carte>
                    <x-carte titre="Plus gros retards" sans-marge>
                        @forelse ($b['retards'] as $l)
                            <a href="{{ route('finances.show', $l['inscription']) }}" class="flex justify-between gap-3 border-b border-slate-100 px-5 py-2.5 text-sm last:border-0 hover:bg-slate-50">
                                <span>{{ $l['inscription']->eleve->nom_complet }} <span class="text-slate-500">· {{ $l['inscription']->classe->nom }}</span></span>
                                <span class="font-medium text-red-700">{{ montant($l['retard']) }}</span>
                            </a>
                        @empty
                            <x-vide message="Aucun élève en retard de paiement." icone="coche" />
                        @endforelse
                    </x-carte>
                </div>
            </section>
        @endisset

        {{-- Administration --}}
        @isset($blocs['administration'])
            @php $b = $blocs['administration']; @endphp
            <section>
                <h2 class="mb-3 text-sm font-semibold tracking-wide text-slate-500 uppercase">Administration</h2>
                <div class="grid gap-6 lg:grid-cols-3">
                    <div class="space-y-4">
                        <x-stat libelle="Comptes actifs" :valeur="$b['comptes_actifs']" icone="cle" :href="route('utilisateurs.index')" />
                        <x-stat libelle="Jamais connectés" :valeur="$b['jamais_connectes']" icone="alerte" couleur="orange" :href="route('utilisateurs.index', ['statut' => 'actif'])" />
                    </div>
                    @if ($b['journal']->isNotEmpty())
                        <x-carte titre="Activité récente" sans-marge class="lg:col-span-2">
                            <x-slot:actions><a href="{{ route('journal.index') }}" class="lien text-sm">Journal complet</a></x-slot:actions>
                            @foreach ($b['journal'] as $j)
                                <div class="flex justify-between gap-3 border-b border-slate-100 px-5 py-2 text-sm last:border-0">
                                    <span class="min-w-0 truncate">{{ $j->description ?? $j->action }}</span>
                                    <span class="shrink-0 text-xs text-slate-500">{{ $j->user?->name ?? 'Système' }} · {{ $j->created_at->diffForHumans() }}</span>
                                </div>
                            @endforeach
                        </x-carte>
                    @endif
                </div>
            </section>
        @endisset

        {{-- Annonces (tous) --}}
        <section>
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-sm font-semibold tracking-wide text-slate-500 uppercase">Annonces</h2>
                <a href="{{ route('annonces.index') }}" class="lien text-sm">Toutes les annonces</a>
            </div>
            @forelse ($blocs['annonces']['liste'] as $a)
                <a href="{{ route('annonces.show', $a) }}" class="mb-3 block rounded-2xl border border-slate-200 bg-white p-4 shadow-xs hover:border-marque-300">
                    <p class="font-medium text-slate-900">{{ $a->titre }}</p>
                    <p class="mt-1 line-clamp-2 text-sm text-slate-600">{{ $a->contenu }}</p>
                    <p class="mt-1 text-xs text-slate-400">{{ $a->publiee_at->translatedFormat('j F Y') }}</p>
                </a>
            @empty
                <x-carte><x-vide message="Aucune annonce en cours." icone="megaphone" /></x-carte>
            @endforelse
        </section>
    </div>
</x-layouts.app>
