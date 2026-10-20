# Cahier des charges — Application de gestion scolaire (GKAS GROUP SARL U)

> Version : 0.1 (brouillon soumis à validation) — 12/10/2026
>
> Légende utilisée dans tout le dossier :
> - **[CONFIRMÉ]** : besoin exprimé explicitement par le client / le responsable du projet.
> - **[PROPOSÉ]** : proposition de l'équipe technique, **à valider**.
> - **[QUESTION]** : information manquante, à trancher avant l'implémentation concernée.

---

## 1. Contexte et objectifs

GKAS GROUP SARL U souhaite proposer aux collèges et lycées une application web pour améliorer leur gestion quotidienne. **[CONFIRMÉ]**

Objectifs **[CONFIRMÉ]** :
1. Suivre les présences et absences des élèves.
2. Gérer les notes et permettre aux parents de consulter les résultats.
3. Gérer les frais de scolarité et les paiements.
4. Administrer les élèves, classes, enseignants et utilisateurs.

Livrable attendu : un **MVP professionnel et fonctionnel**, présentable à un gestionnaire, avec des données fictives. **[CONFIRMÉ]**

### 1.1 Modèle de distribution

- **Pas de SaaS, pas de multi-tenant.** Une installation et une base de données par établissement. **[CONFIRMÉ]**
- Aucun abonnement, aucune facturation des établissements dans l'application, aucun mécanisme de « tenant ». **[CONFIRMÉ]**
- Conséquence technique **[PROPOSÉ]** : l'application gère **un seul établissement** ; ses paramètres (nom, logo, adresse, devise…) sont stockés dans une table unique de paramètres. Aucune colonne `etablissement_id` dans les tables métier.

---

## 2. Utilisateurs et responsabilités

Rôles proposés **[PROPOSÉ]** — la liste exacte est à valider **[QUESTION Q1]** :

