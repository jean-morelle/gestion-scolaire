<x-layouts.app :titre="'Finances — '.$inscription->eleve->nom_complet">
    @php $s = $situation; $modifiable = ! $inscription->anneeScolaire->estCloturee(); @endphp
    <x-entete :titre="$inscription->eleve->nom_complet" :sous-titre="'Matricule '.$inscription->eleve->matricule.' · '.$inscription->classe->nom.' · '.$inscription->anneeScolaire->libelle.($inscription->categorie_frais ? ' · catégorie '.$inscription->categorie_frais : '')"
              :retour="route('finances.index')">
        @can('eleves.consulter')
            <x-bouton variante="secondaire" icone="utilisateurs" :href="route('eleves.show', $inscription->eleve)">Dossier élève</x-bouton>
        @endcan
    </x-entete>

    @if (session('recu'))
        <div class="mb-5 flex flex-wrap items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
            <x-icone nom="recu" class="size-5" /> Reçu prêt à imprimer.
            <x-bouton petit variante="succes" icone="imprimer" :href="route('paiements.recu', session('recu'))" target="_blank">Imprimer le reçu</x-bouton>
        </div>
    @endif

    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat libelle="Total dû" :valeur="montant($s['du'])" :detail="$s['remises'] ? 'remises : '.montant($s['remises']) : null" icone="portefeuille" />
        <x-stat libelle="Payé" :valeur="montant($s['paye'])" :detail="$nbPaiementsValides.' paiement(s)'" icone="billet" couleur="vert" />
        <x-stat libelle="Reste à payer" :valeur="montant($s['solde'])" icone="recu" :couleur="$s['solde'] > 0 ? 'violet' : 'vert'" />
        <x-stat libelle="En retard" :valeur="montant($s['retard'])" icone="alerte" :couleur="$s['retard'] > 0 ? 'rouge' : 'vert'" />
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-carte titre="Frais et échéances" sans-marge>
                @if ($s['lignes']->isEmpty())
                    <x-vide message="Aucun frais facturé à cet élève." icone="portefeuille">
                        @can('frais.configurer')
                            <form method="POST" action="{{ route('finances.generer', $inscription) }}">@csrf <x-bouton petit variante="secondaire">Générer les frais obligatoires</x-bouton></form>
                        @endcan
                    </x-vide>
                @else
                    <x-tableau>
                        <x-slot:entete><th>Frais</th><th>Échéance</th><th class="text-right">Dû</th><th class="text-right">Remise</th><th class="text-right">Payé</th><th class="text-right">Reste</th><th></th></x-slot:entete>
                        @foreach ($s['lignes'] as $l)
                            <tr>
                                <td class="font-medium">{{ $l['frais']->libelle }}</td>
                                <td @class(['text-red-700 font-medium' => $l['echu'] && $l['reste'] > 0])>{{ date_fr($l['frais']->date_limite) }}</td>
                                <td class="text-right">{{ montant($l['du'], false) }}</td>
                                <td class="text-right">{{ $l['remises'] ? montant($l['remises'], false) : '—' }}</td>
                                <td class="text-right">{{ montant($l['paye'], false) }}</td>
                                <td class="text-right font-semibold">@if ($l['reste'] <= 0)<x-badge couleur="vert">Réglé</x-badge>@else{{ montant($l['reste'], false) }}@endif</td>
                                <td class="text-right">
                                    @can('remises.accorder')
                                        @if ($l['reste'] > 0 && $modifiable)
                                            <details class="relative inline-block text-left">
                                                <summary class="lien cursor-pointer list-none">Remise</summary>
                                                <form method="POST" action="{{ route('remises.store', $l['frais']) }}" class="absolute right-0 z-10 mt-2 w-72 space-y-3 rounded-xl border bg-white p-4 shadow-lg">
                                                    @csrf
                                                    <x-champ nom="montant" :label="'Montant (max. '.montant($l['reste']).')'" type="number" min="1" :max="$l['reste']" requis />
                                                    <x-champ nom="motif" label="Motif" placeholder="Ex. bourse, fratrie, enfant du personnel" requis maxlength="255" />
                                                    <x-bouton petit>Accorder</x-bouton>
                                                </form>
                                            </details>
                                        @endif
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                        <tr class="bg-slate-50 font-semibold">
                            <td colspan="2">Total</td>
                            <td class="text-right">{{ montant($s['du'], false) }}</td><td class="text-right">{{ montant($s['remises'], false) }}</td>
                            <td class="text-right">{{ montant($s['paye'], false) }}</td><td class="text-right">{{ montant($s['solde'], false) }}</td><td></td>
                        </tr>
                    </x-tableau>
                @endif
                @can('frais.configurer')
                    @if ($modifiable && ($facultatifs->isNotEmpty() || $s['lignes']->isNotEmpty()))
                        <div class="flex flex-wrap items-end gap-3 border-t border-slate-100 p-4">
                            @if ($facultatifs->isNotEmpty())
                                <form method="POST" action="{{ route('finances.attribuer', $inscription) }}" class="flex flex-wrap items-end gap-2">
                                    @csrf
                                    <x-selection nom="grille_frais_id" label="Ajouter un frais facultatif" vide="— Choisir —" requis class="w-64"
                                        :options="$facultatifs->mapWithKeys(fn ($g) => [$g->id => $g->typeFrais->libelle.($g->categorie ? ' ('.$g->categorie.')' : '').' — '.montant($g->montant)])" />
                                    <x-bouton variante="secondaire" icone="plus">Attribuer</x-bouton>
                                </form>
                            @endif
                            @php $retirables = $s['lignes']->groupBy(fn ($l) => $l['frais']->type_frais_id)->filter(fn ($g) => $g->sum('paye') === 0 && $g->sum('remises') === 0); @endphp
                            @if ($retirables->isNotEmpty())
                                <form method="POST" action="{{ route('finances.retirer', $inscription) }}" class="flex flex-wrap items-end gap-2" data-confirm="Retirer ce frais de la facture de l'élève ?">
                                    @csrf @method('DELETE')
                                    <x-selection nom="type_frais_id" label="Retirer un frais (sans paiement)" vide="— Choisir —" requis class="w-56"
                                        :options="$retirables->map(fn ($g) => $g->first()['frais']->typeFrais->libelle)" />
                                    <x-bouton variante="secondaire">Retirer</x-bouton>
                                </form>
                            @endif
                        </div>
                    @endif
                @endcan
            </x-carte>

            <x-carte titre="Historique des paiements" sans-marge>
                @if ($paiements->isEmpty())
                    <x-vide message="Aucun paiement enregistré." icone="billet" />
                @else
                    <x-tableau>
                        <x-slot:entete><th>Reçu</th><th>Date</th><th class="text-right">Montant</th><th>Mode</th><th>Encaissé par</th><th>Statut</th><th></th></x-slot:entete>
                        @foreach ($paiements as $p)
                            <tr @class(['opacity-70' => $p->estAnnule()])>
                                <td class="font-mono text-xs">{{ $p->numero_recu }}</td>
                                <td>{{ date_fr($p->date_paiement) }}</td>
                                <td @class(['text-right font-semibold', 'line-through' => $p->estAnnule()])>{{ montant($p->montant, false) }}</td>
                                <td>{{ $p->mode->libelle() }}@if ($p->reference_externe)<span class="block text-xs text-slate-500">{{ $p->reference_externe }}</span>@endif</td>
                                <td>{{ $p->encaisseur->name }}</td>
                                <td>
                                    @if ($p->estAnnule())
                                        <x-badge couleur="rouge">Annulé</x-badge>
                                        <span class="block text-xs text-slate-500">{{ $p->annulateur?->name }}, {{ $p->annule_at?->format('d/m/Y') }} : {{ $p->motif_annulation }}</span>
                                    @else
                                        <x-badge couleur="vert">Valide</x-badge>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap text-right">
                                    <a href="{{ route('paiements.recu', $p) }}" target="_blank" class="lien">Reçu</a>
                                    @can('paiements.annuler')
                                        @unless ($p->estAnnule())
                                            <details class="relative ml-3 inline-block text-left">
                                                <summary class="cursor-pointer list-none text-sm font-medium text-red-600 hover:underline">Annuler</summary>
                                                <form method="POST" action="{{ route('paiements.annuler', $p) }}" class="absolute right-0 z-10 mt-2 w-72 space-y-3 rounded-xl border bg-white p-4 shadow-lg"
                                                      data-confirm="Annuler le reçu {{ $p->numero_recu }} ? Cette action est définitive et journalisée.">
                                                    @csrf
                                                    <x-champ nom="motif_annulation" label="Motif (obligatoire)" requis maxlength="255" />
                                                    <p class="text-xs text-slate-500">Le reçu reste consultable avec la mention « ANNULÉ ». Pour corriger, enregistrez ensuite un nouveau paiement.</p>
                                                    <x-bouton variante="danger" petit>Annuler le paiement</x-bouton>
                                                </form>
                                            </details>
                                        @endunless
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </x-tableau>
                @endif
            </x-carte>

            @if ($remises->isNotEmpty())
                <x-carte titre="Remises" sans-marge>
                    <x-tableau>
                        <x-slot:entete><th>Frais</th><th class="text-right">Montant</th><th>Motif</th><th>Accordée par</th><th></th></x-slot:entete>
                        @foreach ($remises as $r)
                            <tr @class(['opacity-70' => $r->annulee_at])>
                                <td>{{ $r->fraisEleve->libelle }}</td>
                                <td @class(['text-right', 'line-through' => $r->annulee_at])>{{ montant($r->montant, false) }}</td>
                                <td>{{ $r->motif }}@if ($r->annulee_at)<span class="block text-xs text-red-700">Annulée par {{ $r->annulateur?->name }} : {{ $r->motif_annulation }}</span>@endif</td>
                                <td>{{ $r->auteur?->name }}<span class="block text-xs text-slate-500">{{ $r->created_at->format('d/m/Y') }}</span></td>
                                <td class="text-right">
                                    @can('remises.accorder')
                                        @unless ($r->annulee_at)
                                            <details class="relative inline-block text-left">
                                                <summary class="cursor-pointer list-none text-sm font-medium text-red-600 hover:underline">Annuler</summary>
                                                <form method="POST" action="{{ route('remises.annuler', $r) }}" class="absolute right-0 z-10 mt-2 w-72 space-y-3 rounded-xl border bg-white p-4 shadow-lg">
                                                    @csrf
                                                    <x-champ nom="motif_annulation" label="Motif (obligatoire)" requis maxlength="255" />
                                                    <x-bouton variante="danger" petit>Annuler la remise</x-bouton>
                                                </form>
                                            </details>
                                        @endunless
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </x-tableau>
                </x-carte>
            @endif
        </div>

        {{-- Encaissement --}}
        <div>
            @can('paiements.enregistrer')
                <x-carte titre="Encaisser un paiement">
                    @if ($s['solde'] <= 0)
                        <x-vide message="Aucun reste à payer." icone="coche" />
                    @else
                        <form method="POST" action="{{ route('paiements.store', $inscription) }}" class="space-y-4"
                              x-data="{ mode: @js(old('mode', 'especes')), repartition: @js(old('repartition', 'auto')) }"
                              data-confirm="Confirmer l'encaissement ? Un reçu numéroté sera émis ; il ne pourra plus être modifié, seulement annulé.">
                            @csrf
                            <x-champ nom="montant" :label="'Montant ('.etablissement()->devise.')'" type="number" min="1" :max="$s['solde']" :valeur="$s['retard'] ?: null" requis
                                :aide="'Reste à payer : '.montant($s['solde']).($s['retard'] ? ' · en retard : '.montant($s['retard']) : '')" />
                            <x-champ nom="date_paiement" label="Date" type="date" :valeur="now()->toDateString()" :max="now()->toDateString()" requis />
                            <div>
                                <label for="mode" class="mb-1 block text-sm font-medium text-slate-700">Mode de paiement <span class="text-red-500">*</span></label>
                                <select id="mode" name="mode" x-model="mode" class="champ">
                                    @foreach (\App\Enums\ModePaiement::options() as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach
                                </select>
                            </div>
                            <div x-show="mode !== 'especes'" x-cloak>
                                <x-champ nom="reference_externe" label="Référence de l'opération" placeholder="N° de transaction, de chèque…" maxlength="100" />
                            </div>
                            <x-champ nom="payeur_nom" label="Payé par" :valeur="$payeur?->nom_complet" maxlength="150" />

                            <fieldset class="space-y-2 text-sm">
                                <legend class="mb-1 font-medium text-slate-700">Imputation</legend>
                                <label class="flex items-center gap-2"><input type="radio" name="repartition" value="auto" x-model="repartition" class="text-marque-600"> Échéances les plus anciennes d'abord</label>
                                <label class="flex items-center gap-2"><input type="radio" name="repartition" value="manuelle" x-model="repartition" class="text-marque-600"> Choisir les frais réglés</label>
                                <div x-show="repartition === 'manuelle'" x-cloak class="space-y-2 rounded-lg bg-slate-50 p-3">
                                    @foreach ($s['lignes']->where('reste', '>', 0) as $l)
                                        <label class="flex items-center justify-between gap-2">
                                            <span class="text-xs">{{ $l['frais']->libelle }} <span class="text-slate-500">(reste {{ montant($l['reste'], false) }})</span></span>
                                            <input type="number" name="imputations[{{ $l['frais']->id }}]" min="0" max="{{ $l['reste'] }}" value="{{ old('imputations.'.$l['frais']->id) }}" class="champ w-28 py-1">
                                        </label>
                                    @endforeach
                                    @error('imputations')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                                </div>
                            </fieldset>
                            <x-bouton variante="succes" icone="billet" class="w-full">Encaisser et émettre le reçu</x-bouton>
                        </form>
                    @endif
                </x-carte>
            @endcan
        </div>
    </div>
</x-layouts.app>
