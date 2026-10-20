<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Génère le schéma relationnel final (docs/uml/08-schema-relationnel.md) depuis la base MySQL réelle :
 * tables, colonnes, types, clés primaires, étrangères, index uniques et règles de suppression.
 */
class DocumenterBase extends Command
{
    protected $signature = 'ecole:doc-base';

    protected $description = 'Régénère le schéma relationnel (Mermaid + tableaux) depuis la base MySQL';

    /** Tables techniques de Laravel, documentées à part. */
    private const TECHNIQUES = ['cache', 'cache_locks', 'failed_jobs', 'job_batches', 'jobs', 'migrations', 'password_reset_tokens', 'sessions'];

    public function handle(): int
    {
        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->error('Cette commande lit information_schema : utilisez la connexion MySQL.');

            return self::FAILURE;
        }
        $base = DB::connection()->getDatabaseName();

        $colonnes = collect(DB::select('SELECT TABLE_NAME t, COLUMN_NAME c, COLUMN_TYPE type, IS_NULLABLE n, COLUMN_KEY k, COLUMN_DEFAULT d
            FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME, ORDINAL_POSITION', [$base]))->groupBy('t');
        $cles = collect(DB::select('SELECT k.TABLE_NAME t, k.COLUMN_NAME c, k.REFERENCED_TABLE_NAME rt, k.REFERENCED_COLUMN_NAME rc, r.DELETE_RULE regle
            FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r
              ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME
            WHERE k.TABLE_SCHEMA = ? AND k.REFERENCED_TABLE_NAME IS NOT NULL ORDER BY k.TABLE_NAME', [$base]));
        $uniques = collect(DB::select("SELECT TABLE_NAME t, INDEX_NAME i, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) cols
            FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND NON_UNIQUE = 0 AND INDEX_NAME <> 'PRIMARY'
            GROUP BY TABLE_NAME, INDEX_NAME ORDER BY TABLE_NAME", [$base]))->groupBy('t');

        $metier = $colonnes->keys()->reject(fn ($t) => in_array($t, self::TECHNIQUES, true))->values();
        file_put_contents(base_path('docs/uml/08-schema-relationnel.md'), $this->rendu($metier, $colonnes, $cles, $uniques));
        $this->info("docs/uml/08-schema-relationnel.md régénéré ({$metier->count()} tables métier, {$cles->count()} clés étrangères).");

        return self::SUCCESS;
    }

    private function rendu(Collection $tables, Collection $colonnes, Collection $cles, Collection $uniques): string
    {
        $md = "# 8. Schéma relationnel final\n\n";
        $md .= '> Généré par `php artisan ecole:doc-base` depuis la base MySQL migrée (le '.now()->format('d/m/Y')."). Reflète exactement les migrations.\n";
        $md .= "> Montants en `bigint unsigned` (unité monétaire entière, ex. FCFA) ; notes et coefficients en `decimal`. Les tables techniques de Laravel (".implode(', ', self::TECHNIQUES).") ne sont pas représentées.\n\n";

        $md .= "## Diagramme\n\n```mermaid\nerDiagram\n";
        foreach ($cles->filter(fn ($k) => $tables->contains($k->t) && $tables->contains($k->rt)) as $k) {
            $nullable = optional($colonnes[$k->t]->firstWhere('c', $k->c))->n === 'YES';
            $md .= "    {$k->rt} ".($nullable ? '|o--o{' : '||--o{')." {$k->t} : \"{$k->c}\"\n";
        }
        foreach ($tables as $t) {
            $md .= "    {$t} {\n";
            foreach ($colonnes[$t] as $c) {
                $type = preg_replace('/[^a-z_]/', '', strtolower(explode('(', $c->type)[0]));
                $marque = $c->k === 'PRI' ? ' PK' : ($cles->contains(fn ($k) => $k->t === $t && $k->c === $c->c) ? ' FK' : ($c->k === 'UNI' ? ' UK' : ''));
                $md .= "        {$type} {$c->c}{$marque}\n";
            }
            $md .= "    }\n";
        }
        $md .= "```\n\n## Tables\n\n";

        foreach ($tables as $t) {
            $md .= "### `{$t}`\n\n| Colonne | Type | Null | Clé | Référence (suppression) |\n|---|---|---|---|---|\n";
            foreach ($colonnes[$t] as $c) {
                $fk = $cles->first(fn ($k) => $k->t === $t && $k->c === $c->c);
                $md .= "| `{$c->c}` | {$c->type} | ".($c->n === 'YES' ? 'oui' : 'non').' | '.match ($c->k) { 'PRI' => 'PK', 'UNI' => 'unique', 'MUL' => 'index', default => '' }
                    .' | '.($fk ? "`{$fk->rt}.{$fk->rc}` ({$fk->regle})" : '')." |\n";
            }
            $u = $uniques->get($t, collect())->filter(fn ($x) => str_contains($x->cols, ','));
            if ($u->isNotEmpty()) {
                $md .= "\nUnicité composée : ".$u->map(fn ($x) => '('.str_replace(',', ', ', $x->cols).')')->join(' ; ')."\n";
            }
            $md .= "\n";
        }

        return $md;
    }
}
