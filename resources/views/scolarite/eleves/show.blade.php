<x-layouts.app :titre="$eleve->nom_complet">
    <x-entete :titre="$eleve->nom_complet" :sous-titre="'Matricule '.$eleve->matricule.($eleve->matricule_national ? ' · Matricule national '.$eleve->matricule_national : '')" :retour="route('eleves.index')">
        @can('update', $eleve)
            <x-bouton variante="secondaire" icone="crayon" :href="route('eleves.edit', $eleve)">Modifier</x-bouton>
            @if (! $eleve->user_id && $eleve->statut === \App\Enums\StatutEleve::Actif)
                <form method="POST" action="{{ route('eleves.compte', $eleve) }}" data-confirm="Créer le compte de l'espace élève ? Identifiant : {{ $eleve->matricule }}.">
                    @csrf <x-bouton variante="secondaire" icone="cle">Créer le compte élève</x-bouton>
                </form>
            @endif
            @if ($eleve->statut === \App\Enums\StatutEleve::Actif)
                <form method="POST" action="{{ route('eleves.archiver', $eleve) }}" data-confirm="Archiver le dossier de {{ $eleve->nom_complet }} ? Il n'apparaîtra plus dans les listes courantes.">
                    @csrf <x-bouton variante="secondaire">Archiver</x-bouton>
                </form>
            @else
                <form method="POST" action="{{ route('eleves.desarchiver', $eleve) }}">
                    @csrf <x-bouton variante="secondaire">Réactiver le dossier</x-bouton>
                </form>
            @endif
            @if ($eleve->inscriptions->isEmpty())
                <form method="POST" action="{{ route('eleves.destroy', $eleve) }}" data-confirm="Supprimer définitivement ce dossier (aucune inscription) ?">
                    @csrf @method('DELETE') <x-bouton variante="danger">Supprimer</x-bouton>
                </form>
            @endif
        @endcan
    </x-entete>

    @if ($eleve->statut === \App\Enums\StatutEleve::Archive)
        <div class="mb-5 rounded-xl border border-slate-200 bg-slate-100 px-4 py-3 text-sm text-slate-700">Dossier archivé : il est conservé mais n'apparaît plus dans les listes courantes.</div>
    @endif
    @if ($manquants = $eleve->elementsManquants())
        <div class="mb-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">Dossier incomplet : {{ implode(', ', $manquants) }}.</div>
    @endif

    @php
        $principal = $eleve->responsables->sortByDesc('pivot.est_contact_principal')->first();
        $ongletInitial = $errors->hasAny(['libelle', 'fichier']) ? 'documents' : 'informations';
        $onglets = ['informations' => 'Informations', 'notes' => 'Notes et bulletins', 'presences' => 'Présences', 'frais' => 'Frais', 'documents' => 'Documents'];
        if ($finances === null) {
            unset($onglets['frais']);
        }
    @endphp

    {{-- En-tête du dossier --}}
    <x-carte class="mb-6">
        <div class="flex flex-col gap-6 sm:flex-row">
            @if ($eleve->photo_chemin)
                <img src="{{ route('eleves.photo', $eleve) }}" alt="Photo de {{ $eleve->nom_complet }}" class="size-32 shrink-0 rounded-2xl object-cover ring-1 ring-slate-200">
            @else
                <span class="grid size-32 shrink-0 place-items-center rounded-2xl bg-marque-50 text-4xl font-semibold text-marque-700 ring-1 ring-marque-100">{{ mb_substr($eleve->prenoms, 0, 1) }}{{ mb_substr($eleve->nom, 0, 1) }}</span>
            @endif
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="text-xl font-semibold text-slate-900">{{ $eleve->prenoms }} {{ mb_strtoupper($eleve->nom) }}</h2>
                    @if ($eleve->statut === \App\Enums\StatutEleve::Archive)
                        <x-badge>Archivé</x-badge>
                    @elseif ($courante && $courante->estActive())
                        <x-badge couleur="vert">Actif</x-badge>
                    @elseif ($courante)
                        <x-badge couleur="rouge">Parti</x-badge>
                    @else
                        <x-badge couleur="orange">Non inscrit</x-badge>
                    @endif
                    @if ($eleve->user_id)<x-badge couleur="bleu">Compte élève</x-badge>@endif
                </div>
                <dl class="mt-4 grid gap-x-8 gap-y-3 text-sm sm:grid-cols-2 lg:grid-cols-3">
                    <div><dt class="text-slate-500">Matricule</dt><dd class="font-mono font-medium">{{ $eleve->matricule }}</dd></div>
                    <div><dt class="text-slate-500">Date de naissance</dt><dd class="font-medium">{{ date_fr($eleve->date_naissance) }} ({{ $eleve->date_naissance->age }} ans)</dd></div>
                    <div><dt class="text-slate-500">Sexe</dt><dd class="font-medium">{{ $eleve->sexe->libelle() }}</dd></div>
                    <div><dt class="text-slate-500">Classe</dt><dd class="font-medium">{{ $courante?->classe->nom ?? '—' }}</dd></div>
                    <div><dt class="text-slate-500">Niveau</dt><dd class="font-medium">{{ $courante ? $courante->classe->niveau->libelle.' · '.$courante->classe->niveau->cycle->libelle() : '—' }}</dd></div>
                    <div><dt class="text-slate-500">Responsable légal</dt><dd class="font-medium">{{ $principal ? $principal->nom_complet.' ('.\App\Enums\LienParente::from($principal->pivot->lien)->libelle().')' : '—' }}</dd></div>
                    <div><dt class="text-slate-500">Téléphone</dt><dd class="font-medium">@if ($principal)<a href="tel:{{ $principal->telephone }}" class="lien">{{ $principal->telephone }}</a>@else — @endif</dd></div>
                    <div class="sm:col-span-2"><dt class="text-slate-500">E-mail</dt><dd class="font-medium">{{ $principal?->email ?: '—' }}</dd></div>
                </dl>
            </div>
        </div>
    </x-carte>

    <div x-data="{ onglet: (@js(array_keys($onglets)).includes(location.hash.slice(1)) ? location.hash.slice(1) : @js($ongletInitial)) }">
        <nav class="mb-6 flex gap-1 overflow-x-auto border-b border-slate-200" role="tablist" aria-label="Sections du dossier">
            @foreach ($onglets as $cle => $libelle)
                <button type="button" role="tab" :aria-selected="onglet === '{{ $cle }}'" @click="onglet = '{{ $cle }}'; history.replaceState(null, '', '#{{ $cle }}')"
                        @class(['whitespace-nowrap border-b-2 px-3 py-2 text-sm font-medium', 'border-marque-600 text-marque-700' => $cle === $ongletInitial, 'border-transparent text-slate-500' => $cle !== $ongletInitial])
                        :class="onglet === '{{ $cle }}' ? 'border-marque-600 text-marque-700' : 'border-transparent text-slate-500 hover:text-slate-800'">{{ $libelle }}</button>
            @endforeach
        </nav>

        {{-- Informations --}}
        <div x-show="onglet === 'informations'" role="tabpanel" class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <x-carte titre="Identité">
                    <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                        <div><dt class="text-slate-500">Lieu de naissance</dt><dd class="font-medium">{{ $eleve->lieu_naissance ?: '—' }}</dd></div>
                        <div><dt class="text-slate-500">Nationalité</dt><dd class="font-medium">{{ $eleve->nationalite ?: '—' }}</dd></div>
                        <div><dt class="text-slate-500">Matricule national</dt><dd class="font-medium">{{ $eleve->matricule_national ?: '—' }}</dd></div>
                        <div><dt class="text-slate-500">Adresse</dt><dd class="font-medium">{{ $eleve->adresse ?: '—' }}</dd></div>
                        @if ($eleve->observations)
                            <div class="sm:col-span-2"><dt class="text-slate-500">Observations</dt><dd class="whitespace-pre-line">{{ $eleve->observations }}</dd></div>
                        @endif
                    </dl>
                </x-carte>

            {{-- Scolarité --}}
            <x-carte titre="Scolarité" sans-marge>
                @can('inscriptions.gerer')
                    <x-slot:actions>
                        @if ($classesDisponibles->isNotEmpty() && $eleve->statut === \App\Enums\StatutEleve::Actif)
                            <details class="relative">
                                <summary class="list-none"><span class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg bg-marque-600 px-2.5 py-1.5 text-xs font-medium text-white hover:bg-marque-700"><x-icone nom="plus" class="size-4" /> {{ $eleve->inscriptions->isEmpty() ? 'Inscrire' : 'Réinscrire' }}</span></summary>
                                <form method="POST" action="{{ route('inscriptions.store', $eleve) }}" class="absolute right-0 z-10 mt-2 w-80 space-y-3 rounded-xl border bg-white p-4 shadow-lg">
                                    @csrf
                                    <x-selection nom="classe_id" label="Classe" vide="— Choisir —" requis
                                        :options="$classesDisponibles->mapWithKeys(fn ($c) => [$c->id => $c->nom.' ('.$c->anneeScolaire->libelle.')'])" />
                                    <x-champ nom="date_inscription" label="Date d'inscription" type="date" :valeur="now()->toDateString()" requis />
                                    <x-champ nom="categorie_frais" label="Catégorie de frais" list="categories-frais" maxlength="30" :valeur="$eleve->inscriptions->first()?->categorie_frais" />
                                    <datalist id="categories-frais">@foreach ($categories as $categorie)<option value="{{ $categorie }}">@endforeach</datalist>
                                    <x-case nom="redoublant" label="Redoublant" />
                                    <x-bouton petit>Enregistrer l'inscription</x-bouton>
                                </form>
                            </details>
                        @endif
                    </x-slot:actions>
                @endcan

                @if ($eleve->inscriptions->isEmpty())
                    <x-vide message="Cet élève n'a encore jamais été inscrit." icone="inscription" />
                @else
                    <ul class="divide-y divide-slate-100">
                        @foreach ($eleve->inscriptions as $inscription)
                            @php $modifiable = ! $inscription->anneeScolaire->estCloturee(); @endphp
                            <li class="px-5 py-4">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <p class="font-medium text-slate-900">
                                            {{ $inscription->anneeScolaire->libelle }} —
                                            @can('eleves.consulter')<a href="{{ route('classes.show', $inscription->classe) }}" class="lien">{{ $inscription->classe->nom }}</a>@endcan
                                            @if ($inscription->anneeScolaire->est_active)<x-badge couleur="bleu" class="ml-1">Année en cours</x-badge>@endif
                                        </p>
                                        <p class="mt-0.5 text-sm text-slate-500">
                                            {{ $inscription->type->libelle() }} le {{ date_fr($inscription->date_inscription) }}
                                            @if ($inscription->redoublant) · Redoublant @endif
                                            @if ($inscription->categorie_frais) · Catégorie {{ $inscription->categorie_frais }} @endif
                                        </p>
                                        @if (! $inscription->estActive())
                                            <p class="mt-1 text-sm text-red-700">Parti le {{ date_fr($inscription->date_sortie) }} — {{ $inscription->motif_sortie }}</p>
                                        @endif
                                        @foreach ($inscription->changementsClasse as $changement)
                                            <p class="mt-1 text-xs text-slate-500">Changement de classe le {{ date_fr($changement->date_effet) }} : {{ $changement->ancienneClasse->nom }} → {{ $changement->nouvelleClasse->nom }}{{ $changement->motif ? ' ('.$changement->motif.')' : '' }}</p>
                                        @endforeach
                                    </div>
                                    <x-badge :couleur="$inscription->estActive() ? 'vert' : 'rouge'">{{ $inscription->statut->libelle() }}</x-badge>
                                </div>

                                @can('inscriptions.gerer')
                                    @if ($modifiable)
                                        <div class="mt-3 flex flex-wrap gap-4 text-sm">
                                            @if ($inscription->estActive())
                                                @php $autres = $classes->where('annee_scolaire_id', $inscription->annee_scolaire_id)->where('id', '!=', $inscription->classe_id); @endphp
                                                @if ($autres->isNotEmpty())
                                                    <details class="relative">
                                                        <summary class="lien cursor-pointer list-none">Changer de classe</summary>
                                                        <form method="POST" action="{{ route('inscriptions.changer-classe', $inscription) }}" class="absolute z-10 mt-2 w-80 space-y-3 rounded-xl border bg-white p-4 shadow-lg">
                                                            @csrf
                                                            <x-selection nom="nouvelle_classe_id" label="Nouvelle classe" :options="$autres->pluck('nom', 'id')" vide="— Choisir —" requis />
                                                            <x-champ nom="date_effet" label="Date d'effet" type="date" :valeur="now()->toDateString()" requis />
                                                            <x-champ nom="motif" label="Motif" maxlength="255" />
                                                            <p class="text-xs text-slate-500">Les notes, présences et frais déjà enregistrés sont conservés.</p>
                                                            <x-bouton petit>Valider le changement</x-bouton>
                                                        </form>
                                                    </details>
                                                @endif
                                                <details class="relative">
                                                    <summary class="cursor-pointer list-none font-medium text-red-600 hover:underline">Enregistrer un départ</summary>
                                                    <form method="POST" action="{{ route('inscriptions.depart', $inscription) }}" class="absolute z-10 mt-2 w-80 space-y-3 rounded-xl border bg-white p-4 shadow-lg"
                                                          data-confirm="Confirmer le départ de {{ $eleve->nom_complet }} ? Il ne figurera plus dans les appels ni les bulletins suivants.">
                                                        @csrf
                                                        <x-champ nom="date_sortie" label="Date de départ" type="date" :valeur="now()->toDateString()" requis />
                                                        <x-champ nom="motif_sortie" label="Motif" placeholder="Ex. déménagement, transfert" requis maxlength="255" />
                                                        <x-bouton variante="danger" petit>Enregistrer le départ</x-bouton>
                                                    </form>
                                                </details>
                                            @else
                                                <form method="POST" action="{{ route('inscriptions.annuler-depart', $inscription) }}" data-confirm="Annuler le départ et réintégrer l'élève en {{ $inscription->classe->nom }} ?">
                                                    @csrf <button class="lien">Annuler le départ (réintégrer)</button>
                                                </form>
                                            @endif
                                        </div>
                                    @endif
                                @endcan
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-carte>
            </div>
            <div>
            <x-carte titre="Responsables légaux" sans-marge>
                @can('update', $eleve)
                    <x-slot:actions><x-bouton petit variante="secondaire" icone="plus" :href="route('responsables.create', $eleve)">Ajouter</x-bouton></x-slot:actions>
                @endcan
                <ul class="divide-y divide-slate-100">
                    @forelse ($eleve->responsables->sortByDesc('pivot.est_contact_principal') as $responsable)
                        <li class="px-5 py-4 text-sm">
                            <p class="font-medium text-slate-900">{{ $responsable->nom_complet }}</p>
                            <p class="text-slate-500">{{ \App\Enums\LienParente::from($responsable->pivot->lien)->libelle() }}
                                @if ($responsable->profession) · {{ $responsable->profession }} @endif</p>
                            <div class="mt-1 flex flex-wrap gap-1">
                                @if ($responsable->pivot->est_contact_principal)<x-badge couleur="bleu">Contact principal</x-badge>@endif
                                @if ($responsable->pivot->est_payeur)<x-badge couleur="violet">Payeur</x-badge>@endif
                                @if ($responsable->user)<x-badge couleur="vert">Compte parent</x-badge>@endif
                            </div>
                            <p class="mt-2"><a href="tel:{{ $responsable->telephone }}" class="lien">{{ $responsable->telephone }}</a>
                                @if ($responsable->telephone_2) · {{ $responsable->telephone_2 }} @endif</p>
                            @if ($responsable->email)<p class="text-slate-600">{{ $responsable->email }}</p>@endif

                            @can('update', $eleve)
                                <div class="mt-3 flex flex-wrap gap-3">
                                    <a href="{{ route('responsables.edit', [$eleve, $responsable]) }}" class="lien">Modifier</a>
                                    @unless ($responsable->user)
                                        <form method="POST" action="{{ route('responsables.compte', [$eleve, $responsable]) }}" data-confirm="Créer un compte parent pour {{ $responsable->nom_complet }} ? Identifiant : {{ $responsable->telephone }}.">
                                            @csrf <button class="lien">Créer le compte parent</button>
                                        </form>
                                    @endunless
                                    @if ($eleve->responsables->count() > 1)
                                        <form method="POST" action="{{ route('responsables.destroy', [$eleve, $responsable]) }}" data-confirm="Détacher {{ $responsable->nom_complet }} de cet élève ?">
                                            @csrf @method('DELETE') <button class="font-medium text-red-600 hover:underline">Détacher</button>
                                        </form>
                                    @endif
                                </div>
                            @endcan
                        </li>
                    @empty
                        <li class="px-5 py-4 text-sm text-red-700">Aucun responsable légal : ajoutez-en au moins un.</li>
                    @endforelse
                </ul>
            </x-carte>
            </div>
        </div>

        {{-- Notes et bulletins --}}
        <div x-show="onglet === 'notes'" x-cloak role="tabpanel">
            @if ($bulletins === null)
                <x-carte><x-vide message="Les résultats sont consultables par la direction, la vie scolaire et le secrétariat (bulletins)." icone="diplome" /></x-carte>
            @else
                <x-carte titre="Bulletins" sans-marge>
                    @if ($bulletins->isEmpty())
                        <x-vide message="Aucun bulletin publié pour cet élève." icone="diplome" />
                    @else
                        <x-tableau>
                            <x-slot:entete><th>Période</th><th>Classe</th><th class="text-right">Moyenne</th><th class="text-right">Rang</th><th>Mention</th><th>État</th><th></th></x-slot:entete>
                            @foreach ($bulletins as $b)
                                <tr>
                                    <td class="font-medium">{{ $b->periode->libelle }} <span class="text-xs text-slate-500">{{ $b->periode->anneeScolaire->libelle }}</span></td>
                                    <td>{{ $b->inscription->classe->nom }}</td>
                                    <td @class(['text-right font-semibold', 'text-red-700' => $b->moyenne_generale !== null && $b->moyenne_generale < 10])>{{ note_fr($b->moyenne_generale) }}</td>
                                    <td class="text-right">{{ $b->rang ?? '—' }} / {{ $b->effectif }}</td>
                                    <td>{{ $b->mention ?? '—' }}</td>
                                    <td>
                                        @if ($b->estVisibleDesFamilles())<x-badge couleur="vert">Publié</x-badge>@else<x-badge couleur="violet">Retiré</x-badge>@endif
                                        @if ($b->version > 1)<span class="text-xs text-slate-500">v{{ $b->version }}</span>@endif
                                    </td>
                                    <td class="text-right"><x-action-icone :href="route('bulletins.pdf', $b)" icone="imprimer" libelle="Bulletin PDF" target="_blank" /></td>
                                </tr>
                            @endforeach
                        </x-tableau>
                    @endif
                </x-carte>
                @if ($courante && auth()->user()->can('notes.consulter'))
                    <p class="mt-3 text-sm"><a href="{{ route('resultats.classe', ['classe' => $courante->classe_id]) }}" class="lien">Voir les résultats en cours de la classe {{ $courante->classe->nom }} →</a></p>
                @endif
            @endif
        </div>

        {{-- Présences --}}
        <div x-show="onglet === 'presences'" x-cloak role="tabpanel" class="grid gap-6 lg:grid-cols-3">
            <div>
                @if ($assiduite->isNotEmpty())
            @if ($assiduite->isNotEmpty())
                <x-carte titre="Assiduité de l'année" sans-marge>
                    <x-tableau>
                        <x-slot:entete><th>Période</th><th class="text-right" title="Demi-journées d'absence">Abs.</th><th class="text-right" title="Non justifiées">Non just.</th><th class="text-right">Retards</th></x-slot:entete>
                        @foreach ($assiduite as $ligne)
                            <tr>
                                <td>{{ $ligne['periode']->libelle }}</td>
                                <td class="text-right">{{ $ligne['absences'] }}</td>
                                <td @class(['text-right', 'font-semibold text-red-700' => $ligne['non_justifiees'] > 0])>{{ $ligne['non_justifiees'] }}</td>
                                <td class="text-right">{{ $ligne['retards'] }}</td>
                            </tr>
                        @endforeach
                    </x-tableau>
                    <p class="px-5 py-2 text-xs text-slate-500">En demi-journées.</p>
                </x-carte>
            @endif
                @else
                    <x-carte><x-vide message="Pas d'inscription pour l'année en cours." icone="coche" /></x-carte>
                @endif
            </div>
            <x-carte titre="Absences et retards récents" sans-marge class="lg:col-span-2">
                @forelse ($evenements as $p)
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-5 py-2.5 text-sm last:border-0">
                        <span>{{ $p->appel->date_appel->translatedFormat('l j F Y') }} · {{ mb_strtolower($p->appel->creneau->libelle()) }}
                            @if ($p->minutes_retard)<span class="text-slate-500">({{ $p->minutes_retard }} min)</span>@endif</span>
                        <x-badge :couleur="match ($p->statut) { \App\Enums\StatutPresence::Absent => 'rouge', \App\Enums\StatutPresence::Retard => 'orange', default => 'vert' }">{{ $p->statut->libelle() }}</x-badge>
                    </div>
                @empty
                    <x-vide message="Aucune absence ni aucun retard." icone="coche" />
                @endforelse
            </x-carte>
        </div>

        {{-- Frais --}}
        @if ($finances !== null)
            <div x-show="onglet === 'frais'" x-cloak role="tabpanel">
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <x-stat libelle="Total dû" :valeur="montant($finances['du'])" :detail="$finances['remises'] ? 'remises : '.montant($finances['remises']) : null" icone="portefeuille" />
                    <x-stat libelle="Payé" :valeur="montant($finances['paye'])" icone="billet" couleur="vert" />
                    <x-stat libelle="Reste à payer" :valeur="montant($finances['solde'])" icone="recu" :couleur="$finances['solde'] > 0 ? 'violet' : 'vert'" />
                    <x-stat libelle="En retard" :valeur="montant($finances['retard'])" icone="alerte" :couleur="$finances['retard'] > 0 ? 'rouge' : 'vert'" />
                </div>
                <p class="mt-4"><x-bouton :href="route('finances.show', $courante)" icone="portefeuille">Ouvrir la situation financière</x-bouton></p>
            </div>
        @endif

        {{-- Documents --}}
        <div x-show="onglet === 'documents'" x-cloak role="tabpanel">
            {{-- Pièces du dossier --}}
            <x-carte titre="Pièces du dossier" sans-marge>
                @if ($eleve->documents->isEmpty())
                    <x-vide message="Aucune pièce enregistrée (acte de naissance, certificat de scolarité…)." icone="trombone" />
                @else
                    <x-tableau>
                        <x-slot:entete><th>Pièce</th><th>Fichier</th><th>Ajoutée le</th><th></th></x-slot:entete>
                        @foreach ($eleve->documents as $document)
                            <tr>
                                <td class="font-medium">{{ $document->libelle }}</td>
                                <td class="text-xs text-slate-500">{{ $document->fichier_nom }} ({{ number_format($document->fichier_taille / 1024, 0, ',', ' ') }} Ko)</td>
                                <td>{{ date_fr($document->created_at) }}<span class="block text-xs text-slate-500">{{ $document->auteur?->name }}</span></td>
                                <td class="whitespace-nowrap text-right">
                                    <a href="{{ route('documents.show', [$eleve, $document]) }}" class="lien">Télécharger</a>
                                    @can('update', $eleve)
                                        <form method="POST" action="{{ route('documents.destroy', [$eleve, $document]) }}" class="ml-3 inline" data-confirm="Retirer la pièce « {{ $document->libelle }} » du dossier ?">
                                            @csrf @method('DELETE') <button class="text-sm font-medium text-red-600 hover:underline">Retirer</button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </x-tableau>
                @endif
                @can('update', $eleve)
                    <form method="POST" action="{{ route('documents.store', $eleve) }}" enctype="multipart/form-data" class="grid gap-3 border-t border-slate-100 p-4 sm:grid-cols-3 sm:items-end">
                        @csrf
                        <x-champ nom="libelle" label="Intitulé de la pièce" placeholder="Ex. Acte de naissance" requis maxlength="100" />
                        <x-champ nom="fichier" label="Fichier" type="file" accept=".pdf,image/jpeg,image/png" requis :aide="'PDF, JPG ou PNG, '.intdiv($tailleMaxKo, 1024).' Mo au maximum.'" />
                        <div><x-bouton variante="secondaire" icone="trombone">Ajouter</x-bouton></div>
                    </form>
                @endcan
            </x-carte>
        </div>
    </div>
</x-layouts.app>
