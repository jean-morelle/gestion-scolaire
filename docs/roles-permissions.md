# Rôles et permissions

> Fichier généré par `php artisan ecole:doc-permissions` à partir de `app/Support/Autorisations.php` (source unique, utilisée aussi par le seeder). Ne pas modifier à la main.

Les rôles sont **cumulables** : un utilisateur dispose de l'union des permissions de ses rôles. Les permissions protègent les routes (middleware `permission:`) ; les **Policies** ajoutent les contrôles propres à chaque donnée (affectations de l'enseignant, lien parent–enfant, publication…). Masquer un bouton n'est jamais la seule protection.

## Matrice

| Permission | Description | Administrateur technique | Directeur | Secrétaire | Censeur / Surveillant | Enseignant | Comptable / Caissier | Parent | Élève |
|---|---|---|---|---|---|---|---|---|---|
| `etablissement.gerer` | Paramétrer l'établissement, les années, périodes, niveaux et matières | ✔ |  |  |  |  |  |  |  |
| `utilisateurs.gerer` | Gérer les comptes du personnel et leurs rôles | ✔ |  |  |  |  |  |  |  |
| `journal.consulter` | Consulter le journal d'audit | ✔ | ✔ |  |  |  |  |  |  |
| `classes.gerer` | Créer et modifier les classes | ✔ | ✔ |  |  |  |  |  |  |
| `eleves.consulter` | Consulter les dossiers des élèves |  | ✔ | ✔ | ✔ |  | ✔ |  |  |
| `eleves.gerer` | Créer et modifier les élèves, responsables et pièces du dossier |  |  | ✔ |  |  |  |  |  |
| `inscriptions.gerer` | Inscrire, réinscrire, changer de classe, enregistrer un départ |  |  | ✔ |  |  |  |  |  |
| `enseignants.gerer` | Gérer les fiches enseignants |  | ✔ |  |  |  |  |  |  |
| `affectations.gerer` | Affecter les enseignants et fixer les coefficients |  | ✔ |  |  |  |  |  |  |
| `emplois_du_temps.gerer` | Construire les emplois du temps |  | ✔ |  | ✔ |  |  |  |  |
| `presences.saisir` | Faire l'appel de toutes les classes |  |  |  | ✔ |  |  |  |  |
| `presences.saisir_ses_classes` | Faire l'appel des classes affectées |  |  |  |  | ✔ |  |  |  |
| `presences.justifier` | Justifier les absences |  | ✔ |  | ✔ |  |  |  |  |
| `presences.consulter` | Consulter les présences de toutes les classes |  | ✔ | ✔ | ✔ |  |  |  |  |
| `notes.saisir` | Créer des évaluations et saisir les notes de ses affectations |  |  |  |  | ✔ |  |  |  |
| `notes.consulter` | Consulter toutes les notes |  | ✔ |  | ✔ |  |  |  |  |
| `evaluations.deverrouiller` | Déverrouiller une évaluation validée |  | ✔ |  |  |  |  |  |  |
| `bulletins.consulter` | Consulter et imprimer les bulletins |  | ✔ | ✔ | ✔ |  |  |  |  |
| `bulletins.publier` | Publier, déverrouiller et republier les bulletins |  | ✔ |  |  |  |  |  |  |
| `cours.gerer` | Publier des cours, ressources et devoirs pour ses affectations |  |  |  |  | ✔ |  |  |  |
| `finances.consulter` | Consulter les frais, paiements et soldes |  | ✔ |  |  |  | ✔ |  |  |
| `frais.configurer` | Configurer les frais, grilles et échéances |  |  |  |  |  | ✔ |  |  |
| `paiements.enregistrer` | Encaisser les paiements et éditer les reçus |  |  |  |  |  | ✔ |  |  |
| `paiements.annuler` | Annuler un paiement |  | ✔ |  |  |  |  |  |  |
| `remises.accorder` | Accorder ou annuler une remise |  | ✔ |  |  |  |  |  |  |
| `depenses.gerer` | Enregistrer les dépenses |  |  |  |  |  | ✔ |  |  |
| `depenses.annuler` | Annuler une dépense |  | ✔ |  |  |  |  |  |  |
| `rapports.financiers` | Consulter et exporter les rapports financiers |  | ✔ |  |  |  | ✔ |  |  |
| `rapports.scolaires` | Consulter et exporter les statistiques scolaires |  | ✔ |  | ✔ |  |  |  |  |
| `annonces.gerer` | Publier des annonces | ✔ | ✔ |  | ✔ |  |  |  |  |

## Accès accordés par les Policies (sans permission)

| Rôle | Accès | Contrôle |
|---|---|---|
| Parent | Ses enfants : résumé, résultats et bulletins **publiés**, absences, frais, paiements et reçus, emploi du temps ; photo de l'enfant ; annonces qui le concernent | `ElevePolicy::suivre` / `suivreFinances`, `BulletinPolicy`, `PaiementPolicy` — lien responsable–élève vérifié à chaque requête |
| Élève | Son espace : résultats et bulletins publiés, absences, emploi du temps, cours publiés et devoirs de sa classe, **sa** copie uniquement | `ElevePolicy::suivre`, `CoursPolicy`, `DevoirPolicy::voirRemise` |
| Enseignant | Ses affectations : évaluations et notes, cours et devoirs, copies ; liste des élèves (sans coordonnées des familles) et emploi du temps des classes où il intervient ; appel de ces classes ; appréciations s'il est professeur principal | `EvaluationPolicy`, `CoursPolicy`, `DevoirPolicy`, `ClassePolicy` |

## Principes

- L'**administrateur technique** configure l'application et les comptes ; il n'a aucun accès aux notes ni aux opérations financières.
- Les opérations sensibles sont réservées à la **direction** : déverrouiller une évaluation, publier ou retirer des bulletins, annuler un paiement ou une dépense, accorder une remise.
- Les **comptes parents et élèves** sont créés depuis les fiches (secrétariat), jamais par auto-inscription ; mot de passe temporaire à changer à la première connexion.
- Toutes les actions sensibles sont inscrites au **journal d'audit** (ajout seul).
