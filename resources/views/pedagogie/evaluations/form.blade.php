<x-layouts.app :titre="$evaluation->exists ? 'Modifier l\'évaluation' : 'Nouvelle évaluation'">
    <x-entete :titre="$evaluation->exists ? 'Modifier : '.$evaluation->libelle : 'Nouvelle évaluation'"
              :sous-titre="$affectation->classe->nom.' — '.$affectation->matiere->libelle"
              :retour="$evaluation->exists ? route('evaluations.show', $evaluation) : route('evaluations.index')" />

    <x-carte class="max-w-3xl">
        @if ($periodes->isEmpty())
            <x-vide message="Aucune période ouverte pour cette année : aucune évaluation ne peut être créée." icone="calendrier" />
        @else
            <form method="POST" action="{{ $evaluation->exists ? route('evaluations.update', $evaluation) : route('evaluations.store') }}" class="space-y-5">
                @csrf
                @if ($evaluation->exists) @method('PUT') @else <input type="hidden" name="affectation_id" value="{{ $affectation->id }}"> @endif
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-champ nom="libelle" label="Intitulé" :valeur="$evaluation->libelle" placeholder="Ex. Devoir n°1" requis maxlength="100" class="sm:col-span-2" />
                    <x-selection nom="type" label="Type" :options="\App\Enums\TypeEvaluation::options()" :valeur="$evaluation->type" requis />
                    <x-selection nom="periode_id" label="Période" :options="$periodes" :valeur="$evaluation->periode_id" vide="— Choisir —" requis />
                    <x-champ nom="date_evaluation" label="Date" type="date" :valeur="$evaluation->date_evaluation" requis />
                    <div class="grid grid-cols-2 gap-4">
                        <x-champ nom="bareme" label="Noté sur" type="number" min="1" max="100" step="0.5" :valeur="(float) $evaluation->bareme" requis />
                        <x-champ nom="poids" label="Poids" type="number" min="0.25" max="10" step="0.25" :valeur="(float) $evaluation->poids" requis aide="1 par défaut" />
                    </div>
                </div>
                <p class="text-xs text-slate-500">Les notes sont ramenées sur 20 pour le calcul de la moyenne de la matière, pondérées par le poids.</p>
                <div class="flex gap-2">
                    <x-bouton>Enregistrer</x-bouton>
                    <x-bouton variante="secondaire" :href="$evaluation->exists ? route('evaluations.show', $evaluation) : route('evaluations.index')">Annuler</x-bouton>
                </div>
            </form>
        @endif
    </x-carte>
</x-layouts.app>
