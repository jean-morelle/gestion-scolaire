<x-layouts.app titre="Élèves">
    <x-entete titre="Élèves" :sous-titre="$eleves->total().' dossier(s)'.($annee ? ' · classes de l\'année '.$annee->libelle : '')">
        <x-bouton variante="secondaire" icone="telecharger" :href="route('eleves.index', array_merge(request()->query(), ['format' => 'csv']))">Exporter (CSV)</x-bouton>
        @can('eleves.gerer')
            <x-bouton :href="route('eleves.create')" icone="plus">Nouvel élève</x-bouton>
        @endcan
    </x-entete>

    <x-carte sans-marge>
        <form method="GET" class="grid gap-3 border-b border-slate-100 p-4 sm:grid-cols-2 lg:grid-cols-6">
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Nom, prénoms ou matricule" class="champ lg:col-span-2" aria-label="Rechercher">
            <select name="classe" class="champ" aria-label="Classe">
                <option value="">Toutes les classes</option>
                @foreach ($classes as $c)
                    <option value="{{ $c->id }}" @selected(request('classe') == $c->id)>{{ $c->nom }}</option>
                @endforeach
            </select>
            <select name="sexe" class="champ" aria-label="Sexe">
                <option value="">Filles et garçons</option>
                @foreach (\App\Enums\Sexe::options() as $valeur => $libelle)
                    <option value="{{ $valeur }}" @selected(request('sexe') === $valeur)>{{ $libelle }}</option>
                @endforeach
            </select>
            <select name="inscription" class="champ" aria-label="Inscription">
                <option value="">Inscrits ou non</option>
                <option value="non_inscrit" @selected(request('inscription') === 'non_inscrit')>Non inscrits cette année</option>
            </select>
            <div class="flex gap-2">
                <select name="statut" class="champ" aria-label="Statut du dossier">
                    <option value="actif">Actifs</option>
                    <option value="archive" @selected(request('statut') === 'archive')>Archivés</option>
                </select>
                <x-bouton variante="secondaire" icone="recherche" aria-label="Filtrer"></x-bouton>
            </div>
        </form>

        @if ($eleves->isEmpty())
            <x-vide message="Aucun élève ne correspond à la recherche." icone="utilisateurs">
                @can('eleves.gerer')<x-bouton :href="route('eleves.create')" icone="plus" petit>Créer un dossier</x-bouton>@endcan
            </x-vide>
        @else
            <x-tableau>
                <x-slot:entete><th>Élève</th><th>Sexe</th><th>Né(e) le</th><th>Classe {{ $annee?->libelle }}</th><th>Responsable</th><th>Statut</th><th class="text-right">Actions</th></x-slot:entete>
                @foreach ($eleves as $eleve)
                    @php
                        $inscription = $eleve->inscriptions->first();
                        $responsable = $eleve->responsables->sortByDesc('pivot.est_contact_principal')->first();
                    @endphp
                    <tr>
                        <td>
                            <a href="{{ route('eleves.show', $eleve) }}" class="flex items-center gap-3">
                                <x-avatar :eleve="$eleve" />
                                <span class="min-w-0"><span class="block truncate font-medium text-slate-900 hover:underline">{{ $eleve->nom_complet }}</span>
                                    <span class="block font-mono text-xs text-slate-500">{{ $eleve->matricule }}</span></span>
                            </a>
                        </td>
                        <td>{{ $eleve->sexe->value }}</td>
                        <td class="whitespace-nowrap">{{ date_fr($eleve->date_naissance) }}</td>
                        <td>
                            @if ($inscription)
                                {{ $inscription->classe->nom }}<span class="block text-xs text-slate-500">{{ $inscription->classe->niveau->cycle->libelle() }}</span>
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            @if ($responsable)
                                {{ $responsable->nom_complet }}<span class="block text-xs text-slate-500">{{ $responsable->telephone }}</span>
                            @else
                                <x-badge couleur="rouge">Aucun</x-badge>
                            @endif
                        </td>
                        <td>
                            @if ($eleve->statut === \App\Enums\StatutEleve::Archive)
                                <x-badge>Archivé</x-badge>
                            @elseif (! $inscription)
                                <x-badge couleur="orange">Non inscrit</x-badge>
                            @elseif (! $inscription->estActive())
                                <x-badge couleur="rouge">Parti</x-badge>
                            @else
                                <x-badge couleur="vert">Actif</x-badge>
                            @endif
                        </td>
                        <td class="whitespace-nowrap text-right">
                            <x-action-icone :href="route('eleves.show', $eleve)" icone="oeil" :libelle="'Voir le dossier de '.$eleve->nom_complet" />
                            @can('update', $eleve)
                                <x-action-icone :href="route('eleves.edit', $eleve)" icone="crayon" :libelle="'Modifier '.$eleve->nom_complet" couleur="gris" />
                            @endcan
                            @if ($inscription && auth()->user()->canAny(['finances.consulter', 'paiements.enregistrer']))
                                <x-action-icone :href="route('finances.show', $inscription)" icone="portefeuille" :libelle="'Situation financière de '.$eleve->nom_complet" couleur="gris" />
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-tableau>
            <div class="p-4">{{ $eleves->links() }}</div>
        @endif
    </x-carte>
</x-layouts.app>
