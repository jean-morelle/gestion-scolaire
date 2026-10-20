# EduGestion — Application intégrée de gestion scolaire

Application web de gestion d'un établissement scolaire (primaire, collège, lycée), développée pour **GKAS GROUP SARL U**. Une installation par établissement : code, base de données et configuration propres.

## Modules

| Module | Fonctionnalités |
|---|---|
| **Gestion scolaire** | Années et périodes, niveaux, classes, élèves et dossiers (pièces privées), responsables légaux, inscriptions et réinscriptions (en lot), enseignants, matières, affectations et coefficients, emplois du temps (détection des conflits), appel par demi-journée et justificatifs, évaluations et notes (validation, déverrouillage tracé), calcul des moyennes et rangs, bulletins PDF publiés et versionnés |
| **Espace parents et élèves** | Résultats et bulletins publiés, absences, emploi du temps, frais, paiements et reçus (parents), cours et devoirs (élèves) — strictement limité à ses enfants / à soi |
| **Enseignants** | Profils, matières enseignées, « Mes classes », saisie des notes et de l'appel de ses classes, suivi de son activité |
| **Plateforme d'apprentissage** | Cours (chapitres, documents, exercices, liens), devoirs avec date limite, dépôt en ligne, correction et notation |
| **Gestion financière** | Types de frais et grilles par niveau avec échéanciers, frais facultatifs, remises, encaissements (imputation automatique, reçus PDF numérotés sans trou), annulations motivées, soldes et retards, dépenses, rapports et exports CSV |
| **Transverse** | 8 rôles cumulables, tableau de bord par rôle, annonces ciblées et programmées, notifications internes, rappels d'échéances, journal d'audit, statistiques scolaires, sauvegarde automatique |

## Démarrage rapide (Windows / XAMPP)

```bash
composer install && npm install && npm run build
copy .env.example .env
php artisan key:generate
# créer la base MySQL « gestion-ecole » (utf8mb4_unicode_ci), puis :
php artisan migrate --seed
php artisan ecole:creer-admin
```

Ouvrir **http://localhost/gestion-scolaire/public** (ou `php artisan serve` puis http://127.0.0.1:8000).

Procédure détaillée, tâches planifiées et dépannage : **[docs/installation-xampp.md](docs/installation-xampp.md)**.

Pour essayer avec des données fictives sans toucher à votre base : `php artisan ecole:demo` (base séparée `gestion-ecole-demo`, comptes et mot de passe affichés).

## Commandes utiles

| Commande | Rôle |
|---|---|
| `php artisan ecole:creer-admin` | Créer un administrateur technique |
| `php artisan ecole:demo` | Créer et remplir la base de démonstration (données fictives) |
| `php artisan ecole:sauvegarder` | Sauvegarder la base et les fichiers privés (également chaque nuit à 2 h) |
| `php artisan schedule:run` | Planificateur : à lancer chaque minute (rappels, annonces, sauvegarde) |
| `php artisan test` | Suite de tests (SQLite en mémoire, n'affecte aucune base réelle) |
| `php artisan ecole:doc-permissions` / `ecole:doc-base` | Régénérer la matrice des permissions / le schéma relationnel |

## Technologies

PHP 8.2+, Laravel 12, MySQL 8 / MariaDB 10.4+, Blade, Tailwind CSS 4, Alpine.js, spatie/laravel-permission, barryvdh/laravel-dompdf. Aucun framework JavaScript lourd, aucun service externe obligatoire.

## Documentation

| Document | Contenu |
|---|---|
| [docs/cahier-des-charges.md](docs/cahier-des-charges.md) | Cahier des charges fonctionnel |
| [docs/regles-metier.md](docs/regles-metier.md) | Règles de gestion (calculs, finances, accès, notifications) |
| [docs/architecture.md](docs/architecture.md) | Architecture technique, sécurité, stratégie des valeurs calculées |
| [docs/roles-permissions.md](docs/roles-permissions.md) | Matrice des rôles et permissions (générée depuis le code) |
| [docs/base-de-donnees.md](docs/base-de-donnees.md) | Structure, relations et contraintes de la base |
| [docs/uml/](docs/uml/README.md) | Diagrammes UML (Mermaid) : cas d'utilisation, classes, séquences, activités, MCD, MLD, schéma relationnel |
| [docs/installation-xampp.md](docs/installation-xampp.md) | Installation sous Windows avec XAMPP |
| [docs/deploiement.md](docs/deploiement.md) | Déploiement sur serveur, sauvegarde et restauration |
| [docs/tests.md](docs/tests.md) | Procédures de test et résultats |
| [docs/plan-de-developpement.md](docs/plan-de-developpement.md) | Plan, décisions validées, avancement |

## Sécurité

Mots de passe hachés, CSRF, limitation des tentatives de connexion, validation serveur de toutes les entrées, permissions vérifiées sur les routes **et** par des Policies sur chaque donnée, documents privés hors du dossier public, opérations financières non modifiables (annulation tracée), journal d'audit. Aucun secret dans le dépôt : la configuration se fait dans `.env` (voir `.env.example`).
