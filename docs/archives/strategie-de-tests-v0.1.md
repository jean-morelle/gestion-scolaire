# Stratégie de tests

> Version 0.1 — 12/10/2026

## 1. Outils
- **PHPUnit 11** (déjà présent), lancé par `php artisan test`.
- Base de test par défaut : **SQLite en mémoire** (`phpunit.xml`) → rapide pour la majorité des tests.
- **[PROPOSÉ]** Base MySQL dédiée `gestion-ecole-test` pour les tests marqués `@group mysql` : verrous (`lockForUpdate`), contraintes et comportements propres à MySQL/MariaDB (numérotation concurrente des reçus).
- Factories pour toutes les entités ; un seeder de démonstration distinct des seeders de référence.

État au 12/10/2026 : 2 tests d'exemple Laravel, **2/2 passent** (exécutés).

## 2. Niveaux de tests

| Niveau | Cible | Exemples |
|---|---|---|
| **Unitaire** | Services de calcul purs | moyenne de matière avec barèmes différents, poids, absences ; moyenne générale avec coefficients ; rangs ex aequo ; arrondis ; solde |
| **Fonctionnel (Feature)** | Requêtes HTTP complètes | création d'élève, inscription, appel, saisie et validation de notes, paiement, annulation, publication, PDF généré (statut 200 + type `application/pdf`) |
| **Autorisations** | Policies et middleware | chaque route sensible testée pour chaque rôle non autorisé → 403 ; parent accédant à l'enfant d'une autre famille → 403/404 |
| **Intégrité** | Contraintes | double inscription la même année refusée ; double appel → mise à jour ; double note impossible ; reçus uniques |
| **Validation** | Form Requests | notes hors barème, montants négatifs, dates hors période, fichiers trop lourds / mauvais type |

## 3. Scénarios critiques (doivent toujours passer)

### Notes
- T-NOT-01 Calcul d'une moyenne sur un jeu d'essai calculé à la main (3 matières, coefficients 2/3/1, barèmes 10 et 20, un absent).
- T-NOT-02 Un enseignant ne peut pas créer d'évaluation dans une classe qui ne lui est pas affectée.
- T-NOT-03 Modification d'une note validée refusée ; déverrouillage par la direction journalisé.
- T-NOT-04 Un parent ne voit pas les notes d'une période non publiée.

### Présences
- T-PRE-01 Refaire l'appel d'un même créneau ne crée pas de doublon.
- T-PRE-02 Justification d'une absence → statut `absent_excuse`, totaux mis à jour.
- T-PRE-03 Appel refusé dans une période clôturée ou à une date future.

### Finance
- T-FIN-01 Génération des frais dus à l'inscription conforme à la grille et aux tranches.
- T-FIN-02 Paiement partiel → imputations sur la tranche la plus ancienne → solde correct.
- T-FIN-03 Paiement supérieur au reste dû refusé (selon Q5).
- T-FIN-04 Annulation : motif obligatoire, droit requis, solde recalculé, paiement toujours listé.
- T-FIN-05 Numéros de reçu séquentiels et uniques, y compris en cas d'échec de transaction (rollback).
- T-FIN-06 Aucun endpoint ne permet de modifier ou supprimer un paiement.

### Sécurité
- T-SEC-01 Compte désactivé → connexion refusée.
- T-SEC-02 Limitation des tentatives de connexion.
- T-SEC-03 Isolement parents : parent A ne peut accéder à aucune donnée de l'élève de la famille B (fiche, notes, bulletin PDF, reçus, justificatifs).
- T-SEC-04 Toutes les routes internes exigent l'authentification.
- T-SEC-05 Les fichiers privés (photos, justificatifs) ne sont pas accessibles par URL publique.

## 4. Règles de travail
- Chaque fonctionnalité livrée l'est avec ses tests Feature et d'autorisation.
- La suite complète est exécutée avant de déclarer une étape terminée ; le résultat réel est rapporté.
- Tout défaut corrigé reçoit un test de non-régression.
- Recette manuelle finale avec les comptes de démonstration de chaque rôle, sur ordinateur et téléphone.
