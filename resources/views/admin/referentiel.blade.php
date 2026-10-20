<x-layouts.app titre="Niveaux et matières">
    <x-entete titre="Niveaux et matières" sous-titre="Référentiel commun à toutes les années scolaires." />

    <div class="grid gap-6 xl:grid-cols-2">
        <x-carte titre="Niveaux" sans-marge>
            <x-tableau>
                <x-slot:entete><th>Ordre</th><th>Niveau</th><th>Cycle</th><th>Classes</th><th></th></x-slot:entete>
                @foreach ($niveaux as $niveau)
                    <tr>
                        <td>{{ $niveau->ordre }}</td>
                        <td class="font-medium">{{ $niveau->libelle }}</td>
                        <td><x-badge :couleur="['primaire' => 'violet', 'college' => 'bleu', 'lycee' => 'vert'][$niveau->cycle->value]">{{ $niveau->cycle->libelle() }}</x-badge></td>
                        <td>{{ $niveau->classes_count }}</td>
                        <td class="text-right whitespace-nowrap">
                            <details class="inline-block text-left">
                                <summary class="lien cursor-pointer list-none">Modifier</summary>
                                <form method="POST" action="{{ route('niveaux.update', $niveau) }}" class="mt-2 w-72 space-y-3 rounded-xl border bg-white p-4 shadow-lg">
                                    @csrf @method('PUT')
                                    <x-champ nom="libelle" label="Libellé" :valeur="$niveau->libelle" requis />
                                    <x-selection nom="cycle" label="Cycle" :options="\App\Enums\Cycle::options()" :valeur="$niveau->cycle" requis />
                                    <x-champ nom="ordre" label="Ordre" type="number" :valeur="$niveau->ordre" requis />
                                    <x-bouton petit>Enregistrer</x-bouton>
                                </form>
                            </details>
                            @if ($niveau->classes_count === 0)
                                <form method="POST" action="{{ route('niveaux.destroy', $niveau) }}" class="ml-3 inline" data-confirm="Supprimer le niveau {{ $niveau->libelle }} ?">
                                    @csrf @method('DELETE') <button class="text-sm font-medium text-red-600 hover:underline">Supprimer</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-tableau>
            <form method="POST" action="{{ route('niveaux.store') }}" class="grid gap-3 border-t border-slate-100 p-5 sm:grid-cols-4 sm:items-end">
                @csrf
                <x-champ nom="libelle" label="Nouveau niveau" requis placeholder="Ex. 1ère D" />
                <x-selection nom="cycle" label="Cycle" :options="\App\Enums\Cycle::options()" requis />
                <x-champ nom="ordre" label="Ordre" type="number" :valeur="$niveaux->max('ordre') + 1" requis />
                <x-bouton icone="plus">Ajouter</x-bouton>
            </form>
            @if ($errors->niveau->any())
                <p class="px-5 pb-4 text-sm text-red-600">{{ $errors->niveau->first() }}</p>
            @endif
        </x-carte>

        <x-carte titre="Matières" sans-marge>
            <x-tableau>
                <x-slot:entete><th>Code</th><th>Matière</th><th>Affectations</th><th></th></x-slot:entete>
                @foreach ($matieres as $matiere)
                    <tr>
                        <td><span class="inline-flex items-center gap-2"><span class="size-2.5 rounded-full" style="background: {{ $matiere->couleur ?? '#94a3b8' }}"></span>{{ $matiere->code }}</span></td>
                        <td class="font-medium">{{ $matiere->libelle }}</td>
                        <td>{{ $matiere->affectations_count }}</td>
                        <td class="text-right whitespace-nowrap">
                            <details class="inline-block text-left">
                                <summary class="lien cursor-pointer list-none">Modifier</summary>
                                <form method="POST" action="{{ route('matieres.update', $matiere) }}" class="mt-2 w-72 space-y-3 rounded-xl border bg-white p-4 shadow-lg">
                                    @csrf @method('PUT')
                                    <x-champ nom="code" label="Code" :valeur="$matiere->code" requis />
                                    <x-champ nom="libelle" label="Libellé" :valeur="$matiere->libelle" requis />
                                    <x-champ nom="couleur" label="Couleur" type="color" :valeur="$matiere->couleur ?? '#3b6cf6'" />
                                    <x-bouton petit>Enregistrer</x-bouton>
                                </form>
                            </details>
                            @if ($matiere->affectations_count === 0)
                                <form method="POST" action="{{ route('matieres.destroy', $matiere) }}" class="ml-3 inline" data-confirm="Supprimer la matière {{ $matiere->libelle }} ?">
                                    @csrf @method('DELETE') <button class="text-sm font-medium text-red-600 hover:underline">Supprimer</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </x-tableau>
            <form method="POST" action="{{ route('matieres.store') }}" class="grid gap-3 border-t border-slate-100 p-5 sm:grid-cols-4 sm:items-end">
                @csrf
                <x-champ nom="code" label="Code" requis placeholder="MATH" />
                <x-champ nom="libelle" label="Libellé" requis class="sm:col-span-2" />
                <x-bouton icone="plus">Ajouter</x-bouton>
            </form>
            @if ($errors->matiere->any())
                <p class="px-5 pb-4 text-sm text-red-600">{{ $errors->matiere->first() }}</p>
            @endif
        </x-carte>
    </div>
</x-layouts.app>
