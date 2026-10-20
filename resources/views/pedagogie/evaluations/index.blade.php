<x-layouts.app titre="Évaluations et notes">
    <x-entete titre="Évaluations et notes" :sous-titre="$tout ? 'Toutes les classes de l\'année active' : 'Vos classes et matières de l\'année active'" />

    @can('notes.saisir')
        @php $miennes = $affectations->where('enseignant_id', auth()->user()->enseignant?->id); @endphp
        @if ($miennes->isNotEmpty())
            <x-carte titre="Mes matières" class="mb-6" sans-marge>
                <ul class="grid divide-y divide-slate-100 sm:grid-cols-2 sm:divide-y-0 lg:grid-cols-3">
                    @foreach ($miennes as $affectation)
                        <li class="flex items-center justify-between gap-3 px-5 py-3 text-sm">
                            <span><span class="font-medium">{{ $affectation->classe->nom }}</span> — {{ $affectation->matiere->libelle }}</span>
                            <span class="flex shrink-0 gap-3">
                                <a href="{{ route('resultats.affectation', $affectation) }}" class="lien">Relevé</a>
                                <a href="{{ route('evaluations.create', ['affectation' => $affectation->id]) }}" class="lien">+ Évaluation</a>
                            </span>
                        </li>
                    @endforeach
                </ul>
            </x-carte>
        @endif
    @endcan

    <x-carte sans-marge>
        <form method="GET" class="grid gap-3 border-b border-slate-100 p-4 sm:grid-cols-2 lg:grid-cols-5">
            <select name="classe" class="champ" aria-label="Classe">
                <option value="">Toutes les classes</option>
                @foreach ($classes as $id => $nom)
                    <option value="{{ $id }}" @selected(request('classe') == $id)>{{ $nom }}</option>
                @endforeach
            </select>
            <select name="affectation" class="champ lg:col-span-2" aria-label="Matière">
                <option value="">Toutes les matières</option>
                @foreach ($affectations as $a)
                    <option value="{{ $a->id }}" @selected(request('affectation') == $a->id)>{{ $a->classe->nom }} — {{ $a->matiere->libelle }}</option>
                @endforeach
            </select>
            <select name="periode" class="champ" aria-label="Période">
                <option value="">Toutes les périodes</option>
                @foreach ($periodes as $id => $libelle)
                    <option value="{{ $id }}" @selected(request('periode') == $id)>{{ $libelle }}</option>
                @endforeach
            </select>
            <div class="flex gap-2">
                <select name="statut" class="champ" aria-label="Statut">
                    <option value="">Tous statuts</option>
                    @foreach (\App\Enums\StatutEvaluation::options() as $valeur => $libelle)
                        <option value="{{ $valeur }}" @selected(request('statut') === $valeur)>{{ $libelle }}</option>
                    @endforeach
                </select>
                <x-bouton variante="secondaire" icone="recherche" aria-label="Filtrer"></x-bouton>
            </div>
        </form>

        @if ($evaluations->isEmpty())
            <x-vide message="Aucune évaluation." icone="crayon" />
        @else
            <x-tableau>
                <x-slot:entete><th>Date</th><th>Évaluation</th><th>Classe et matière</th><th>Période</th><th class="text-right">Notes</th><th>Statut</th><th></th></x-slot:entete>
                @foreach ($evaluations as $evaluation)
                    <tr>
                        <td class="whitespace-nowrap">{{ date_fr($evaluation->date_evaluation) }}</td>
                        <td class="font-medium">{{ $evaluation->libelle }}
                            <span class="block text-xs text-slate-500">{{ $evaluation->type->libelle() }} · sur {{ note_fr($evaluation->bareme, 0) }} · poids {{ note_fr($evaluation->poids, 1) }}</span></td>
                        <td>{{ $evaluation->affectation->classe->nom }} — {{ $evaluation->affectation->matiere->libelle }}</td>
                        <td>{{ $evaluation->periode->libelle }}</td>
                        <td class="text-right">{{ $evaluation->notes_count - $evaluation->absents }}@if ($evaluation->absents) <span class="text-xs text-slate-500">+ {{ $evaluation->absents }} abs.</span>@endif</td>
                        <td>
                            @if ($evaluation->estValidee())<x-badge couleur="vert">Validée</x-badge>@else<x-badge couleur="orange">Brouillon</x-badge>@endif
                            @if ($evaluation->deverrouillee_at)<x-badge couleur="violet">Déverrouillée</x-badge>@endif
                        </td>
                        <td class="text-right"><a href="{{ route('evaluations.show', $evaluation) }}" class="lien">@can('update', $evaluation){{ $evaluation->estValidee() ? 'Voir' : 'Saisir' }}@else Voir @endcan</a></td>
                    </tr>
                @endforeach
            </x-tableau>
            <div class="p-4">{{ $evaluations->links() }}</div>
        @endif
    </x-carte>
</x-layouts.app>
