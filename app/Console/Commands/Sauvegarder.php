<?php

namespace App\Console\Commands;

use App\Services\JournalService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use ZipArchive;

/**
 * Sauvegarde complète : base MySQL (mysqldump) + fichiers privés (photos, pièces, reçus, copies).
 * Produit une archive unique horodatée ; la restauration est décrite dans docs/deploiement.md.
 */
class Sauvegarder extends Command
{
    protected $signature = 'ecole:sauvegarder {--sans-fichiers : Sauvegarder uniquement la base}';

    protected $description = 'Sauvegarde la base de données et les fichiers privés dans une archive horodatée';

    public function handle(JournalService $journal): int
    {
        $connexion = config('database.connections.'.config('database.default'));
        if (($connexion['driver'] ?? null) !== 'mysql' && ($connexion['driver'] ?? null) !== 'mariadb') {
            $this->error('La sauvegarde automatique prend en charge MySQL / MariaDB uniquement.');

            return self::FAILURE;
        }

        $dossier = rtrim(config('sauvegarde.dossier'), '/\\');
        File::ensureDirectoryExists($dossier);
        $horodatage = now()->format('Y-m-d_His');
        $sql = "{$dossier}/base-{$horodatage}.sql";

        // Mot de passe transmis par variable d'environnement : il n'apparaît pas dans la liste des processus.
        $process = new Process([
            config('sauvegarde.mysqldump'),
            '--host='.$connexion['host'], '--port='.$connexion['port'], '--user='.$connexion['username'],
            '--single-transaction', '--routines', '--default-character-set=utf8mb4', '--result-file='.$sql,
            $connexion['database'],
        ], null, ['MYSQL_PWD' => (string) $connexion['password']], null, 600);
        $process->run();
        if (! $process->isSuccessful() || ! is_file($sql) || filesize($sql) === 0) {
            $this->error('Échec de mysqldump : '.trim($process->getErrorOutput() ?: 'vérifiez SAUVEGARDE_MYSQLDUMP dans .env.'));
            File::delete($sql);

            return self::FAILURE;
        }

        $archive = "{$dossier}/sauvegarde-{$horodatage}.zip";
        $zip = new ZipArchive;
        if ($zip->open($archive, ZipArchive::CREATE) !== true) {
            $this->error("Impossible de créer l'archive {$archive}.");

            return self::FAILURE;
        }
        $zip->addFile($sql, 'base.sql');
        $fichiers = 0;
        if (! $this->option('sans-fichiers')) {
            $prive = storage_path('app/private');
            foreach (File::exists($prive) ? File::allFiles($prive) : [] as $fichier) {
                $zip->addFile($fichier->getPathname(), 'fichiers/'.str_replace('\\', '/', $fichier->getRelativePathname()));
                $fichiers++;
            }
        }
        $zip->close();
        File::delete($sql);

        // Rotation : suppression des sauvegardes plus anciennes que la durée de conservation.
        $limite = now()->subDays(config('sauvegarde.conservation_jours'))->getTimestamp();
        $supprimees = 0;
        foreach (File::glob("{$dossier}/sauvegarde-*.zip") as $ancienne) {
            if (filemtime($ancienne) < $limite) {
                File::delete($ancienne);
                $supprimees++;
            }
        }

        $taille = round(filesize($archive) / 1024 / 1024, 2);
        $journal->enregistrer('sauvegarde.effectuee', null, "Sauvegarde {$horodatage} : base + {$fichiers} fichier(s), {$taille} Mo");
        $this->info("Sauvegarde créée : {$archive} ({$taille} Mo, {$fichiers} fichier(s) privé(s)).");
        if ($supprimees) {
            $this->line("{$supprimees} ancienne(s) sauvegarde(s) supprimée(s).");
        }
        $this->warn('Copiez régulièrement ce dossier sur un support externe (disque USB, stockage en ligne).');

        return self::SUCCESS;
    }
}
