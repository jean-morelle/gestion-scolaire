<x-layouts.app titre="Inscriptions">
    <x-entete titre="Inscriptions" :sous-titre="$annee ? 'Année scolaire '.$annee->libelle : 'Aucune année scolaire'">
        @if ($annee)
            <x-bouton variante="secondaire" icone="telecharger" :href="route('inscriptions.index', array_merge(request()->query(), ['annee' => $annee->id, 'format' => 'csv']))">Exporter (CSV)</x-bouton>
        @endif
        <x-bouton variante="secondaire" icone="inscription" :href="route('inscriptions.reinscriptions')">Réinscription en lot</x-bouton>
        @can('eleves.gerer')
            <x-bouton icone="plus" :href="route('eleves.create')">Nouvel élève</x-bouton>
        @endcan
    </x-entete>

    @if ($annee)
        <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-stat libelle="Élèves inscrits" :valeur="$stats['inscrits']" icone="utilisateurs" />
            <x-stat libelle="Nouvelles inscriptions" :valeur="$stats['nouvelles']" icone="inscription" couleur="vert" />
            <x-stat libelle="Réinscriptions" :valeur="$stats['reinscriptions']" icone="inscription" couleur="violet" />
            <x-stat libelle="Départs" :valeur="$stats['partis']" icone="deconnexion" couleur="rouge" />
        </div>
    @endif

    <x-carte sans-marge>
        <form method="GET" class="grid gap-3 border-b border-slate-100 p-4 sm:grid-cols-2 lg:grid-cols-6">
            <select name="annee" class="champ" aria-label="Année scolaire">
                @foreach ($annees as $a)
                    <option value="{{ $a->id }}" @selected($annee?->id === $a->id)>{{ $a->libelle }}</option>
                @endforeach
            </select>
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Nom ou matricule" class="champ lg:col-span-2" aria-label="Rechercher">
            <select name="classe" class="champ" aria-label="Classe">
                <option value="">Toutes les classes</option>
                @foreach ($classes as $id => $nom)
                    <option value="{{ $id }}" @selected(request('classe') == $id)>{{ $nom }}</option>
                @endforeach
            </select>
            <select name="type" class="champ" aria-label="Type">
                <option value="">Tous les types</option>
                @foreach (\App\Enums\TypeInscription::options() as $valeur => $libelle)
                    <option value="{{ $valeur }}" @selected(request('type') === $valeur)>{{ $libelle }}</option>
                @endforeach
            </select>
            <div class="flex gap-2">
                <select name="statut" class="champ" aria-label="Statut">
                    <option value="">Tous</option>
                    @foreach (\App\Enums\StatutInscription::options() as $valeur => $libelle)
                        <option value="{{ $valeur }}" @selected(request('statut') === $valeur)>{{ $libelle }}</option>
                    @endforeach
                </select>
                <x-bouton variante="secondaire" icone="recherche" aria-label="Filtrer"></x-bouton>
            </div>
        </form>

        @if ($inscriptions->isEmpty())
            <x-vide message="Aucune inscription ne correspond à la recherche." icone="inscription" />
        @else
            <x-tableau>
                <x-slot:entete><th>Date</th><th>Matricule</th><th>Élève</th><th>Classe</th><th>Type</th><th>Statut</th><th></th></x-slot:entete>
                @foreach ($inscriptions as $inscription)
                    <tr>
                        <td>{{ date_fr($inscription->date_inscription) }}</td>
                        <td class="font-mono text-xs">{{ $inscription->eleve->matricule }}</td>
                        <td class="font-medium"><span class="inline-flex items-center gap-2 align-middle"><x-avatar :eleve="$inscription->eleve" taille="size-8" />{{ $inscription->eleve->nom_complet }}</span>
                            @if ($inscription->redoublant)<x-badge couleur="orange" class="ml-1">Redoublant</x-badge>@endif</td>
                        <td>{{ $inscription->classe->nom }}</td>
                        <td><x-badge :couleur="$inscription->type === \App\Enums\TypeInscription::Nouvelle ? 'vert' : 'violet'">{{ $inscription->type->libelle() }}</x-badge></td>
                        <td><x-badge :couleur="$inscription->estActive() ? 'vert' : 'rouge'">{{ $inscription->statut->libelle() }}</x-badge></td>
                        <td class="text-right">@can('eleves.consulter')<a href="{{ route('eleves.show', $inscription->eleve) }}" class="lien">Dossier</a>@endcan</td>
                    </tr>
                @endforeach
            </x-tableau>
            <div class="p-4">{{ $inscriptions->links() }}</div>
        @endif
    </x-carte>
</x-layouts.app>
