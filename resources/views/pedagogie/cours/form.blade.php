<x-layouts.app :titre="$cours->exists ? 'Modifier le cours' : 'Nouveau cours'">
    <x-entete :titre="$cours->exists ? 'Modifier : '.$cours->titre : 'Nouveau cours'" :sous-titre="$affectation->classe->nom.' — '.$affectation->matiere->libelle"
              :retour="$cours->exists ? route('cours.show', $cours) : route('cours.index')" />

    <x-carte class="max-w-3xl">
        <form method="POST" action="{{ $cours->exists ? route('cours.update', $cours) : route('cours.store') }}" class="space-y-5">
            @csrf
            @if ($cours->exists) @method('PUT') @else <input type="hidden" name="affectation_id" value="{{ $affectation->id }}"> @endif
            <x-champ nom="titre" label="Titre du cours" :valeur="$cours->titre" placeholder="Ex. Chapitre 3 — Les fractions" requis maxlength="150" />
            <x-zone-texte nom="description" label="Présentation" :valeur="$cours->description" :lignes="4" aide="Objectifs, prérequis… (facultatif)" />
            <div class="flex gap-2">
                <x-bouton>Enregistrer</x-bouton>
                <x-bouton variante="secondaire" :href="$cours->exists ? route('cours.show', $cours) : route('cours.index')">Annuler</x-bouton>
            </div>
        </form>
    </x-carte>
</x-layouts.app>
