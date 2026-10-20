<x-layouts.app :titre="$titre">
    <x-entete :titre="$titre" :sous-titre="$sousTitre" :retour="$retour">
        <x-bouton variante="secondaire" icone="imprimer" type="button" onclick="window.print()">Imprimer</x-bouton>
        @if ($classe)
            @can('affectations.gerer')
                <x-bouton variante="secondaire" icone="lien" :href="route('affectations.classe', $classe)">Matières et enseignants</x-bouton>
            @endcan
        @endif
    </x-entete>

    @if ($modifiable)
        <x-carte titre="Ajouter un créneau" class="no-print mb-6">
            @if ($affectations->isEmpty())
                <p class="text-sm text-slate-500">Aucune matière n'est affectée à cette classe.
                    @can('affectations.gerer')<a href="{{ route('affectations.classe', $classe) }}" class="lien">Définir les matières et enseignants</a>.@endcan</p>
            @else
                <form method="POST" action="{{ route('emplois-du-temps.store', $classe) }}" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6 lg:items-end">
                    @csrf
                    <x-selection nom="affectation_id" label="Matière" vide="— Choisir —" requis class="lg:col-span-2"
                        :options="$affectations->mapWithKeys(fn ($a) => [$a->id => $a->matiere->libelle.' — '.($a->enseignant?->user->name ?? 'non pourvu')])" />
                    <x-selection nom="jour" label="Jour" :options="\App\Enums\Jour::options()" requis />
                    <x-champ nom="heure_debut" label="Début" type="time" step="300" requis />
                    <x-champ nom="heure_fin" label="Fin" type="time" step="300" requis />
                    <x-champ nom="salle" label="Salle" maxlength="50" placeholder="Ex. B12" />
                    <div class="lg:col-span-6"><x-bouton icone="plus">Ajouter</x-bouton>
                        <span class="ml-2 text-xs text-slate-500">Les chevauchements (classe, enseignant, salle) sont refusés.</span></div>
                </form>
            @endif
        </x-carte>
    @endif

    <x-carte>
        @include('pedagogie.emplois-du-temps._semaine', ['jours' => $jours, 'vue' => $vue, 'modifiable' => $modifiable])
    </x-carte>
</x-layouts.app>
