<x-layouts.app :titre="$devoir->exists ? 'Modifier le devoir' : 'Nouveau devoir'">
    <x-entete :titre="$devoir->exists ? 'Modifier : '.$devoir->titre : 'Nouveau devoir'" :sous-titre="$affectation->classe->nom.' — '.$affectation->matiere->libelle"
              :retour="$devoir->exists ? route('devoirs.show', $devoir) : route('cours.index')" />

    <x-carte class="max-w-3xl">
        <form method="POST" enctype="multipart/form-data" action="{{ $devoir->exists ? route('devoirs.update', $devoir) : route('devoirs.store') }}" class="space-y-5">
            @csrf
            @if ($devoir->exists) @method('PUT') @else <input type="hidden" name="affectation_id" value="{{ $affectation->id }}"> @endif
            <x-champ nom="titre" label="Titre" :valeur="$devoir->titre" placeholder="Ex. Exercices 4 à 7 page 52" requis maxlength="150" />
            <x-zone-texte nom="consignes" label="Consignes" :valeur="$devoir->consignes" :lignes="6" requis />
            <div class="grid gap-4 sm:grid-cols-3">
                <x-champ nom="date_limite" label="À rendre le" type="datetime-local" :valeur="old('date_limite', $devoir->date_limite?->format('Y-m-d\TH:i'))" requis class="sm:col-span-2" />
                <x-champ nom="bareme" label="Noté sur" type="number" min="1" max="100" step="0.5" :valeur="(float) $devoir->bareme" requis />
            </div>
            @if ($affectation->cours->isNotEmpty())
                <x-selection nom="cours_id" label="Cours associé" :options="$affectation->cours->pluck('titre', 'id')" :valeur="$devoir->cours_id" vide="— Aucun —" />
            @endif
            <x-champ nom="sujet" label="Sujet (fichier, facultatif)" type="file" :aide="$devoir->fichier_nom ? 'Fichier actuel : '.$devoir->fichier_nom.'. Un nouveau fichier le remplace.' : 'PDF, Word, image…'" />
            <div class="grid gap-3 sm:grid-cols-2">
                <x-case nom="remise_en_ligne" label="Remise en ligne" :coche="$devoir->remise_en_ligne"
                    :aide="etablissement()->parametre('remise_devoirs_en_ligne', true) ? 'Les élèves déposent leur travail dans leur espace.' : 'Désactivée par l\'établissement : le devoir se rendra en classe.'" />
                <x-case nom="accepte_retard" label="Accepter les remises en retard" :coche="$devoir->accepte_retard" aide="Les dépôts après la date limite sont marqués « en retard »." />
            </div>
            <div class="flex gap-2">
                <x-bouton>Enregistrer</x-bouton>
                <x-bouton variante="secondaire" :href="$devoir->exists ? route('devoirs.show', $devoir) : route('cours.index')">Annuler</x-bouton>
            </div>
        </form>
    </x-carte>
</x-layouts.app>
