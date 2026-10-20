<x-layouts.app titre="Cours et devoirs">
    <x-entete titre="Cours et devoirs" :sous-titre="$tout ? 'Suivi de l\'activité pédagogique de l\'année active' : 'Vos cours, ressources et devoirs'" />

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <x-stat libelle="Cours publiés" :valeur="$affectations->sum('cours_publies').' / '.$affectations->sum('cours_count')" icone="livre" />
        <x-stat libelle="Devoirs" :valeur="$affectations->sum('devoirs_count')" icone="document" couleur="violet" />
        <x-stat libelle="Copies à corriger" :valeur="$copiesACorriger" icone="crayon" :couleur="$copiesACorriger ? 'orange' : 'vert'" />
    </div>

    @if ($tout && $enseignants->isNotEmpty())
        <form method="GET" class="mb-4">
            <x-selection nom="enseignant" label="Enseignant" :options="$enseignants" :valeur="request('enseignant')" vide="Tous les enseignants" class="w-64" onchange="this.form.submit()" />
        </form>
    @endif

    <x-carte titre="Matières" sans-marge>
        @if ($affectations->isEmpty())
            <x-vide message="Aucune matière affectée pour l'année active." icone="lien" />
        @else
            <x-tableau>
                <x-slot:entete><th>Classe</th><th>Matière</th>@if ($tout)<th>Enseignant</th>@endif<th class="text-right">Cours (publiés)</th><th class="text-right">Devoirs</th><th></th></x-slot:entete>
                @foreach ($affectations as $a)
                    <tr>
                        <td class="font-medium">{{ $a->classe->nom }}</td>
                        <td>{{ $a->matiere->libelle }}</td>
                        @if ($tout)<td>{{ $a->enseignant?->user->name ?? '—' }}</td>@endif
                        <td class="text-right">{{ $a->cours_count }} ({{ $a->cours_publies }})</td>
                        <td class="text-right">{{ $a->devoirs_count }}</td>
                        <td class="whitespace-nowrap text-right">
                            @can('create', [\App\Models\Cours::class, $a])
                                <a href="{{ route('cours.create', ['affectation' => $a->id]) }}" class="lien">+ Cours</a>
                                <a href="{{ route('devoirs.create', ['affectation' => $a->id]) }}" class="lien ml-3">+ Devoir</a>
                            @endcan
                        </td>
                    </tr>
                @endforeach
            </x-tableau>
        @endif
    </x-carte>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <x-carte titre="Cours récents" sans-marge>
            @forelse ($cours as $c)
                <a href="{{ route('cours.show', $c) }}" class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-3 text-sm last:border-0 hover:bg-slate-50">
                    <span><span class="font-medium">{{ $c->titre }}</span>
                        <span class="block text-xs text-slate-500">{{ $c->affectation->classe->nom }} — {{ $c->affectation->matiere->libelle }} · {{ $c->ressources_count }} ressource(s)</span></span>
                    <x-badge :couleur="$c->est_publie ? 'vert' : 'gris'">{{ $c->est_publie ? 'Publié' : 'Brouillon' }}</x-badge>
                </a>
            @empty
                <x-vide message="Aucun cours." icone="livre" />
            @endforelse
        </x-carte>
        <x-carte titre="Devoirs récents" sans-marge>
            @forelse ($devoirs as $d)
                <a href="{{ route('devoirs.show', $d) }}" class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-3 text-sm last:border-0 hover:bg-slate-50">
                    <span><span class="font-medium">{{ $d->titre }}</span>
                        <span class="block text-xs text-slate-500">{{ $d->affectation->classe->nom }} — {{ $d->affectation->matiere->libelle }} · pour le {{ $d->date_limite->format('d/m/Y H:i') }}</span></span>
                    <span class="text-right text-xs">
                        @unless ($d->est_publie)<x-badge>Non publié</x-badge>@else{{ $d->remises_count }} remis · {{ $d->corrigees }} corrigé(s)@endunless
                    </span>
                </a>
            @empty
                <x-vide message="Aucun devoir." icone="document" />
            @endforelse
        </x-carte>
    </div>
</x-layouts.app>
