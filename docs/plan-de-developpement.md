# Plan de développement

> Version 0.1 — 12/10/2026

## 1. Étapes

| # | Étape | Contenu | Livrable / critère de fin | État |
|---|---|---|---|---|
| 1 | Inspection et conception | Analyse de l'existant, documents `docs/`, bascule `.env` vers MySQL | Documents relus | **Fait** |
| 2 | Validation | Réponses aux questions ci-dessous, périmètre MVP figé | Décisions consignées dans ce fichier | **Fait** (12/10/2026) |
| 3 | Modèle de données | Migrations, modèles, relations, enums, factories, seeders de référence | `migrate:fresh --seed` OK sur MySQL ; tests de contraintes | **Fait** (MySQL et SQLite) |
| 4 | Authentification et rôles | Authentification maison (Blade + Alpine, connexion par e-mail ou téléphone), spatie/permission, rôles/permissions, middleware compte actif, changement de mot de passe obligatoire, limitation des tentatives, journal | Tests d'accès par rôle | **Fait** (15/10/2026) |
| 5a | Administration | Établissement, années, périodes, niveaux, classes, utilisateurs | CRUD testés | **Fait** (15/10/2026) — les classes sont gérées avec la scolarité |
| 5b | Scolarité | Classes, élèves, responsables et comptes parents, pièces du dossier (stockage privé), inscriptions et réinscriptions (dont en lot), changements de classe, départs, génération des frais dus | Tests RG-ELV | **Fait** (17/10/2026) — 68 tests OK |
| 5c | Pédagogie | Enseignants, matières, affectations, évaluations, saisie des notes, validation | Tests RG-NOT / Policies | **Fait** (17/10/2026) — enseignants, affectations, emplois du temps, évaluations, saisie et validation des notes, déverrouillage historisé |
| 5d | Bulletins | Calcul des moyennes et rangs, publication, PDF | Test de calcul sur jeu d'essai manuel | **Fait** (17/10/2026) — moyennes/rangs/mentions (jeu d'essai), appréciations, publication figée, dépublication et republication versionnée, moyenne annuelle, PDF, notifications ; 118 tests OK |
| 5e | Vie scolaire | Appel, justificatifs, statistiques | Tests anti-doublon | **Fait** (17/10/2026) — appel par demi-journée, justificatifs, notifications des parents, totaux d'assiduité ; 109 tests OK |
| 5f | Finance | Grilles, tranches, frais dus, remises, paiements, reçus PDF, annulations, rapports | Tests de solde, numérotation concurrente, annulation | **Fait** (17/10/2026) — types et grilles de frais avec échéancier, frais facultatifs, remises, encaissement avec imputation, reçus PDF numérotés, annulations, soldes et retards, dépenses, rapports et exports ; 134 tests OK |
| 5g | Espace parents | Tableau de bord parent, notes publiées, présences, paiements | Tests d'isolement entre familles | **Fait** (17/10/2026) — espace famille (enfants, résultats publiés, absences, frais et reçus, emploi du temps) et espace élève (connexion par matricule) ; 141 tests OK. Cours, devoirs et annonces : étapes 8 et 9 |
| 5h | Apprentissage | Cours, chapitres, ressources (fichiers privés), exercices, devoirs, dépôt des travaux, correction | Tests d'accès aux copies | **Fait** (17/10/2026) — 149 tests OK |
| 5i | Pilotage et notifications | Annonces ciblées et programmées, tableaux de bord par rôle, rappels d'échéances (tâche planifiée), statistiques scolaires | Tests de ciblage et d'absence de doublon | **Fait** (17/10/2026) — 157 tests OK |
| 6 | Interface | Système de design (composants Blade), tableau de bord direction, responsive | Revue visuelle sur mobile et bureau | **Fait** — composants Blade communs, tableaux de bord par rôle, mise en page adaptée au mobile |
| 7 | Recette | Tests complets, revue des permissions, corrections | Suite de tests verte | **Fait** (17/10/2026) — 164 tests, 0 échec ; parcours de chaque rôle sur données de démonstration ; vérifications MySQL, Apache, PDF, sauvegarde et restauration (voir docs/tests.md) |
| 8 | Livraison | Données de démonstration, guide d'installation, sauvegarde/restauration | Installation propre réussie depuis la doc | **Fait** (17/10/2026) — `ecole:demo`, `ecole:sauvegarder`, docs/installation-xampp.md, docs/deploiement.md, UML (docs/uml) |

