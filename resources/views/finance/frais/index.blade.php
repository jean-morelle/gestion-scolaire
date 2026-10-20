<x-layouts.app titre="Frais et échéances">
    <x-entete titre="Frais et échéances" :sous-titre="$annee ? 'Grilles de frais par niveau · '.$annee->libelle.' · montants en '.etablissement()->devise : 'Aucune année scolaire'">
        @if ($annee && ! $annee->estCloturee())
            <x-bouton icone="plus" :href="route('grilles-frais.create', ['annee' => $annee->id])">Nouvelle grille</x-bouton>
        @endif
    </x-entete>

    @if ($annees->isNotEmpty())
        <form method="GET" class="mb-4">
            <x-selection nom="annee" label="Année scolaire" :options="$annees->pluck('libelle', 'id')" :valeur="$annee?->id" class="w-48" onchange="this.form.submit()" />
        </form>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            @forelse ($niveaux as $niveau)
                @php $lignes = $grilles->get($niveau->id, collect()); @endphp
                @continue($lignes->isEmpty())
                <x-carte :titre="$niveau->libelle.' — total obligatoire : '.montant($lignes->filter(fn ($g) => $g->typeFrais->est_obligatoire && $g->categorie === '')->sum('montant'))" sans-marge>
                    <x-tableau>
                        <x-slot:entete><th>Frais</th><th>Catégorie</th><th class="text-right">Montant</th><th>Échéancier</th><th></th></x-slot:entete>
                        @foreach ($lignes as $grille)
                            <tr>
                                <td class="font-medium">{{ $grille->typeFrais->libelle }}
                                    @unless ($grille->typeFrais->est_obligatoire)<x-badge class="ml-1">Facultatif</x-badge>@endunless</td>
                                <td>{{ $grille->categorie ?: 'Toutes' }}</td>
                                <td class="text-right font-medium">{{ montant($grille->montant) }}</td>
                                <td class="text-xs text-slate-600">
                                    @forelse ($grille->tranches as $t)
                                        <span class="block">{{ $t->libelle }} : {{ montant($t->montant, false) }} avant le {{ date_fr($t->date_limite) }}</span>
                                    @empty
                                        Payable à l'inscription
                                    @endforelse
                                </td>
                                <td class="text-right">@unless ($annee->estCloturee())<a href="{{ route('grilles-frais.edit', $grille) }}" class="lien">Modifier</a>@endunless</td>
                            </tr>
                        @endforeach
                    </x-tableau>
                </x-carte>
            @empty
            @endforelse
            @if ($grilles->isEmpty())
                <x-carte><x-vide message="Aucune grille de frais pour cette année." icone="reglages">
                    @if ($annee && ! $annee->estCloturee())<x-bouton petit icone="plus" :href="route('grilles-frais.create', ['annee' => $annee->id])">Créer une grille</x-bouton>@endif
                </x-vide></x-carte>
            @endif
        </div>

        <div class="space-y-6">
            @if ($annee && ! $annee->estCloturee())
                <x-carte titre="Appliquer aux élèves inscrits">
                    <p class="mb-3 text-sm text-slate-600">Les frais sont attribués automatiquement à l'inscription. Si des grilles ont été créées après, appliquez-les aux élèves déjà inscrits (seuls les frais obligatoires manquants sont ajoutés).</p>
                    <form method="POST" action="{{ route('grilles-frais.generer', $annee) }}" data-confirm="Ajouter les frais obligatoires manquants à tous les élèves inscrits en {{ $annee->libelle }} ?">
                        @csrf <x-bouton variante="secondaire">Générer les frais manquants</x-bouton>
                    </form>
                </x-carte>
                @if ($anneesSource->isNotEmpty())
                    <x-carte titre="Recopier les grilles">
                        <form method="POST" action="{{ route('grilles-frais.copier') }}" class="space-y-3">
                            @csrf
                            <input type="hidden" name="cible_id" value="{{ $annee->id }}">
                            <x-selection nom="source_id" label="Depuis l'année" :options="$anneesSource->pluck('libelle', 'id')" requis />
                            <x-bouton variante="secondaire">Recopier vers {{ $annee->libelle }}</x-bouton>
                            <p class="text-xs text-slate-500">Les dates limites sont décalées d'un an ; les grilles existantes ne sont pas modifiées.</p>
                        </form>
                    </x-carte>
                @endif
            @endif

            <x-carte titre="Types de frais" sans-marge>
                <ul class="divide-y divide-slate-100">
                    @foreach ($types as $type)
                        <li class="px-5 py-2.5 text-sm">
                            <details>
                                <summary class="flex cursor-pointer list-none items-center justify-between gap-2">
                                    <span><span class="font-medium">{{ $type->libelle }}</span> <span class="font-mono text-xs text-slate-500">{{ $type->code }}</span></span>
                                    <x-badge :couleur="$type->est_obligatoire ? 'bleu' : 'gris'">{{ $type->est_obligatoire ? 'Obligatoire' : 'Facultatif' }}</x-badge>
                                </summary>
                                <form method="POST" action="{{ route('types-frais.update', $type) }}" class="mt-3 space-y-2">
                                    @csrf @method('PUT')
                                    <input type="text" name="code" value="{{ $type->code }}" class="champ" required aria-label="Code">
                                    <input type="text" name="libelle" value="{{ $type->libelle }}" class="champ" required aria-label="Libellé">
                                    <x-case nom="est_obligatoire" label="Obligatoire (attribué à l'inscription)" :coche="$type->est_obligatoire" />
                                    <x-bouton petit variante="secondaire">Enregistrer</x-bouton>
                                </form>
                            </details>
                        </li>
                    @endforeach
                </ul>
                <form method="POST" action="{{ route('types-frais.store') }}" class="space-y-2 border-t border-slate-100 p-4">
                    @csrf
                    <p class="text-sm font-medium text-slate-700">Nouveau type</p>
                    <div class="grid grid-cols-2 gap-2">
                        <input type="text" name="code" placeholder="Code (ex. CANTINE)" class="champ" required aria-label="Code">
                        <input type="text" name="libelle" placeholder="Libellé" class="champ" required aria-label="Libellé">
                    </div>
                    <x-case nom="est_obligatoire" label="Obligatoire" aide="Facultatif : attribué élève par élève (cantine, transport…)." />
                    <x-bouton petit icone="plus">Ajouter</x-bouton>
                </form>
            </x-carte>
        </div>
    </div>
</x-layouts.app>
