# Modèle de données (proposition)

> Version 0.1 — 12/10/2026 — **[PROPOSÉ]**, à valider avant l'écriture des migrations (étape 3).
> SGBD : MariaDB 10.4 / MySQL 8, moteur InnoDB, `utf8mb4_unicode_ci`.
> Toutes les tables ont `id` (BIGINT auto-incrémenté) et `created_at`/`updated_at` sauf mention.
> Montants : `BIGINT UNSIGNED` en unité monétaire entière (voir RG-FIN-01).

## 1. Vue d'ensemble

```
etablissement (1 ligne)
annees_scolaires 1─n periodes
annees_scolaires 1─n classes n─1 niveaux
eleves 1─n inscriptions n─1 classes            (unique: eleve + annee)
eleves n─n responsables  (eleve_responsable)
responsables 0..1─1 users (compte parent)
users 1─0..1 enseignants
classes 1─n affectations n─1 matieres, n─1 enseignants   (unique: classe + matiere)
affectations 1─n evaluations n─1 periodes ; evaluations 1─n notes n─1 inscriptions
classes 1─n appels 1─n presences n─1 inscriptions ; presences 1─0..1 justificatifs
publications (classe + periode)
types_frais ; grilles_frais (annee + niveau + type) 1─n tranches
inscriptions 1─n frais_eleves 1─n remises ; inscriptions 1─n paiements 1─n imputations n─1 frais_eleves
sequences ; journal_activites ; (+ tables spatie : roles, permissions, model_has_roles…)
```

Choix clé : les notes, présences, frais et paiements sont rattachés à l'**inscription** (élève × année) et non directement à l'élève. Cela fige automatiquement l'année et la classe et simplifie l'historique.

## 2. Tables

### Administration
**etablissement** — nom, sigle, adresse, telephone, email, logo_chemin, devise (`FCFA`), ville, slogan, chef_etablissement, parametres (JSON : arrondis, mentions, durée de modification des appels…).

**annees_scolaires** — libelle (`2026-2027`, unique), date_debut, date_fin, est_active (bool), statut (`preparation|en_cours|cloturee`).

**periodes** — annee_scolaire_id (FK), libelle (`1er trimestre`), ordre, date_debut, date_fin, statut (`ouverte|cloturee`). Unique (annee_scolaire_id, ordre).

**users** (table Laravel étendue) — name, email (unique, nullable si connexion par téléphone), telephone (unique, nullable), password, est_actif, doit_changer_mot_de_passe, derniere_connexion_at, remember_token.

