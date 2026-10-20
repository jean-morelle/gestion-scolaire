<x-layouts.app titre="Mes classes">
    <x-entete titre="Mes classes" :sous-titre="(annee_active()?->libelle ?? 'Aucune année active').' · '.note_fr($volume, 1).' h de cours par semaine'">
        <x-bouton variante="secondaire" icone="calendrier" :href="route('emplois-du-temps.enseignant', $enseignant)">Mon emploi du temps</x-bouton>
    </x-entete>

    @if ($classes->isEmpty())
        <x-carte>
            <x-vide message="Aucune classe ne vous est attribuée pour l'année en cours. Rapprochez-vous de la direction." icone="classe" />
        </x-carte>
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($classes as $ligne)
                <x-carte :titre="$ligne['classe']->nom">
                    <x-slot:actions>
                        @if ($ligne['principal'])<x-badge couleur="violet">Professeur principal</x-badge>@endif
                    </x-slot:actions>
                    <p class="text-sm text-slate-500">{{ $ligne['classe']->niveau->libelle }} · {{ $effectifs[$ligne['classe']->id] ?? 0 }} élève(s)</p>
                    <ul class="mt-3 space-y-2 text-sm">
                        @forelse ($ligne['affectations'] as $affectation)
                            <li class="flex items-center justify-between gap-2">
                                <span><span class="mr-2 inline-block size-2.5 rounded-full" style="background-color: {{ $affectation->matiere->couleur ?? '#94a3b8' }}"></span>{{ $affectation->matiere->libelle }}</span>
                                <span class="text-xs text-slate-500">coef. {{ note_fr($affectation->coefficient) }} · {{ note_fr(\App\Services\EmploiDuTempsService::volumeHoraire($affectation->creneaux), 1) }} h</span>
                            </li>
                        @empty
                            <li class="text-slate-500">Aucune matière enseignée dans cette classe.</li>
                        @endforelse
                    </ul>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <x-bouton petit variante="secondaire" icone="utilisateurs" :href="route('classes.show', $ligne['classe'])">Liste des élèves</x-bouton>
                        <x-bouton petit variante="secondaire" icone="calendrier" :href="route('emplois-du-temps.classe', $ligne['classe'])">Emploi du temps</x-bouton>
                    </div>
                </x-carte>
            @endforeach
        </div>
    @endif
</x-layouts.app>
