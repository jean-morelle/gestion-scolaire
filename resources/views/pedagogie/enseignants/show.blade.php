<x-layouts.app :titre="$enseignant->user->name">
    <x-entete :titre="$enseignant->user->name" :sous-titre="collect([$enseignant->matricule, $enseignant->specialite])->filter()->join(' · ') ?: 'Enseignant'" :retour="route('enseignants.index')">
        <x-bouton variante="secondaire" icone="calendrier" :href="route('emplois-du-temps.enseignant', $enseignant)">Emploi du temps</x-bouton>
        <x-bouton variante="secondaire" icone="crayon" :href="route('enseignants.edit', $enseignant)">Modifier</x-bouton>
    </x-entete>

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <x-stat libelle="Classes" :valeur="$affectations->pluck('classe_id')->unique()->count()" icone="classe" />
        <x-stat libelle="Matières enseignées" :valeur="$affectations->count()" detail="couples classe × matière" icone="lien" couleur="violet" />
        <x-stat libelle="Volume horaire" :valeur="note_fr($volume, 1).' h'" detail="par semaine (emploi du temps)" icone="calendrier" couleur="vert" />
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <x-carte titre="Profil">
            <dl class="space-y-3 text-sm">
                <div><dt class="text-slate-500">Statut</dt><dd>@if ($enseignant->est_actif)<x-badge couleur="vert">En activité</x-badge>@else<x-badge>Inactif</x-badge>@endif
                    @unless ($enseignant->user->est_actif)<x-badge couleur="rouge">Compte désactivé</x-badge>@endunless</dd></div>
                <div><dt class="text-slate-500">Téléphone</dt><dd class="font-medium">{{ $enseignant->user->telephone ?? '—' }}</dd></div>
                <div><dt class="text-slate-500">E-mail</dt><dd class="font-medium">{{ $enseignant->user->email ?? '—' }}</dd></div>
                <div><dt class="text-slate-500">Diplôme</dt><dd class="font-medium">{{ $enseignant->diplome ?? '—' }}</dd></div>
                <div><dt class="text-slate-500">Date d'embauche</dt><dd class="font-medium">{{ date_fr($enseignant->date_embauche) }}</dd></div>
                <div><dt class="text-slate-500">Matières enseignées</dt>
                    <dd class="mt-1 flex flex-wrap gap-1">@forelse ($enseignant->matieres as $m)<x-badge couleur="bleu">{{ $m->libelle }}</x-badge>@empty — @endforelse</dd></div>
                <div><dt class="text-slate-500">Professeur principal</dt><dd class="font-medium">{{ $enseignant->classesPrincipales->pluck('nom')->join(', ') ?: '—' }}</dd></div>
            </dl>
        </x-carte>

        <x-carte titre="Affectations de l'année" sans-marge class="lg:col-span-2">
            @if ($affectations->isEmpty())
                <x-vide message="Aucune affectation pour l'année active." icone="lien">
                    @can('affectations.gerer')<x-bouton petit variante="secondaire" :href="route('affectations.index')">Gérer les affectations</x-bouton>@endcan
                </x-vide>
            @else
                <x-tableau>
                    <x-slot:entete><th>Classe</th><th>Matière</th><th class="text-right">Coef.</th><th class="text-right">Heures / sem.</th><th class="text-right">Évaluations</th></x-slot:entete>
                    @foreach ($affectations as $affectation)
                        <tr>
                            <td class="font-medium"><a href="{{ route('classes.show', $affectation->classe) }}" class="lien">{{ $affectation->classe->nom }}</a></td>
                            <td>{{ $affectation->matiere->libelle }}</td>
                            <td class="text-right">{{ note_fr($affectation->coefficient) }}</td>
                            <td class="text-right">{{ note_fr(\App\Services\EmploiDuTempsService::volumeHoraire($affectation->creneaux), 1) }}</td>
                            <td class="text-right">{{ $affectation->evaluations_count }}</td>
                        </tr>
                    @endforeach
                </x-tableau>
            @endif
        </x-carte>
    </div>

    <x-carte titre="Emploi du temps de la semaine" class="mt-6">
        @include('pedagogie.emplois-du-temps._semaine', ['jours' => $jours, 'vue' => 'enseignant', 'modifiable' => false])
    </x-carte>
</x-layouts.app>
