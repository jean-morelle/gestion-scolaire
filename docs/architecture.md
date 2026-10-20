# Architecture technique

> Description de l'application livrée. La version de conception initiale (propositions du 12/10/2026) est conservée dans [`archives/architecture-technique-v0.1.md`](archives/architecture-technique-v0.1.md).

## 1. Pile technique

| Élément | Choix | Remarque |
|---|---|---|
| Langage, framework | PHP ≥ 8.2, **Laravel 12** | MVC + services métier |
| Base de données | **MySQL 8 / MariaDB ≥ 10.4**, InnoDB, `utf8mb4_unicode_ci` | 48 tables métier, voir [`base-de-donnees.md`](base-de-donnees.md) |
| Interface | Blade + composants Blade, **Tailwind CSS 4**, **Alpine.js** (interactions légères) | Compilée par Vite ; aucune SPA |
| Authentification | Contrôleurs maison (connexion par e-mail, téléphone ou matricule) | Mots de passe hachés (bcrypt), limitation des tentatives, changement obligatoire du mot de passe temporaire |
| Rôles et permissions | **spatie/laravel-permission** + **Policies** Laravel | Matrice : [`roles-permissions.md`](roles-permissions.md) |
| PDF | **barryvdh/laravel-dompdf** | Bulletins, reçus ; police DejaVu en sous-ensemble (~25 Ko par PDF) |
| Exports | CSV natif (UTF-8 avec BOM, séparateur « ; », protégé contre l'injection de formules) | `App\Support\ExportCsv` |
| Notifications | Canal `database` de Laravel | Aucun SMS ni e-mail simulé |
| Tâches planifiées | Planificateur Laravel (`routes/console.php`) | Rappels d'échéances, annonces programmées, sauvegarde nocturne |
| Tests | PHPUnit 11, SQLite en mémoire | Voir [`tests.md`](tests.md) |

Volontairement **absents** : React, Vue, Livewire, API REST, multi-établissement, paiement en ligne (point d'extension prévu, voir § 7).

## 2. Organisation du code

```
app/
  Console/Commands/      ecole:creer-admin, ecole:demo, ecole:sauvegarder, ecole:doc-permissions, ecole:doc-base
  Contracts/             PasserellePaiement (paiement en ligne futur)
  Enums/                 statuts et listes (Role, StatutPaiement, TypeEvaluation, Creneau…)
  Http/Controllers/
    Admin/               établissement, années et périodes, référentiel, utilisateurs, journal
    Scolarite/           classes, élèves, responsables, pièces du dossier, inscriptions
    Pedagogie/           enseignants, affectations, emplois du temps, évaluations, résultats, bulletins,
                         cours, devoirs, statistiques scolaires, « mes classes »
    VieScolaire/         appel, justificatifs
    Finance/             frais et grilles, situation des élèves, paiements et reçus, remises, dépenses, rapports
    Famille/             espace famille et espace élève (suivi, cours, devoirs)
    AnnonceController, NotificationController, TableauDeBordController, Auth/
  Http/Requests/         validation serveur (Form Requests)
  Models/                38 modèles Eloquent (noms en français)
  Notifications/         AbsenceSignalee, BulletinPublie, DevoirPublie, AnnoncePubliee, EcheancePaiement
  Policies/              Eleve, Classe, Evaluation, Bulletin, Paiement, Cours, Devoir
  Services/              règles métier (voir § 3)
  Support/               Autorisations (matrice des droits), Navigation (menu selon les droits), ExportCsv
resources/views/         une vue par écran, composants dans components/, PDF dans pedagogie/bulletins et finance/paiements
routes/web.php           187 routes, groupées par module ; middleware auth + permission
routes/console.php       commandes et planification
database/migrations      17 migrations ; seeders : Referentiel, RolesEtPermissions, Demonstration
tests/Feature            tests fonctionnels, d'autorisation et de calcul
```

## 3. Règles métier : services

Les contrôleurs valident les entrées et vérifient les droits ; **toute règle métier est dans un service** testable :

| Service | Responsabilité |
|---|---|
| `NumerotationService` | Matricules, reçus, n° de dépense : compteur lu avec `SELECT … FOR UPDATE` dans la transaction de l'appelant (ni trou ni doublon) |
| `JournalService` | Journal d'audit en ajout seul (qui, quoi, quand, avant/après, IP) |
| `EleveService`, `InscriptionService` | Dossiers, responsables, comptes famille, inscriptions, changements de classe, départs |
| `FraisService`, `SoldeService`, `PaiementService`, `RemiseService`, `DepenseService` | Grilles et échéanciers, soldes calculés, encaissements transactionnels avec imputation, annulations |
| `AffectationService`, `EmploiDuTempsService` | Coefficients et titulaires, détection des chevauchements (classe, enseignant, salle) |
| `AppelService`, `JustificatifService` | Appel par demi-journée, délais de modification, absences excusées, totaux d'assiduité |
| `EvaluationService`, `MoyenneService`, `BulletinService` | Saisie et validation des notes, **calcul centralisé des moyennes et rangs**, publication figée et versionnée |
| `ApprentissageService` | Cours, ressources, devoirs, dépôts, corrections |
| `SuiviEleveService` | Données visibles par les familles (uniquement le publié) |
| `AnnonceService`, `RappelEcheanceService`, `TableauDeBordService` | Annonces ciblées, rappels, tableaux de bord par rôle |

## 4. Sécurité

- **Authentification** : sessions régénérées à la connexion, CSRF sur tous les formulaires, 5 tentatives par minute (identifiant + IP) puis blocage et journalisation, comptes désactivables (middleware `CompteActif`), mot de passe temporaire à changer.
- **Autorisations à deux niveaux** : permission sur la route (`permission:…`) **et** Policy sur la donnée (affectations de l'enseignant, lien parent–enfant, publication). L'interface masque ce qui n'est pas permis, mais ce n'est jamais la seule protection.
- **Validation serveur** de toutes les entrées (Form Requests et règles dans les services) ; Eloquent / requêtes paramétrées (pas de SQL concaténé avec des données utilisateur).
- **Fichiers privés** (photos, pièces du dossier, justificatifs, ressources, copies, pièces de dépense) sur le disque `local` (`storage/app/private`), **hors du dossier public**, servis uniquement par des routes contrôlées. Types et tailles validés (taille maximale paramétrable).
- **Intégrité financière** : paiements et dépenses non modifiables ni supprimables (exceptions levées dans les modèles), annulation motivée et journalisée, transactions SQL.
- **Traçabilité** : journal d'audit (consultation par l'administration et la direction) pour les connexions échouées répétées, comptes et rôles, notes validées, déverrouillées ou modifiées, publications, paiements, remises, annulations, modifications d'appels, rattachements parent–élève, paramètres.
- **Secrets** : uniquement dans `.env`, exclu du dépôt ; `.env.example` sans secret.

## 5. Stratégie des valeurs calculées et figées

| Donnée | Stratégie |
|---|---|
| Soldes, retards de paiement | **Toujours calculés** à partir des frais dus, remises actives et paiements valides (`SoldeService`) |
| Moyennes et rangs avant publication | **Calculés à la demande** (`MoyenneService`) |
| Bulletin publié | **Instantané figé** (table `bulletins`, JSON des lignes et de l'assiduité), **versionné** : une correction impose retrait de publication, puis republication avec historique |
| Frais facturés à un élève | **Copie** de la grille au moment de l'attribution : modifier la grille ne change pas les factures existantes |

## 6. Tâches planifiées

| Commande | Fréquence | Rôle |
|---|---|---|
| `finances:rappels` | chaque jour à 7 h | Rappels d'échéances aux parents payeurs (une seule fois par échéance) |
| `annonces:diffuser` | toutes les 15 minutes | Notifications des annonces programmées |
| `ecole:sauvegarder` | chaque nuit à 2 h | Sauvegarde base + fichiers privés, rotation 30 jours |

Elles ne s'exécutent que si `php artisan schedule:run` est lancé chaque minute (voir [`installation-xampp.md`](installation-xampp.md) et [`deploiement.md`](deploiement.md)).

## 7. Points d'extension prévus

- **Paiement en ligne** (FedaPay, Mobile Money) : interface `App\Contracts\PasserellePaiement` ; le webhook appellera `PaiementService::encaisser()` (même reçu, même imputation, même journal). Rien n'est branché ni simulé.
- **SMS / e-mail** : ajouter un canal aux classes de `app/Notifications` (méthode `via`).
- **Quiz et évaluations en ligne** : nouveau type de ressource, détaillé au § 8.
- **Autres fuseaux horaires** : `APP_TIMEZONE` dans `.env`.

## 8. Détail des extensions

### Quiz et évaluations en ligne (plateforme d'apprentissage)
Les ressources d'un cours sont typées (`App\Enums\TypeRessource` : chapitre, document, exercice, lien). Ajouter les quiz se fera sans toucher à l'existant :
1. ajouter le cas `Quiz` à l'énumération ;
2. créer les tables `quiz_questions` (ressource_id, énoncé, type, points), `quiz_choix` (question_id, libellé, est_correct) et `quiz_tentatives` (ressource_id, inscription_id, score, réponses JSON, terminée_at), avec une contrainte d'unicité sur la tentative si une seule est autorisée ;
3. un service `QuizService` (correction automatique) et des vues dédiées ; l'accès réutilise `CoursPolicy` (élève de la classe, cours publié).
Une évaluation en ligne notée pour le bulletin pourra créer une `Evaluation` et des `Note` à partir des tentatives, en passant par `EvaluationService` (même validation, même historique).

### Paiement en ligne
Voir l'interface `App\Contracts\PasserellePaiement` : un prestataire (ex. FedaPay) s'y branche et appelle `PaiementService::encaisser()` après vérification de sa notification ; rien n'est simulé tant qu'aucun prestataire n'est configuré.
