<x-layouts.app titre="Nouvelle dépense">
    <x-entete titre="Nouvelle dépense" sous-titre="Une dépense enregistrée ne peut plus être modifiée : en cas d'erreur, elle est annulée puis ressaisie." :retour="route('depenses.index')" />

    <x-carte class="max-w-3xl">
        <form method="POST" action="{{ route('depenses.store') }}" enctype="multipart/form-data" class="space-y-5"
              data-confirm="Enregistrer cette dépense ? Elle recevra un numéro définitif.">
            @csrf
            <div class="grid gap-4 sm:grid-cols-2">
                <x-champ nom="libelle" label="Libellé" placeholder="Ex. Achat de craies et cahiers" requis maxlength="150" class="sm:col-span-2" />
                <x-selection nom="categorie_depense_id" label="Catégorie" :options="$categories" vide="— Choisir —" requis />
                <x-champ nom="montant" :label="'Montant ('.etablissement()->devise.')'" type="number" min="1" requis />
                <x-champ nom="date_depense" label="Date" type="date" :valeur="now()->toDateString()" :max="now()->toDateString()" requis />
                <x-selection nom="mode" label="Mode de règlement" :options="\App\Enums\ModePaiement::options()" valeur="especes" requis />
                <x-champ nom="beneficiaire" label="Bénéficiaire" maxlength="150" />
                <x-champ nom="reference_piece" label="Référence de la pièce" placeholder="N° de facture, de chèque…" maxlength="100" />
                <x-champ nom="justificatif" label="Pièce justificative" type="file" accept=".pdf,image/jpeg,image/png" class="sm:col-span-2"
                    :aide="'Facultatif. PDF, JPG ou PNG, '.intdiv($tailleMaxKo, 1024).' Mo au maximum. Stockée de façon privée.'" />
            </div>
            <div class="flex gap-2">
                <x-bouton>Enregistrer</x-bouton>
                <x-bouton variante="secondaire" :href="route('depenses.index')">Annuler</x-bouton>
            </div>
        </form>
    </x-carte>
</x-layouts.app>
