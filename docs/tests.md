# Tests : procédures et résultats

> La stratégie initiale (12/10/2026) est conservée dans [`archives/strategie-de-tests-v0.1.md`](archives/strategie-de-tests-v0.1.md).

## 1. Lancer les tests

```bash
php artisan test                                  # suite complète
php artisan test tests/Feature/Finance            # un dossier
php artisan test --filter=test_les_numeros_de_recu # un test
```

- Base de test : **SQLite en mémoire**, recréée à chaque test (`phpunit.xml`, trait `RefreshDatabase`). Les tests **ne touchent jamais** à la base MySQL de travail ni à la base de démonstration.
- `phpunit.xml` fixe `APP_URL=http://localhost` : les tests restent indépendants de l'adresse locale (sous-dossier XAMPP).
- Les fichiers téléversés utilisent un disque factice (`Storage::fake`).
- Aucun test ne dépend de l'extension GD.

## 2. Couverture des tests exigés (cahier des charges § 13)

| Exigence | Tests (`tests/Feature/…`) |
|---|---|
| Connexion et déconnexion | `Auth/AuthentificationTest` : e-mail, téléphone, matricule élève (`ValidationFinaleTest`, `EspacesFamilleEtEleveTest`), mauvais mot de passe, compte désactivé, limitation des tentatives, mot de passe temporaire |
| Rôles et permissions | Un test « droits d'accès » par module (Administration, Élèves, Classes et inscriptions, Enseignants, Emplois du temps, Évaluations, Appel, Bulletins, Finances, Cours et devoirs, Annonces, Statistiques) ; `ValidationFinaleTest` : chaque rôle ouvre chaque lien de son menu |
| Création et modification des élèves | `Scolarite/ElevesEtResponsablesTest` |
| Inscriptions et réinscriptions | `Scolarite/InscriptionsEtClassesTest` (une inscription par an, réinscription détectée, en lot, changement de classe, départ, capacité) |
| Calcul des moyennes | `Pedagogie/CalculDesMoyennesTest` : **jeu d'essai calculé à la main** (coefficients, poids, barèmes 10/20/40, absences ignorées ou à 0, rangs ex æquo, élève non classé, notes d'une ancienne classe) ; `ValidationFinaleTest` : bulletins figés = calcul en direct |
| Limites des barèmes | `Pedagogie/EvaluationsEtNotesTest::test_limites_du_bareme` (au-dessus du barème, négatif, texte, 3 décimales) |
| Publication des bulletins | `Pedagogie/BulletinsTest` (figé, brouillons, retrait et republication versionnée, moyenne annuelle, appréciations, PDF) |
| Enregistrement des paiements | `Finance/PaiementsEtSoldesTest` (imputation la plus ancienne d'abord, imputation manuelle, pas de dépassement du reste dû) |
| Calcul des soldes | `Finance/PaiementsEtSoldesTest`, `Finance/FraisDepensesEtRapportsTest`, `ValidationFinaleTest` (soldes jamais négatifs, aucune ligne sur-imputée) |
| Corrections et annulations financières | Annulation de paiement (motif, droit, reçu « ANNULÉ »), remises, dépenses ; `ModeleDonnees/ProtectionHistoriqueTest` (ni modification ni suppression) |
| Accès des parents à leurs enfants | `Famille/EspacesFamilleEtEleveTest` (5 onglets refusés pour l'enfant d'une autre famille, rien avant publication), `BulletinsTest`, `PaiementsEtSoldesTest` (reçus) |
| Restrictions des enseignants | `EvaluationsEtNotesTest`, `EnseignantsEtAffectationsTest`, `CoursEtDevoirsTest`, `AppelEtJustificatifsTest` (hors affectation → 403) |
| Remise et correction des devoirs | `Pedagogie/CoursEtDevoirsTest` (dépôt, remplacement, retard, correction, verrouillage) |
| Protection des documents privés | Pièces du dossier, photos, justificatifs, ressources, copies d'élèves (un élève ne voit jamais la copie d'un autre), pièces de dépenses |

