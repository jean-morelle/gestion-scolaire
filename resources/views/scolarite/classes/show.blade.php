<x-layouts.app :titre="'Classe '.$classe->nom">
    <x-entete :titre="'Classe '.$classe->nom"
              :sous-titre="$classe->niveau->libelle.($classe->serie ? ' · Série '.$classe->serie : '').' · '.$classe->anneeScolaire->libelle.' · Professeur principal : '.($classe->professeurPrincipal?->user->name ?? 'non désigné')"
              :retour="auth()->user()->can('viewAny', \App\Models\Classe::class) ? route('classes.index', ['annee' => $classe->annee_scolaire_id]) : route('mes-classes.index')">
        <x-bouton variante="secondaire" icone="imprimer" type="button" onclick="window.print()">Imprimer</x-bouton>
        <x-bouton variante="secondaire" icone="telecharger" :href="route('classes.show', [$classe, 'format' => 'csv'])">Exporter (CSV)</x-bouton>
        @can('voirEmploiDuTemps', $classe)
            <x-bouton variante="secondaire" icone="calendrier" :href="route('emplois-du-temps.classe', $classe)">Emploi du temps</x-bouton>
        @endcan
        @can('affectations.gerer')
            <x-bouton variante="secondaire" icone="lien" :href="route('affectations.classe', $classe)">Matières et enseignants</x-bouton>
        @endcan
        @can('classes.gerer')
            @unless ($classe->anneeScolaire->estCloturee())
                <x-bouton variante="secondaire" :href="route('classes.edit', $classe)">Modifier</x-bouton>
            @endunless
        @endcan
    </x-entete>

    @php $filles = $inscrits->filter(fn ($i) => $i->eleve->sexe === \App\Enums\Sexe::Feminin)->count(); @endphp
    <div class="no-print mb-6 grid gap-4 sm:grid-cols-4">
        <x-stat libelle="Effectif" :valeur="$inscrits->count()" :detail="$classe->capacite ? 'Capacité : '.$classe->capacite : null" icone="utilisateurs" :couleur="$classe->capacite && $inscrits->count() > $classe->capacite ? 'rouge' : 'marque'" />
        <x-stat libelle="Filles" :valeur="$filles" icone="utilisateurs" couleur="violet" />
        <x-stat libelle="Garçons" :valeur="$inscrits->count() - $filles" icone="utilisateurs" couleur="vert" />
        <x-stat libelle="Redoublants" :valeur="$inscrits->where('redoublant', true)->count()" icone="inscription" couleur="orange" />
    </div>

    <x-carte titre="Liste des élèves" sans-marge>
        @if ($inscrits->isEmpty())
            <x-vide message="Aucun élève inscrit dans cette classe." icone="utilisateurs" />
        @else
            <x-tableau>
                <x-slot:entete><th>N°</th><th>Matricule</th><th>Nom et prénoms</th><th>Sexe</th><th>Né(e) le</th>@if ($contacts)<th>Responsable</th><th>Téléphone</th>@endif<th class="no-print"></th></x-slot:entete>
                @foreach ($inscrits as $n => $inscription)
                    @php $responsable = $contacts ? $inscription->eleve->responsables->sortByDesc('pivot.est_contact_principal')->first() : null; @endphp
                    <tr>
                        <td>{{ $n + 1 }}</td>
                        <td class="font-mono text-xs">{{ $inscription->eleve->matricule }}</td>
                        <td class="font-medium"><span class="inline-flex items-center gap-2 align-middle"><x-avatar :eleve="$inscription->eleve" taille="size-8" class="print:hidden" />{{ $inscription->eleve->nom_complet }}</span>
                            @if ($inscription->redoublant)<x-badge couleur="orange" class="ml-1">Redoublant</x-badge>@endif</td>
                        <td>{{ $inscription->eleve->sexe->value }}</td>
                        <td>{{ date_fr($inscription->eleve->date_naissance) }}</td>
                        @if ($contacts)
                            <td>{{ $responsable?->nom_complet ?? '—' }}</td>
                            <td>{{ $responsable?->telephone ?? '—' }}</td>
                        @endif
                        <td class="no-print text-right">@can('eleves.consulter')<a href="{{ route('eleves.show', $inscription->eleve) }}" class="lien">Dossier</a>@endcan</td>
                    </tr>
                @endforeach
            </x-tableau>
        @endif
    </x-carte>

    <x-carte titre="Matières et enseignants" sans-marge class="mt-6">
        @if ($affectations->isEmpty())
            <x-vide message="Aucune matière n'est encore affectée à cette classe." icone="lien" />
        @else
            <x-tableau>
                <x-slot:entete><th>Matière</th><th>Enseignant</th><th class="text-right">Coefficient</th></x-slot:entete>
                @foreach ($affectations as $affectation)
                    <tr>
                        <td class="font-medium"><span class="mr-2 inline-block size-2.5 rounded-full" style="background-color: {{ $affectation->matiere->couleur ?? '#94a3b8' }}"></span>{{ $affectation->matiere->libelle }}</td>
                        <td>{!! $affectation->enseignant ? e($affectation->enseignant->user->name) : '<span class="text-amber-700">Non pourvu</span>' !!}</td>
                        <td class="text-right">{{ note_fr($affectation->coefficient, 2) }}</td>
                    </tr>
                @endforeach
            </x-tableau>
        @endif
    </x-carte>

    @if ($partis->isNotEmpty())
        <x-carte titre="Élèves partis en cours d'année" sans-marge class="no-print mt-6">
            <x-tableau>
                <x-slot:entete><th>Matricule</th><th>Nom et prénoms</th><th>Date de départ</th><th>Motif</th><th></th></x-slot:entete>
                @foreach ($partis as $inscription)
                    <tr>
                        <td class="font-mono text-xs">{{ $inscription->eleve->matricule }}</td>
                        <td>{{ $inscription->eleve->nom_complet }}</td>
                        <td>{{ date_fr($inscription->date_sortie) }}</td>
                        <td>{{ $inscription->motif_sortie }}</td>
                        <td class="text-right">@can('eleves.consulter')<a href="{{ route('eleves.show', $inscription->eleve) }}" class="lien">Dossier</a>@endcan</td>
                    </tr>
                @endforeach
            </x-tableau>
        </x-carte>
    @endif
</x-layouts.app>
