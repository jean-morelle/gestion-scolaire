<?php

namespace App\Providers;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Carbon::setLocale('fr');

        // Noms courts et stables dans le journal d'audit et les notifications.
        Relation::enforceMorphMap([
            'user' => \App\Models\User::class,
            'eleve' => \App\Models\Eleve::class,
            'responsable' => \App\Models\Responsable::class,
            'inscription' => \App\Models\Inscription::class,
            'classe' => \App\Models\Classe::class,
            'annee_scolaire' => \App\Models\AnneeScolaire::class,
            'periode' => \App\Models\Periode::class,
            'etablissement' => \App\Models\Etablissement::class,
            'enseignant' => \App\Models\Enseignant::class,
            'affectation' => \App\Models\Affectation::class,
            'evaluation' => \App\Models\Evaluation::class,
            'note' => \App\Models\Note::class,
            'publication' => \App\Models\Publication::class,
            'appel' => \App\Models\Appel::class,
            'justificatif' => \App\Models\Justificatif::class,
            'paiement' => \App\Models\Paiement::class,
            'remise' => \App\Models\Remise::class,
            'grille_frais' => \App\Models\GrilleFrais::class,
            'depense' => \App\Models\Depense::class,
            'cours' => \App\Models\Cours::class,
            'devoir' => \App\Models\Devoir::class,
            'remise_devoir' => \App\Models\RemiseDevoir::class,
            'annonce' => \App\Models\Annonce::class,
            'document_eleve' => \App\Models\DocumentEleve::class,
        ]);
    }
}