| Rôle | Responsabilités principales |
|---|---|
| **Administrateur** | Paramètres de l'établissement, années scolaires, comptes utilisateurs, rôles, journal d'activité. Accès complet. |
| **Direction** (chef d'établissement, censeur, proviseur) | Consultation globale, tableaux de bord, validation et publication des résultats, autorisation des annulations de paiement. |
| **Secrétariat / Scolarité** | Dossiers élèves, responsables légaux, inscriptions et réinscriptions, affectation aux classes. |
| **Comptable / Économe** | Grilles de frais, encaissements, reçus, situations financières, rapports financiers. |
| **Enseignant** | Saisie des notes de ses classes/matières ; appel de ses classes si l'appel se fait par séance. |
| **Surveillant / Vie scolaire** | Appel journalier, saisie des retards, justification des absences. |
| **Parent / Responsable légal** | Consultation, en lecture seule, des notes publiées, présences et paiements de **ses** enfants uniquement. |

Un utilisateur peut cumuler plusieurs rôles (ex. un enseignant également censeur). **[PROPOSÉ]**

---

## 3. Périmètre fonctionnel

### 3.1 Priorisation

| Priorité | Signification |
|---|---|
| **P1 – MVP** | Indispensable à la démonstration et à un usage réel minimal. |
| **P2 – V1.1** | Utile, prévu juste après le MVP. |
| **P3 – Ultérieur** | Hors périmètre actuel. |

### 3.2 Modules et périmètre proposé **[PROPOSÉ]**

#### A. Administration et utilisateurs
| Fonction | Priorité |
|---|---|
| Paramètres de l'établissement (nom, logo, adresse, contacts, devise, en-tête des documents) | P1 |
| Années scolaires (une seule active à la fois) et périodes (trimestres ou semestres) | P1 |
| Comptes utilisateurs, rôles, permissions, activation/désactivation | P1 |
| Journal des actions sensibles (notes validées, paiements, annulations, droits) | P1 |
| Permissions fines personnalisables depuis l'interface | P2 (rôles prédéfinis au MVP) |

#### B. Élèves et inscriptions
| Fonction | Priorité |
|---|---|
| Dossier élève (identité, photo, contacts), matricule unique | P1 |
| Responsables légaux, liens multiples (un parent ↔ plusieurs enfants) | P1 |
| Inscription / réinscription annuelle, affectation à une classe | P1 |
| Niveaux et classes par année scolaire | P1 |
| Historique scolaire (classes et résultats des années précédentes dans l'application) | P1 (lecture des inscriptions passées) |
| Changement de classe en cours d'année, départ/abandon | P1 |
| Import Excel/CSV massif d'élèves | P2 |
| Passage automatique en classe supérieure (décisions de fin d'année) | P2 |

#### C. Enseignants et matières
| Fonction | Priorité |
|---|---|
| Fiches enseignants (liées à un compte utilisateur) | P1 |
| Matières | P1 |
| Affectation enseignant ↔ classe ↔ matière avec coefficient | P1 |
| Emploi du temps | **P3** — justification : non demandé comme besoin principal, coûteux (contraintes, salles, conflits), et non nécessaire si l'appel se fait par journée. |

#### D. Présences et absences
| Fonction | Priorité |
|---|---|
| Appel d'une classe (par journée **ou** par séance, voir [QUESTION Q4]) | P1 |
| Statuts : présent, absent, retard, absence excusée | P1 |
| Justification d'une absence (motif + pièce jointe facultative) | P1 |
| Historique par élève et par classe, totaux par période | P1 |
| Unicité : un seul enregistrement par élève et par appel | P1 |
| Statistiques de présence (taux par classe, élèves les plus absents) | P1 (simples) |
| Notification SMS/WhatsApp aux parents | P3 |

#### E. Notes et bulletins
| Fonction | Priorité |
|---|---|
| Évaluations (devoir, interrogation, composition…) par classe/matière/période | P1 |
| Saisie des notes en grille, note sur barème converti sur 20 | P1 |
| Calcul des moyennes par matière, moyenne générale pondérée, rang | P1 |
| Règles de calcul configurables (poids des types d'évaluation, arrondis) | P1 (paramètres simples), P2 (avancé) |
| Validation des notes → verrouillage | P1 |
| Publication des résultats d'une période (classe par classe) | P1 |
| Bulletin PDF imprimable | P1 |
| Appréciations du conseil de classe, mentions | P1 (champ libre + mention calculée), P2 (workflow conseil de classe) |
| Moyenne annuelle et décision de passage | P2 |

#### F. Frais de scolarité
| Fonction | Priorité |
|---|---|
| Types de frais (inscription, scolarité, tenue, etc.) | P1 |
| Grille des montants par année scolaire et niveau (et catégorie si confirmé) | P1 |
| Échéancier (tranches avec date limite) | P1 |
| Génération des montants dus à l'inscription ; remises/bourses justifiées | P1 |
| Encaissement complet ou partiel, mode de paiement | P1 |
| Reçu PDF avec référence unique et séquentielle | P1 |
| Solde restant, élèves en retard de paiement | P1 |
| Annulation contrôlée (motif, droit spécifique, jamais de suppression) | P1 |
| Rapports financiers (encaissements par jour/période/mode, restes à recouvrer) | P1 |
| Paiement en ligne / Mobile Money intégré | P3 |
| Comptabilité générale (dépenses, salaires) | P3 |

#### G. Espace parents
| Fonction | Priorité |
|---|---|
| Connexion sécurisée, compte créé par l'établissement | P1 |
| Liste de ses enfants uniquement | P1 |
| Notes **publiées** et bulletins | P1 |
| Présences et absences | P1 |
| Frais, paiements, solde, reçus | P1 |
| Aucune modification possible | P1 |
| Messagerie parent ↔ école | P3 |

#### H. Tableau de bord et rapports
| Fonction | Priorité |
|---|---|
| Indicateurs : effectifs, taux de présence, moyennes, encaissements, restes à recouvrer | P1 |
| Filtres par année scolaire, classe, période | P1 |
| Exports CSV et impressions (listes de classe, états financiers) | P1 |
| Graphiques avancés et comparaisons pluriannuelles | P2 |

### 3.3 Hors périmètre (toutes versions actuellement envisagées)
- Plateforme SaaS, abonnements, multi-établissements dans une même base.
- Application mobile native.
- Cantine, transport, bibliothèque, gestion RH/paie.

---

## 4. Exigences fonctionnelles

Les règles détaillées figurent dans [`regles-metier.md`](regles-metier.md). Principes structurants :

- **EF-01** Toute donnée scolaire (classe, inscription, note, présence, frais) est rattachée à une **année scolaire**.
- **EF-02** Un élève a **au plus une inscription active par année scolaire**.
- **EF-03** Un élève possède un **matricule unique** et non réutilisable.
- **EF-04** Les paiements forment un **historique transactionnel** ; le solde est **calculé**, jamais saisi.
- **EF-05** Une note validée ne peut plus être modifiée sauf déverrouillage tracé par une personne habilitée.
- **EF-06** Un parent n'accède qu'aux élèves qui lui sont rattachés par l'établissement.
- **EF-07** Les opérations sensibles sont journalisées (qui, quoi, quand, valeurs avant/après).

## 5. Exigences non fonctionnelles

| Code | Exigence | Critère |
|---|---|---|
| ENF-01 | Langue | Interface en français par défaut, dates au format JJ/MM/AAAA. |
| ENF-02 | Responsive | Utilisable sur ordinateur, tablette et téléphone (≥ 360 px). |
| ENF-03 | Performance | Pages courantes < 2 s pour un établissement de 2 000 élèves sur serveur modeste. |
| ENF-04 | Disponibilité | Fonctionne sur un serveur local sans Internet (hors espace parents distant). |
| ENF-05 | Sécurité | Voir § 7. |
| ENF-06 | Maintenabilité | Code Laravel standard (MVC, services), tests sur les fonctions critiques, documentation d'installation. |
| ENF-07 | Portabilité | Installation sur XAMPP/Windows, Linux (Apache/Nginx) ou VPS. |
| ENF-08 | Accessibilité | Contrastes suffisants, libellés de formulaires, navigation au clavier. |

---

## 6. Critères d'acceptation (MVP)

1. Un administrateur installe l'application, renseigne l'établissement, crée une année scolaire, ses périodes, niveaux, classes, matières et utilisateurs.
2. Le secrétariat inscrit un élève avec ses responsables ; le matricule est unique ; une seconde inscription du même élève dans la même année est refusée.
3. Un surveillant (ou enseignant) fait l'appel d'une classe ; refaire l'appel du même créneau modifie l'existant au lieu de créer un doublon.
4. Un enseignant ne peut saisir des notes que pour ses classes/matières ; après validation, la modification est refusée côté serveur.
5. Les moyennes et rangs calculés correspondent à un jeu d'essai calculé à la main (test automatisé).
6. La direction publie les résultats d'une période ; le parent les voit ensuite, pas avant.
7. Le comptable enregistre un paiement partiel ; le solde diminue ; un reçu PDF avec numéro unique est généré.
8. L'annulation d'un paiement exige un motif et un droit spécifique ; le paiement reste visible avec le statut « annulé » ; le solde est recalculé.
9. Un parent qui modifie l'identifiant d'un élève dans l'URL reçoit une erreur 403/404.
10. Toutes les actions ci-dessus réservées à un rôle sont refusées côté serveur pour les autres rôles (tests automatisés).
11. Une sauvegarde de la base peut être produite et restaurée selon la procédure documentée.

---

## 7. Contraintes de sécurité, sauvegarde et déploiement

### 7.1 Sécurité **[PROPOSÉ]**
- Authentification Laravel, mots de passe hachés (bcrypt/argon), limitation des tentatives de connexion.
- Autorisations vérifiées côté serveur (Policies, middleware) — l'interface ne fait que refléter les droits.
- Protection CSRF, validation de toutes les entrées (Form Requests), échappement Blade.
- Identifiants des ressources vérifiés par Policy (pas d'accès par simple modification d'URL).
- Fichiers (photos, justificatifs) stockés hors du dossier public, servis via contrôleur autorisé.
- Journal des actions sensibles.
- Aucun secret dans le dépôt (`.env` ignoré par Git).
- Comptes de démonstration avec données fictives uniquement.
- HTTPS obligatoire dès que l'application est exposée sur Internet.

### 7.2 Sauvegarde **[PROPOSÉ]**
- Sauvegarde quotidienne automatisée de la base (`mysqldump`) et du dossier `storage/app` ; rétention 30 jours ; copie hors du serveur (disque externe ou stockage distant).
- Procédure de restauration documentée et testée.

### 7.3 Déploiement **[PROPOSÉ]**
- Une installation par établissement : code + `.env` + base MySQL dédiée.
- Deux scénarios supportés : **serveur local** dans l'établissement (accès parents limité au réseau local, ou exposé via un nom de domaine) ou **serveur dédié / VPS** économique (accès parents depuis Internet).
- Voir [`architecture.md`](architecture.md).

---

## 8. Hypothèses (non validées)

- H1. Notation **sur 20**, périodes en **trimestres** (configurable en semestres).
- H2. Monnaie **sans décimales** (type FCFA) — à confirmer.
- H3. L'établissement crée les comptes parents (pas d'auto-inscription).
- H4. L'appel se fait **par demi-journée** (matin / après-midi) au MVP.
- H5. Un seul établissement par installation, éventuellement plusieurs cycles (collège + lycée).
- H6. Les bulletins suivent un modèle standard (une page par élève et par période) personnalisable en en-tête.

## 9. Questions à trancher

Voir la liste consolidée dans [`plan-de-developpement.md`](plan-de-developpement.md#decisions-a-valider).