**journal_activites** — user_id (nullable), action (`paiement.annule`…), sujet_type, sujet_id, description, anciennes_valeurs (JSON), nouvelles_valeurs (JSON), ip, user_agent, created_at (pas d'updated_at). Index (sujet_type, sujet_id), (action), (created_at).

**sequences** — cle (`recu-2026`, `matricule-2026`), valeur. Unique (cle). Lue avec `SELECT … FOR UPDATE`.

### Scolarité
**niveaux** — libelle (`6e`, `2nde`…), cycle (`college|lycee`), ordre. Unique (libelle).

**classes** — annee_scolaire_id, niveau_id, nom (`6e A`), serie (nullable : `A`, `C`, `D`…), capacite (nullable), professeur_principal_id (FK enseignants, nullable). Unique (annee_scolaire_id, nom).

**eleves** — matricule (unique), nom, prenoms, sexe (`M|F`), date_naissance, lieu_naissance, nationalite, adresse, photo_chemin, statut (`actif|archive`), observations. Index (nom, prenoms). *Soft delete non utilisé : archivage par statut.*

**responsables** — user_id (FK users, nullable, unique), nom, prenoms, telephone, telephone_2, email, profession, adresse.

**eleve_responsable** — eleve_id, responsable_id, lien (`pere|mere|tuteur|autre`), est_contact_principal, est_payeur. PK/unique (eleve_id, responsable_id).

**inscriptions** — eleve_id, annee_scolaire_id, classe_id, date_inscription, type (`nouvelle|reinscription`), statut (`inscrit|parti`), date_sortie, motif_sortie, categorie_frais (nullable, voir Q5), redoublant (bool). **Unique (eleve_id, annee_scolaire_id)**. Index (classe_id, statut).

**changements_classe** — inscription_id, ancienne_classe_id, nouvelle_classe_id, date_effet, motif, user_id.

### Pédagogie
**enseignants** — user_id (unique), matricule (unique, nullable), specialite, telephone, statut (`actif|inactif`).

**matieres** — code (unique), libelle, couleur (affichage).

**affectations** — classe_id, matiere_id, enseignant_id (nullable tant que non pourvu), coefficient (DECIMAL 4,2), ordre_bulletin. **Unique (classe_id, matiere_id)**.

**evaluations** — affectation_id, periode_id, libelle, type (`devoir|interrogation|composition`), date_evaluation, bareme (DECIMAL 5,2, défaut 20), poids (DECIMAL 4,2, défaut 1), statut (`brouillon|validee`), validee_par, validee_at, created_by.

**notes** — evaluation_id, inscription_id, valeur (DECIMAL 5,2, nullable), est_absent (bool), observation. **Unique (evaluation_id, inscription_id)**. Contrainte applicative : 0 ≤ valeur ≤ barème.

**publications** — classe_id, periode_id, publiee_par, publiee_at, depubliee_at (nullable). Unique (classe_id, periode_id).

**appreciations** — inscription_id, periode_id, appreciation_generale, mention, saisie_par. Unique (inscription_id, periode_id). *(Les moyennes ne sont pas stockées au MVP : elles sont recalculées. Option P2 : instantané figé à la publication.)*

### Vie scolaire
**appels** — classe_id, date_appel, creneau (`matin|apres_midi` ou séance — Q4), saisi_par, saisi_at. **Unique (classe_id, date_appel, creneau)**.

**presences** — appel_id, inscription_id, statut (`present|absent|retard|absent_excuse`), minutes_retard (nullable), remarque. **Unique (appel_id, inscription_id)**. Index (inscription_id, statut).

**justificatifs** — inscription_id, date_debut, date_fin, motif, fichier_chemin (nullable), saisi_par. Table pivot **justificatif_presence** (justificatif_id, presence_id) — un justificatif peut couvrir plusieurs jours.

### Finance
**types_frais** — libelle (`Inscription`, `Scolarité`, `Tenue`…), code (unique), est_obligatoire.

**grilles_frais** — annee_scolaire_id, niveau_id, type_frais_id, categorie (chaîne vide = toutes catégories, pour que l'index unique s'applique), montant. Unique (annee_scolaire_id, niveau_id, type_frais_id, categorie).

**tranches** — grille_frais_id, libelle (`1re tranche`), montant, date_limite, ordre. Contrainte applicative : Σ montants = montant de la grille.

**frais_eleves** — inscription_id, type_frais_id, tranche_id (nullable), libelle, montant_du, date_limite. Généré à l'inscription (une ligne par tranche). Index (inscription_id).

**remises** — frais_eleve_id, montant, motif, accordee_par, annulee_at, annulee_par, motif_annulation. Active si `annulee_at` est vide ; jamais supprimée.

**paiements** — numero_recu (**unique**), inscription_id, montant (> 0), date_paiement, mode (`especes|mobile_money|virement|cheque`), reference_externe, payeur_nom, encaisse_par (FK users), statut (`valide|annule`), annule_par, annule_at, motif_annulation. Index (date_paiement), (statut).

**imputations** — paiement_id, frais_eleve_id, montant. Unique (paiement_id, frais_eleve_id).

Solde d'une inscription : `Σ frais_eleves.montant_du − Σ remises actives − Σ imputations des paiements valides`.

## 3. Contraintes d'intégrité principales
| Règle | Mise en œuvre |
|---|---|
| Matricule unique | index unique `eleves.matricule` + `sequences` |
| 1 inscription / élève / année | unique (eleve_id, annee_scolaire_id) |
| Pas de doublon de présence | unique (classe_id, date_appel, creneau) + unique (appel_id, inscription_id) |
| Pas de doublon de note | unique (evaluation_id, inscription_id) |
| 1 enseignant + 1 coefficient par matière/classe | unique (classe_id, matiere_id) |
| Reçu unique et séquentiel | unique numero_recu + `sequences` verrouillée en transaction |
| Pas de suppression en cascade de données historiques | FK `restrictOnDelete` sur inscriptions, notes, paiements |

## 4. Points ouverts impactant le modèle
- Q3 : coefficients par classe ou par niveau/série.
- Q4 : granularité de l'appel (journée, demi-journée, séance) → colonne `creneau` ou table `seances`.
- Q5 : catégories de frais, monnaie, avances.
- Q7 : identifiant de connexion des parents (e-mail ou téléphone).
