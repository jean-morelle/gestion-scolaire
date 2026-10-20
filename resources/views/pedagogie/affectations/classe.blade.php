<x-layouts.app :titre="'Affectations '.$classe->nom">
    @php $modifiable = ! $classe->anneeScolaire->estCloturee(); @endphp
    <x-entete :titre="'Matières et enseignants — '.$classe->nom"
              :sous-titre="$classe->niveau->libelle.' · '.$classe->anneeScolaire->libelle.' · total des coefficients : '.note_fr($totalCoefficients)"
              :retour="route('affectations.index', ['annee' => $classe->annee_scolaire_id])">
        <x-bouton variante="secondaire" icone="calendrier" :href="route('emplois-du-temps.classe', $classe)">Emploi du temps</x-bouton>
        <x-bouton variante="secondaire" icone="classe" :href="route('classes.show', $classe)">Liste de la classe</x-bouton>
    </x-entete>

    @unless ($modifiable)
        <div class="mb-5 rounded-xl border border-slate-200 bg-slate-100 px-4 py-3 text-sm text-slate-700">Année clôturée : les affectations sont en lecture seule.</div>
    @endunless

    <x-carte titre="Matières de la classe" sans-marge>
        @if ($affectations->isEmpty())
            <x-vide message="Aucune matière pour cette classe. Ajoutez-les ci-dessous ou reprenez celles d'une autre classe." icone="lien" />
        @else
            <form method="POST" action="{{ route('affectations.update', $classe) }}" id="form-affectations">
                @csrf @method('PUT')
                <x-tableau>
                    <x-slot:entete><th>Matière</th><th>Enseignant titulaire</th><th class="w-28">Coefficient</th><th class="w-24">Ordre</th><th>Suivi</th><th></th></x-slot:entete>
                    @foreach ($affectations as $affectation)
                        @php $cle = "lignes.{$affectation->id}"; @endphp
                        <tr>
                            <td class="font-medium"><span class="mr-2 inline-block size-2.5 rounded-full" style="background-color: {{ $affectation->matiere->couleur ?? '#94a3b8' }}"></span>{{ $affectation->matiere->libelle }}</td>
                            <td class="min-w-56">
                                <select name="lignes[{{ $affectation->id }}][enseignant_id]" class="champ" @disabled(! $modifiable) aria-label="Enseignant pour {{ $affectation->matiere->libelle }}">
                                    <option value="">— Non pourvu —</option>
                                    @foreach ($enseignants as $e)
                                        <option value="{{ $e->id }}" @selected((string) old("$cle.enseignant_id", $affectation->enseignant_id) === (string) $e->id)>
                                            {{ $e->user->name }}{{ $e->matieres->contains('id', $affectation->matiere_id) ? ' ★' : '' }}
                                        </option>
                                    @endforeach
                                    @if ($affectation->enseignant && ! $affectation->enseignant->est_actif)
                                        <option value="{{ $affectation->enseignant_id }}" selected>{{ $affectation->enseignant->user->name }} (inactif)</option>
                                    @endif
                                </select>
                                @error("$cle.enseignant_id")<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            </td>
                            <td>
                                <input type="number" name="lignes[{{ $affectation->id }}][coefficient]" value="{{ old("$cle.coefficient", (float) $affectation->coefficient) }}" min="0.5" max="20" step="0.5" required
                                    @disabled(! $modifiable) @class(['champ', 'champ-erreur' => $errors->has("$cle.coefficient")]) aria-label="Coefficient">
                            </td>
                            <td>
                                <input type="number" name="lignes[{{ $affectation->id }}][ordre_bulletin]" value="{{ old("$cle.ordre_bulletin", $affectation->ordre_bulletin) }}" min="0" max="99" required
                                    @disabled(! $modifiable) class="champ" aria-label="Ordre sur le bulletin">
                            </td>
                            <td class="text-xs text-slate-500">{{ $affectation->creneaux_count }} créneau(x)<br>{{ $affectation->evaluations_count }} évaluation(s)</td>
                            <td class="text-right">
                                @if ($modifiable && ! $affectation->evaluations_count)
                                    <button type="submit" form="retirer-{{ $affectation->id }}" class="text-sm font-medium text-red-600 hover:underline">Retirer</button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </x-tableau>
                @if ($modifiable)
                    <div class="flex flex-wrap items-center gap-3 border-t border-slate-100 p-4">
                        <x-bouton>Enregistrer les modifications</x-bouton>
                        <span class="text-xs text-slate-500">★ : matière déclarée dans la fiche de l'enseignant. Chaque changement est journalisé.</span>
                    </div>
                @endif
            </form>
            @foreach ($affectations as $affectation)
                <form method="POST" action="{{ route('affectations.destroy', [$classe, $affectation]) }}" id="retirer-{{ $affectation->id }}" class="hidden"
                      data-confirm="Retirer {{ $affectation->matiere->libelle }} de la classe {{ $classe->nom }} ? Ses créneaux d'emploi du temps seront supprimés.">
                    @csrf @method('DELETE')
                </form>
            @endforeach
        @endif
    </x-carte>

    @if ($modifiable)
        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <x-carte titre="Ajouter une matière">
                @if ($matieresDisponibles->isEmpty())
                    <p class="text-sm text-slate-500">Toutes les matières du référentiel sont déjà affectées à cette classe.</p>
                @else
                    <form method="POST" action="{{ route('affectations.store', $classe) }}" class="grid gap-3 sm:grid-cols-2">
                        @csrf
                        <x-selection nom="matiere_id" label="Matière" :options="$matieresDisponibles" vide="— Choisir —" requis />
                        <x-selection nom="enseignant_id" label="Enseignant" :options="$enseignants->mapWithKeys(fn ($e) => [$e->id => $e->user->name])" vide="— Non pourvu —" />
                        <x-champ nom="coefficient" label="Coefficient" type="number" min="0.5" max="20" step="0.5" valeur="1" requis />
                        <div class="self-end"><x-bouton icone="plus">Ajouter</x-bouton></div>
                    </form>
                @endif
            </x-carte>

            <x-carte titre="Reprendre les matières d'une autre classe">
                @if ($modeles->isEmpty())
                    <p class="text-sm text-slate-500">Aucune autre classe n'a encore de matières.</p>
                @else
                    <form method="POST" action="{{ route('affectations.copier', $classe) }}" class="space-y-3">
                        @csrf
                        <x-selection nom="source_id" label="Classe modèle" vide="— Choisir —" requis
                            :options="$modeles->mapWithKeys(fn ($c) => [$c->id => $c->nom.' ('.$c->anneeScolaire->libelle.')'])" />
                        <x-case nom="avec_enseignants" label="Reprendre aussi les enseignants" aide="Utile d'une année sur l'autre ; seuls les enseignants actifs sont repris." />
                        <x-bouton variante="secondaire">Reprendre matières et coefficients</x-bouton>
                        <p class="text-xs text-slate-500">Les matières déjà présentes ne sont pas modifiées.</p>
                    </form>
                @endif
            </x-carte>
        </div>
    @endif
</x-layouts.app>
