<x-layouts.app titre="Enseignants">
    <x-entete titre="Enseignants" :sous-titre="$enseignants->total().' enseignant(s)'">
        <x-bouton variante="secondaire" icone="telecharger" :href="route('enseignants.index', array_merge(request()->query(), ['format' => 'csv']))">Exporter (CSV)</x-bouton>
        <x-bouton icone="plus" :href="route('enseignants.create')">Nouvel enseignant</x-bouton>
    </x-entete>

    <x-carte sans-marge>
        <form method="GET" class="grid gap-3 border-b border-slate-100 p-4 sm:grid-cols-4">
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Nom, téléphone, matricule, spécialité" class="champ sm:col-span-2" aria-label="Rechercher">
            <select name="matiere" class="champ" aria-label="Matière">
                <option value="">Toutes les matières</option>
                @foreach ($matieres as $id => $libelle)
                    <option value="{{ $id }}" @selected(request('matiere') == $id)>{{ $libelle }}</option>
                @endforeach
            </select>
            <div class="flex gap-2">
                <select name="statut" class="champ" aria-label="Statut">
                    <option value="actif">Actifs</option>
                    <option value="inactif" @selected(request('statut') === 'inactif')>Inactifs</option>
                    <option value="tous" @selected(request('statut') === 'tous')>Tous</option>
                </select>
                <x-bouton variante="secondaire" icone="recherche" aria-label="Filtrer"></x-bouton>
            </div>
        </form>

        @if ($enseignants->isEmpty())
            <x-vide message="Aucun enseignant ne correspond à la recherche." icone="enseignant">
                <x-bouton :href="route('enseignants.create')" icone="plus" petit>Ajouter un enseignant</x-bouton>
            </x-vide>
        @else
            <x-tableau>
                <x-slot:entete><th>Nom</th><th>Contact</th><th>Matières enseignées</th><th class="text-right">Affectations</th><th>Statut</th><th></th></x-slot:entete>
                @foreach ($enseignants as $enseignant)
                    <tr>
                        <td class="font-medium"><a href="{{ route('enseignants.show', $enseignant) }}" class="hover:underline">{{ $enseignant->user->name }}</a>
                            @if ($enseignant->matricule)<span class="block font-mono text-xs text-slate-500">{{ $enseignant->matricule }}</span>@endif</td>
                        <td>{{ $enseignant->user->telephone ?? '—' }}<span class="block text-xs text-slate-500">{{ $enseignant->user->email }}</span></td>
                        <td>{{ $enseignant->matieres->pluck('libelle')->join(', ') ?: ($enseignant->specialite ?? '—') }}</td>
                        <td class="text-right">{{ $enseignant->nb_affectations }}</td>
                        <td>@if ($enseignant->est_actif)<x-badge couleur="vert">Actif</x-badge>@else<x-badge>Inactif</x-badge>@endif</td>
                        <td class="text-right"><a href="{{ route('enseignants.show', $enseignant) }}" class="lien">Fiche</a></td>
                    </tr>
                @endforeach
            </x-tableau>
            <div class="p-4">{{ $enseignants->links() }}</div>
        @endif
    </x-carte>
</x-layouts.app>