Les étapes 5 et 6 sont menées ensemble module par module (back + écrans + tests) pour avoir à chaque fois une fonctionnalité démontrable.

## 2. Prérequis techniques immédiats (poste de développement)
1. **Redémarrer MySQL** dans le panneau XAMPP : la première migration est restée bloquée par un verrou interne InnoDB.
2. Activer `extension=gd` et `extension=intl` dans `C:\xampp\php\php.ini`.
3. `npm install`.

## 3. Décisions à valider {#decisions-a-valider}

| # | Sujet | Question | Proposition par défaut |
|---|---|---|---|
| Q1 | Rôles | La liste Administrateur / Direction / Secrétariat / Comptable / Enseignant / Surveillant / Parent convient-elle ? | Oui, rôles cumulables |
| Q2 | Notation | Notes sur 20 ? Trimestres ou semestres ? Types d'évaluations et leurs poids (ex. composition = 2) ? Absence à une évaluation : ignorée ou 0 ? Calcul de la moyenne annuelle ? Mentions/tableau d'honneur ? | Sur 20, trimestres, poids 1 partout, absence ignorée, mentions standard |
| Q3 | Coefficients | Définis par classe, par niveau ou par série (lycée) ? | Par classe, pré-remplis depuis un modèle par niveau |
| Q4 | Appel | Par journée, par demi-journée ou par séance de cours ? Qui fait l'appel (enseignant ou surveillant) ? | Demi-journée, par la vie scolaire (enseignants autorisés) |
| Q5 | Frais | Monnaie (FCFA ?) ; frais différents selon catégorie (nouveau/ancien élève, affecté/non affecté par l'État…) ? Remises/bourses ? Accepter les paiements supérieurs au reste dû ? Modes de paiement ? | FCFA entiers ; catégorie facultative ; remises avec motif ; pas d'avance ; espèces/Mobile Money/virement/chèque |
| Q6 | Matricule | Existe-t-il un matricule national à reprendre, ou on génère le nôtre ? | Matricule interne `AAAA-NNNNN` + champ facultatif « matricule national » |
| Q7 | Parents | Connexion par e-mail ou par numéro de téléphone (beaucoup de parents sans e-mail) ? Réinitialisation du mot de passe : par l'établissement ou par e-mail ? | Téléphone **ou** e-mail ; réinitialisation par l'établissement au MVP |
| Q8 | Dépendances | Accord pour installer Laravel Breeze (Blade + Alpine.js), spatie/laravel-permission, barryvdh/laravel-dompdf ? | Oui |
| Q9 | Hébergement | Pour la démo et le premier établissement : serveur local ou VPS ? Les parents doivent-ils accéder depuis Internet dès le MVP ? | Démo en local (XAMPP) ; architecture prête pour un VPS |
| Q10 | Bulletin | Avez-vous un modèle de bulletin officiel à reproduire (mentions obligatoires, signature, cachet) ? | Modèle standard avec logo, moyennes, rang, appréciation, absences |
| Q11 | Périmètre | Le périmètre P1 du cahier des charges est-il acceptable, notamment le report de l'emploi du temps, des SMS et du paiement en ligne ? | Oui |
| Q12 | Données | Les bases d'établissements contiendront des données de mineurs : y a-t-il une réglementation locale (déclaration à l'autorité de protection des données, durée de conservation) à respecter ? | À vérifier par GKAS |

### Décisions validées (12/10/2026)
Le responsable du projet a validé **l'ensemble des propositions par défaut Q1 à Q11** ci-dessus. Elles deviennent les règles de référence jusqu'à nouvel ordre. Q12 (réglementation des données personnelles) reste à vérifier par GKAS et ne bloque pas le développement.

## 4. Risques
| Risque | Mitigation |
|---|---|
| Règles de calcul différentes d'un établissement à l'autre | Paramètres de calcul dans `etablissement.parametres` ; tests de calcul |
| Instabilité de MariaDB XAMPP (constatée) | Documenter la réparation ; recommander MySQL 8 / MariaDB récent en production |
| Périmètre MVP trop large | Livraison module par module, démontrable à chaque étape |
| Saisie massive initiale (élèves) | Import CSV en priorité P2 haute |
