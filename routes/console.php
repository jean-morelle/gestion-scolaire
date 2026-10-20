<?php

use App\Services\AnnonceService;
use App\Services\RappelEcheanceService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
| Tâches planifiées : à lancer chaque minute par le planificateur du système
| (cron « * * * * * php artisan schedule:run » ou Planificateur de tâches Windows), voir docs/installation.md.
*/

Artisan::command('finances:rappels', function (RappelEcheanceService $service) {
    $n = $service->envoyer();
    $this->info("{$n} rappel(s) d'échéance envoyé(s).");
})->purpose('Notifie les responsables des échéances de paiement proches ou dépassées');

Artisan::command('annonces:diffuser', function (AnnonceService $service) {
    $n = $service->diffuserEnAttente();
    $this->info("{$n} notification(s) d'annonce envoyée(s).");
})->purpose('Notifie les destinataires des annonces programmées dont la date est arrivée');

Schedule::command('finances:rappels')->dailyAt('07:00');
Schedule::command('annonces:diffuser')->everyFifteenMinutes();
Schedule::command('ecole:sauvegarder')->dailyAt('02:00');
