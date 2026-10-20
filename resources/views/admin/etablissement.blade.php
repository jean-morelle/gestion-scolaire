<x-layouts.app titre="Établissement">
    <x-entete titre="Paramètres de l'établissement" sous-titre="Ces informations apparaissent sur les bulletins, reçus et documents imprimés." />

    @php $p = fn ($cle, $defaut = null) => $etablissement->parametre($cle, $defaut); @endphp
    <form method="POST" action="{{ route('etablissement.update') }}" enctype="multipart/form-data" class="space-y-6">
        @csrf
        @method('PUT')
        <x-carte titre="Identité">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-champ nom="nom" label="Nom de l'établissement" :valeur="$etablissement->nom" requis class="sm:col-span-2" />
                <x-champ nom="sigle" label="Sigle" :valeur="$etablissement->sigle" />
                <x-champ nom="slogan" label="Devise / slogan" :valeur="$etablissement->slogan" />
                <x-champ nom="chef_etablissement" label="Chef d'établissement" :valeur="$etablissement->chef_etablissement" aide="Nom imprimé sous la signature des bulletins." />
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700" for="logo">Logo (PNG/JPG, 1 Mo max.)</label>
                    <div class="flex items-center gap-3">
                        @if ($etablissement->logo_chemin)
                            <img src="{{ route('etablissement.logo') }}" alt="Logo actuel" class="size-12 rounded-lg border object-contain">
                        @endif
                        <input id="logo" type="file" name="logo" accept="image/png,image/jpeg" class="text-sm">
                    </div>
                    @error('logo')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div class="sm:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-slate-700" for="image_connexion">Photo de la page de connexion (JPG/PNG, 4 Mo max.)</label>
                    <div class="flex flex-wrap items-center gap-3">
                        @if ($fond = image_connexion())
                            <img src="{{ $fond }}" alt="Photo actuelle" class="h-16 w-28 rounded-lg border object-cover">
                        @endif
                        <input id="image_connexion" type="file" name="image_connexion" accept="image/jpeg,image/png,image/webp" class="text-sm">
                    </div>
                    <p class="mt-1 text-xs text-slate-500">Une photo de l'établissement, de préférence en paysage. Elle apparaît derrière le formulaire de connexion.</p>
                    @error('image_connexion')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>
        </x-carte>

        <x-carte titre="Coordonnées">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-champ nom="adresse" label="Adresse" :valeur="$etablissement->adresse" class="sm:col-span-2" />
                <x-champ nom="ville" label="Ville" :valeur="$etablissement->ville" />
                <x-champ nom="telephone" label="Téléphone" :valeur="$etablissement->telephone" />
                <x-champ nom="email" label="E-mail" type="email" :valeur="$etablissement->email" />
                <x-champ nom="devise" label="Devise monétaire" :valeur="$etablissement->devise ?? 'FCFA'" requis aide="Montants enregistrés en unités entières (sans centimes)." />
            </div>
        </x-carte>

        <x-carte titre="Règles scolaires">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <x-champ nom="parametres[decimales]" label="Décimales affichées (moyennes)" type="number" min="0" max="3" :valeur="$p('decimales', 2)" requis />
                <x-selection nom="parametres[absence_evaluation]" label="Absence à une évaluation" :valeur="$p('absence_evaluation', 'ignoree')"
                    :options="['ignoree' => 'Non comptée dans la moyenne', 'zero' => 'Comptée comme 0']" requis />
                <x-champ nom="parametres[delai_modification_appel_heures]" label="Délai de modification d'un appel (heures)" type="number" min="0" :valeur="$p('delai_modification_appel_heures', 48)" requis
                    aide="Au-delà, seule la vie scolaire peut le modifier." />
                <x-champ nom="parametres[taille_max_fichier_ko]" label="Taille maximale des fichiers (Ko)" type="number" min="100" :valeur="$p('taille_max_fichier_ko', 5120)" requis />
                <x-champ nom="parametres[jours_rappel_echeance]" label="Rappel avant échéance de paiement (jours)" type="number" min="0" :valeur="$p('jours_rappel_echeance', 7)" requis />
            </div>
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <x-case nom="parametres[notifier_absences]" label="Notifier les parents des absences" :coche="$p('notifier_absences', true)" />
                <x-case nom="parametres[remise_devoirs_en_ligne]" label="Autoriser la remise des devoirs en ligne" :coche="$p('remise_devoirs_en_ligne', true)" />
            </div>

            <h3 class="mt-6 mb-2 text-sm font-semibold text-slate-800">Mentions (moyenne minimale sur 20)</h3>
            <div class="grid gap-3 sm:grid-cols-5">
                @foreach ($p('mentions', []) as $i => $mention)
                    <div class="rounded-xl border border-slate-200 p-3">
                        <x-champ nom="parametres[mentions][{{ $i }}][libelle]" label="Libellé" :valeur="$mention['libelle']" requis />
                        <x-champ nom="parametres[mentions][{{ $i }}][min]" label="À partir de" type="number" step="0.01" min="0" max="20" :valeur="$mention['min']" requis class="mt-2" />
                    </div>
                @endforeach
            </div>
        </x-carte>

        <x-bouton>Enregistrer les paramètres</x-bouton>
    </form>
</x-layouts.app>
