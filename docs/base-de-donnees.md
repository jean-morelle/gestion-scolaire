# Base de données

MySQL 8 / MariaDB ≥ 10.4, moteur InnoDB, `utf8mb4_unicode_ci`. Créée exclusivement par les **migrations** (`database/migrations`, 17 fichiers) : `php artisan migrate`.

| Document | Contenu |
|---|---|
| [`uml/06-modele-conceptuel.md`](uml/06-modele-conceptuel.md) | Modèle conceptuel (entités, associations, cardinalités) |
| [`uml/07-modele-logique.md`](uml/07-modele-logique.md) | Modèle logique (tables, clés, unicités, règles de suppression) |
| [`uml/08-schema-relationnel.md`](uml/08-schema-relationnel.md) | **Schéma relationnel final généré depuis la base réelle** : 48 tables métier, 83 clés étrangères, types exacts |

Pour régénérer le schéma après une nouvelle migration : `php artisan ecole:doc-base` (connexion MySQL).

## Domaines

| Domaine | Tables |
|---|---|
| Administration | `etablissement`, `users`, `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`, `annees_scolaires`, `periodes`, `sequences`, `journal_activites`, `notifications`, `annonces` |
| Scolarité | `niveaux`, `classes`, `eleves`, `responsables`, `eleve_responsable`, `inscriptions`, `changements_classe`, `documents_eleves` |
| Pédagogie | `enseignants`, `matieres`, `enseignant_matiere`, `affectations`, `emplois_du_temps`, `evaluations`, `notes`, `appreciations`, `publications`, `bulletins` |
| Vie scolaire | `appels`, `presences`, `justificatifs`, `justificatif_presence` |
| Finances | `types_frais`, `grilles_frais`, `tranches`, `frais_eleves`, `remises`, `paiements`, `imputations`, `categories_depenses`, `depenses` |
| Apprentissage | `cours`, `ressources_pedagogiques`, `devoirs`, `remises_devoirs` |
| Techniques (Laravel) | `migrations`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `password_reset_tokens` |

Correspondance avec les entités suggérées par le cahier des charges : *Parent* → `responsables` (+ `eleve_responsable`) ; *PeriodeScolaire* → `periodes` ; *AffectationEnseignant* → `affectations` ; *FraisScolarite* → `grilles_frais` (tarif) et `frais_eleves` (facturé) ; *Echeance* → `tranches` (et une ligne de `frais_eleves` par échéance) ; *JournalAudit* → `journal_activites` ; *Notification* → `notifications`.

## Contraintes d'intégrité essentielles

| Règle | Mise en œuvre |
|---|---|
| Une inscription par élève et par année | `UNIQUE (eleve_id, annee_scolaire_id)` |
| Matricule, reçu, n° de dépense uniques et sans trou | `UNIQUE` + table `sequences` lue en `SELECT … FOR UPDATE` dans la transaction |
| Une note par élève et par évaluation | `UNIQUE (evaluation_id, inscription_id)` |
| Un appel par classe, date et demi-journée ; une présence par élève et par appel | `UNIQUE (classe_id, date_appel, creneau)`, `UNIQUE (appel_id, inscription_id)` |
| Un titulaire et un coefficient par matière dans une classe | `UNIQUE (classe_id, matiere_id)` |
| Un bulletin par élève et par période ; une publication par classe et par période | `UNIQUE (inscription_id, periode_id)`, `UNIQUE (classe_id, periode_id)` |
| Une remise de devoir par élève | `UNIQUE (devoir_id, inscription_id)` |
| Historique non supprimable | `ON DELETE RESTRICT` sur inscriptions, notes, paiements, imputations… ; paiements, remises, dépenses et journal protégés aussi dans les modèles |

## Types

- **Montants** : `BIGINT UNSIGNED` en unité monétaire entière (FCFA) — jamais de flottant.
- **Notes, barèmes, coefficients, poids** : `DECIMAL(5,2)` / `DECIMAL(4,2)` ; moyennes figées : `DECIMAL(6,3)`.
- **Dates** : `DATE` (échéances, naissances, appels) ; `TIMESTAMP` pour les horodatages.
- **Mots de passe** : hachés (bcrypt, `BCRYPT_ROUNDS=12`), jamais en clair.

## Données calculées ou figées

Les soldes et les moyennes ne sont **pas stockés** : ils sont recalculés à partir des données sources (`SoldeService`, `MoyenneService`). Seul le **bulletin publié** est un instantané figé et versionné (table `bulletins`), pour que le document remis aux familles ne change pas sans republication tracée. Voir [`architecture.md`](architecture.md) § 5.

## Seeders

| Seeder | Usage |
|---|---|
| `ReferentielSeeder` | Établissement, niveaux, matières, types de frais, catégories de dépenses (idempotent) |
| `RolesEtPermissionsSeeder` | 8 rôles et 30 permissions depuis `App\Support\Autorisations` (idempotent) |
| `DemonstrationSeeder` | Données **fictives** ; refuse de s'exécuter si le nom de la base ne contient pas « demo » (`php artisan ecole:demo`) |

Les factories (`database/factories`) servent aux tests.
