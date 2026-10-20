<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Support\Autorisations;
use Illuminate\Console\Command;

/**
 * Génère docs/roles-permissions.md à partir de la source unique App\Support\Autorisations.
 */
class DocumenterPermissions extends Command
{
    protected $signature = 'ecole:doc-permissions';

    protected $description = 'Régénère docs/roles-permissions.md depuis la matrice des autorisations';

    public function handle(): int
    {
        file_put_contents(base_path('docs/roles-permissions.md'), self::contenu());
        $this->info('docs/roles-permissions.md régénéré.');

        return self::SUCCESS;
    }

    public static function contenu(): string
    {
        $matrice = Autorisations::matrice();
        $roles = Role::cases();

        $md = "# Rôles et permissions\n\n";
        $md .= "> Fichier généré par `php artisan ecole:doc-permissions` à partir de `app/Support/Autorisations.php` (source unique, utilisée aussi par le seeder). Ne pas modifier à la main.\n\n";
        $md .= "Les rôles sont **cumulables** : un utilisateur dispose de l'union des permissions de ses rôles. Les permissions protègent les routes (middleware `permission:`) ; les **Policies** ajoutent les contrôles propres à chaque donnée (affectations de l'enseignant, lien parent–enfant, publication…). Masquer un bouton n'est jamais la seule protection.\n\n";

        $md .= "## Matrice\n\n| Permission | Description | ".implode(' | ', array_map(fn (Role $r) => $r->libelle(), $roles))." |\n";
        $md .= '|---|---|'.str_repeat('---|', count($roles))."\n";
        foreach (Autorisations::PERMISSIONS as $permission => $description) {
            $md .= "| `{$permission}` | {$description} | ".implode(' | ', array_map(fn (Role $r) => in_array($permission, $matrice[$r->value] ?? [], true) ? '✔' : '', $roles))." |\n";
        }

        $md .= "\n## Accès accordés par les Policies (sans permission)\n\n";
        $md .= "| Rôle | Accès | Contrôle |\n|---|---|---|\n";
        $md .= "| Parent | Ses enfants : résumé, résultats et bulletins **publiés**, absences, frais, paiements et reçus, emploi du temps ; photo de l'enfant ; annonces qui le concernent | `ElevePolicy::suivre` / `suivreFinances`, `BulletinPolicy`, `PaiementPolicy` — lien responsable–élève vérifié à chaque requête |\n";
        $md .= "| Élève | Son espace : résultats et bulletins publiés, absences, emploi du temps, cours publiés et devoirs de sa classe, **sa** copie uniquement | `ElevePolicy::suivre`, `CoursPolicy`, `DevoirPolicy::voirRemise` |\n";
        $md .= "| Enseignant | Ses affectations : évaluations et notes, cours et devoirs, copies ; liste des élèves (sans coordonnées des familles) et emploi du temps des classes où il intervient ; appel de ces classes ; appréciations s'il est professeur principal | `EvaluationPolicy`, `CoursPolicy`, `DevoirPolicy`, `ClassePolicy` |\n";

        $md .= "\n## Principes\n\n";
        $md .= "- L'**administrateur technique** configure l'application et les comptes ; il n'a aucun accès aux notes ni aux opérations financières.\n";
        $md .= "- Les opérations sensibles sont réservées à la **direction** : déverrouiller une évaluation, publier ou retirer des bulletins, annuler un paiement ou une dépense, accorder une remise.\n";
        $md .= "- Les **comptes parents et élèves** sont créés depuis les fiches (secrétariat), jamais par auto-inscription ; mot de passe temporaire à changer à la première connexion.\n";
        $md .= "- Toutes les actions sensibles sont inscrites au **journal d'audit** (ajout seul).\n";

        return $md;
    }
}