Tests de non-régression des règles critiques : numérotation des reçus sans trou après un échec, unicités en base (`ContraintesIntegriteTest`), rappels d'échéance sans doublon, isolement des familles.

## 3. Résultats

Exécution complète du **17/10/2026** (`php artisan test`, PHP 8.2.12, SQLite en mémoire) : **164 tests, 1 434 assertions, 0 échec** (durée ≈ 2 minutes).

| Fichier | Tests | Assertions |
|---|---:|---:|
| Admin/AdministrationTest | 12 | 63 |
| Auth/AuthentificationTest | 11 | 52 |
| DocumentationTest | 4 | 28 |
| Famille/EspacesFamilleEtEleveTest | 7 | 73 |
| Finance/FraisDepensesEtRapportsTest | 7 | 70 |
| Finance/PaiementsEtSoldesTest | 9 | 93 |
| ModeleDonnees/ContraintesIntegriteTest | 11 | 12 |
| ModeleDonnees/ProtectionHistoriqueTest | 5 | 7 |
| ModeleDonnees/RelationsEtReferentielTest | 2 | 11 |
| Pedagogie/BulletinsTest | 9 | 79 |
| Pedagogie/CalculDesMoyennesTest | 5 | 26 |
| Pedagogie/CoursEtDevoirsTest | 8 | 122 |
| Pedagogie/EmploiDuTempsTest | 8 | 42 |
| Pedagogie/EnseignantsEtAffectationsTest | 9 | 91 |
| Pedagogie/EvaluationsEtNotesTest | 10 | 93 |
| PilotageEtNotificationsTest | 8 | 94 |
| Scolarite/ElevesEtResponsablesTest | 14 | 121 |
| Scolarite/InscriptionsEtClassesTest | 12 | 87 |
| Unit/ExampleTest | 1 | 1 |
| ValidationFinaleTest | 3 | 192 |
| VieScolaire/AppelEtJustificatifsTest | 9 | 77 |

## 4. Vérifications complémentaires (hors PHPUnit), effectuées le 17/10/2026

| Vérification | Résultat |
|---|---|
| Chargement complet des données de démonstration sur **MySQL** (`php artisan ecole:demo`) : inscriptions, appels, paiements avec verrous, publication de bulletins | Réussi ; base de travail intacte |
| Requêtes spécifiques MySQL (agrégats, `DATE_FORMAT`, jointures des rapports et tableaux de bord) | Exécutées sans erreur |
| Sauvegarde (`php artisan ecole:sauvegarder`) puis **restauration** dans une base vierge | Réussi (utilisateurs, migrations, rôles retrouvés) |
| Application servie par **Apache (XAMPP)** sous `/gestion-scolaire/public` : page de connexion, styles compilés, jeton CSRF, message d'erreur de connexion, redirections | Réussi (un défaut de redirection de la racine a été trouvé puis corrigé) |
| Rendu visuel des PDF (bulletin A4, reçu A5 avec mention « ANNULÉ ») | Conforme ; ≈ 25 Ko par document |
| Validation des 20 diagrammes UML par le parseur Mermaid 11 | 20/20 valides |
| Tâches planifiées (`php artisan schedule:list`, exécution manuelle de `finances:rappels` et `annonces:diffuser`) | Réussi |

## 5. Recette manuelle conseillée

Avec la base de démonstration (`php artisan ecole:demo`, comptes dans [`installation-xampp.md`](installation-xampp.md) § 8), sur ordinateur **et** téléphone :

1. **Secrétaire** : créer un élève avec responsable et inscription, ajouter une pièce, créer le compte parent.
2. **Enseignant de maths** : faire l'appel de la 6e A, créer une évaluation, saisir et valider les notes, publier un devoir, corriger une copie.
3. **Directeur** : publier les bulletins de la 6e B (une composition en brouillon : tester le refus puis « publier quand même »), retirer et republier, consulter les rapports.
4. **Comptable** : encaisser un paiement partiel, imprimer le reçu ; **directeur** : l'annuler.
5. **Parent** (`90000001`) : vérifier qu'il ne voit que son enfant, ses résultats publiés, ses absences et ses reçus.
6. **Élève** : consulter ses cours, déposer le devoir.
