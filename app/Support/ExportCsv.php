<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export CSV lisible directement par Excel en français (UTF-8 avec BOM, séparateur « ; »).
 */
final class ExportCsv
{
    /**
     * @param  list<string>  $entetes
     * @param  iterable<array<int, mixed>>  $lignes
     */
    public static function telecharger(string $nomFichier, array $entetes, iterable $lignes): StreamedResponse
    {
        return response()->streamDownload(function () use ($entetes, $lignes) {
            $sortie = fopen('php://output', 'w');
            fwrite($sortie, "\xEF\xBB\xBF");
            fputcsv($sortie, $entetes, ';');
            foreach ($lignes as $ligne) {
                fputcsv($sortie, array_map(self::cellule(...), $ligne), ';');
            }
            fclose($sortie);
        }, $nomFichier, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private static function cellule(mixed $valeur): string
    {
        $texte = match (true) {
            $valeur instanceof \BackedEnum && method_exists($valeur, 'libelle') => $valeur->libelle(),
            $valeur instanceof \DateTimeInterface => $valeur->format('d/m/Y'),
            is_bool($valeur) => $valeur ? 'Oui' : 'Non',
            default => (string) $valeur,
        };

        // Neutralise les formules (injection CSV) : =, +, -, @ en début de cellule.
        return preg_match('/^[=+\-@\t\r]/', $texte) ? "'".$texte : $texte;
    }
}
