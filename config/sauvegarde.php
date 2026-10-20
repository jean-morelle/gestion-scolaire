<?php

return [
    // Chemin de mysqldump (sous XAMPP : C:\xampp\mysql\bin\mysqldump.exe).
    'mysqldump' => env('SAUVEGARDE_MYSQLDUMP', 'mysqldump'),

    // Dossier des sauvegardes (hors du dossier public). Copiez-le régulièrement sur un support externe.
    'dossier' => env('SAUVEGARDE_DOSSIER', storage_path('app/sauvegardes')),

    // Nombre de jours de conservation des sauvegardes locales.
    'conservation_jours' => (int) env('SAUVEGARDE_JOURS', 30),
];
