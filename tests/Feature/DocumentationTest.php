<?php

namespace Tests\Feature;

use App\Console\Commands\DocumenterPermissions;
use Tests\TestCase;

/**
 * La documentation exigée par le cahier des charges (§ 9 et § 15) existe et reste cohérente avec le code.
 */
class DocumentationTest extends TestCase
{
    public function test_les_documents_obligatoires_existent(): void
    {
        foreach (['README.md', '.env.example', 'docs/cahier-des-charges.md', 'docs/architecture.md', 'docs/roles-permissions.md',
            'docs/base-de-donnees.md', 'docs/installation-xampp.md', 'docs/tests.md', 'docs/deploiement.md', 'docs/regles-metier.md',
            'docs/uml/01-cas-utilisation-general.md', 'docs/uml/02-cas-utilisation-modules.md', 'docs/uml/03-diagramme-de-classes.md',
            'docs/uml/04-sequences.md', 'docs/uml/05-activites.md', 'docs/uml/06-modele-conceptuel.md', 'docs/uml/07-modele-logique.md',
            'docs/uml/08-schema-relationnel.md'] as $fichier) {
            $this->assertFileExists(base_path($fichier));
        }
    }

    public function test_la_matrice_des_permissions_est_a_jour(): void
    {
        $this->assertSame(DocumenterPermissions::contenu(), file_get_contents(base_path('docs/roles-permissions.md')),
            'docs/roles-permissions.md n\'est plus synchronisé : lancez php artisan ecole:doc-permissions.');
    }

    public function test_aucun_secret_dans_env_example(): void
    {
        $exemple = file_get_contents(base_path('.env.example'));
        $this->assertMatchesRegularExpression('/^APP_KEY=$/m', $exemple);
        $this->assertMatchesRegularExpression('/^DB_PASSWORD=$/m', $exemple);
    }

    public function test_chaque_migration_est_documentee_dans_le_schema(): void
    {
        $schema = file_get_contents(base_path('docs/uml/08-schema-relationnel.md'));
        foreach (['inscriptions', 'paiements', 'bulletins', 'remises_devoirs', 'enseignant_matiere', 'annonces'] as $table) {
            $this->assertStringContainsString("### `{$table}`", $schema);
        }
        $this->assertStringContainsString('notifiee_at', $schema, 'Schéma à régénérer : php artisan ecole:doc-base');
    }
}
