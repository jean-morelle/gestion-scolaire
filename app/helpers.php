<?php

use App\Models\AnneeScolaire;
use App\Models\Etablissement;
use Carbon\CarbonInterface;

if (! function_exists('etablissement')) {
    /**
     * Paramètres de l'établissement (une seule ligne), mis en cache pour la requête.
     */
    function etablissement(): Etablissement
    {
        return app()->bound('etablissement.courant')
            ? app('etablissement.courant')
            : tap(Etablissement::courant() ?? new Etablissement(['nom' => config('app.name'), 'devise' => 'FCFA']),
                fn ($e) => app()->instance('etablissement.courant', $e));
    }
}

if (! function_exists('image_connexion')) {
    /**
     * Photo de fond de la connexion : celle téléversée dans les paramètres, sinon public/images/imageEcole.jpg.
     */
    function image_connexion(): ?string
    {
        if (etablissement()->exists && etablissement()->parametre('image_connexion')) {
            return route('etablissement.image-connexion');
        }

        return is_file(public_path('images/imageEcole.jpg')) ? asset('images/imageEcole.jpg') : null;
    }
}

if (! function_exists('annee_active')) {
    function annee_active(): ?AnneeScolaire
    {
        // Conteneur sous forme de tableau : une instance null ne peut pas être résolue par le conteneur.
        if (! app()->bound('annee.active')) {
            app()->instance('annee.active', ['annee' => AnneeScolaire::active()]);
        }

        return app('annee.active')['annee'];
    }
}

if (! function_exists('montant')) {
    /**
     * Montant entier dans la devise de l'établissement : « 125 000 FCFA ».
     */
    function montant(int|float|null $valeur, bool $avecDevise = true): string
    {
        $texte = number_format((float) $valeur, 0, ',', ' ');

        return $avecDevise ? $texte.' '.etablissement()->devise : $texte;
    }
}

if (! function_exists('note_fr')) {
    /**
     * Nombre décimal au format français (« 12,50 »), tiret si absent.
     */
    function note_fr(int|float|string|null $valeur, int $decimales = 2): string
    {
        return $valeur === null || $valeur === '' ? '—' : number_format((float) $valeur, $decimales, ',', ' ');
    }
}

if (! function_exists('date_fr')) {
    function date_fr(?CarbonInterface $date, bool $avecHeure = false): string
    {
        return $date ? $date->format($avecHeure ? 'd/m/Y H:i' : 'd/m/Y') : '—';
    }
}
