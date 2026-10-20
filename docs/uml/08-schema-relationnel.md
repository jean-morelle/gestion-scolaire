# 8. Schéma relationnel final

> Généré par `php artisan ecole:doc-base` depuis la base MySQL migrée (le 17/10/2026). Reflète exactement les migrations.
> Montants en `bigint unsigned` (unité monétaire entière, ex. FCFA) ; notes et coefficients en `decimal`. Les tables techniques de Laravel (cache, cache_locks, failed_jobs, job_batches, jobs, migrations, password_reset_tokens, sessions) ne sont pas représentées.

## Diagramme

```mermaid
erDiagram
    classes ||--o{ affectations : "classe_id"
    matieres ||--o{ affectations : "matiere_id"
    enseignants |o--o{ affectations : "enseignant_id"
    classes |o--o{ annonces : "classe_id"
    users |o--o{ annonces : "auteur_id"
    classes ||--o{ appels : "classe_id"
    users |o--o{ appels : "saisi_par"
    users |o--o{ appreciations : "saisie_par"
    periodes ||--o{ appreciations : "periode_id"
    inscriptions ||--o{ appreciations : "inscription_id"
    users |o--o{ bulletins : "publie_par"
    publications ||--o{ bulletins : "publication_id"
    periodes ||--o{ bulletins : "periode_id"
    inscriptions ||--o{ bulletins : "inscription_id"
    classes ||--o{ changements_classe : "ancienne_classe_id"
    users |o--o{ changements_classe : "user_id"
    classes ||--o{ changements_classe : "nouvelle_classe_id"
    inscriptions ||--o{ changements_classe : "inscription_id"
    niveaux ||--o{ classes : "niveau_id"
    annees_scolaires ||--o{ classes : "annee_scolaire_id"
    enseignants |o--o{ classes : "professeur_principal_id"
    users |o--o{ cours : "created_by"
    affectations ||--o{ cours : "affectation_id"
    annees_scolaires ||--o{ depenses : "annee_scolaire_id"
    users ||--o{ depenses : "saisi_par"
    categories_depenses ||--o{ depenses : "categorie_depense_id"
    users |o--o{ depenses : "annulee_par"
    cours |o--o{ devoirs : "cours_id"
    affectations ||--o{ devoirs : "affectation_id"
    users |o--o{ devoirs : "created_by"
    eleves ||--o{ documents_eleves : "eleve_id"
    users |o--o{ documents_eleves : "ajoute_par"
    users |o--o{ eleves : "user_id"
    responsables ||--o{ eleve_responsable : "responsable_id"
    eleves ||--o{ eleve_responsable : "eleve_id"
    affectations ||--o{ emplois_du_temps : "affectation_id"
    users ||--o{ enseignants : "user_id"
    enseignants ||--o{ enseignant_matiere : "enseignant_id"
    matieres ||--o{ enseignant_matiere : "matiere_id"
    periodes ||--o{ evaluations : "periode_id"
    users |o--o{ evaluations : "created_by"
    affectations ||--o{ evaluations : "affectation_id"
    users |o--o{ evaluations : "validee_par"
    types_frais ||--o{ frais_eleves : "type_frais_id"
    tranches |o--o{ frais_eleves : "tranche_id"
    inscriptions ||--o{ frais_eleves : "inscription_id"
    annees_scolaires ||--o{ grilles_frais : "annee_scolaire_id"
    types_frais ||--o{ grilles_frais : "type_frais_id"
    niveaux ||--o{ grilles_frais : "niveau_id"
    paiements ||--o{ imputations : "paiement_id"
    frais_eleves ||--o{ imputations : "frais_eleve_id"
    annees_scolaires ||--o{ inscriptions : "annee_scolaire_id"
    eleves ||--o{ inscriptions : "eleve_id"
    classes ||--o{ inscriptions : "classe_id"
    users |o--o{ journal_activites : "user_id"
    inscriptions ||--o{ justificatifs : "inscription_id"
    users |o--o{ justificatifs : "saisi_par"
    presences ||--o{ justificatif_presence : "presence_id"
    justificatifs ||--o{ justificatif_presence : "justificatif_id"
    permissions ||--o{ model_has_permissions : "permission_id"
    roles ||--o{ model_has_roles : "role_id"
    inscriptions ||--o{ notes : "inscription_id"
    evaluations ||--o{ notes : "evaluation_id"
    users ||--o{ paiements : "encaisse_par"
    users |o--o{ paiements : "annule_par"
    inscriptions ||--o{ paiements : "inscription_id"
    annees_scolaires ||--o{ periodes : "annee_scolaire_id"
    inscriptions ||--o{ presences : "inscription_id"
    appels ||--o{ presences : "appel_id"
    classes ||--o{ publications : "classe_id"
    users |o--o{ publications : "publiee_par"
    periodes ||--o{ publications : "periode_id"
    users |o--o{ remises : "annulee_par"
    users |o--o{ remises : "accordee_par"
    frais_eleves ||--o{ remises : "frais_eleve_id"
    inscriptions ||--o{ remises_devoirs : "inscription_id"
    devoirs ||--o{ remises_devoirs : "devoir_id"
    users |o--o{ remises_devoirs : "corrige_par"
    users |o--o{ responsables : "user_id"
    cours ||--o{ ressources_pedagogiques : "cours_id"
    roles ||--o{ role_has_permissions : "role_id"
    permissions ||--o{ role_has_permissions : "permission_id"
    grilles_frais ||--o{ tranches : "grille_frais_id"
    affectations {
        bigint id PK
        bigint classe_id FK
        bigint matiere_id FK
        bigint enseignant_id FK
        decimal coefficient
        tinyint ordre_bulletin
        timestamp created_at
        timestamp updated_at
    }
    annees_scolaires {
        bigint id PK
        varchar libelle UK
        date date_debut
        date date_fin
        tinyint est_active
        varchar statut
        timestamp created_at
        timestamp updated_at
    }
    annonces {
        bigint id PK
        varchar titre
        text contenu
        varchar cible
        bigint classe_id FK
        timestamp publiee_at
        date expire_le
        timestamp notifiee_at
        bigint auteur_id FK
        timestamp created_at
        timestamp updated_at
    }
    appels {
        bigint id PK
        bigint classe_id FK
        date date_appel
        varchar creneau
        bigint saisi_par FK
        timestamp created_at
        timestamp updated_at
    }
    appreciations {
        bigint id PK
        bigint inscription_id FK
        bigint periode_id FK
        text appreciation_generale
        varchar mention
        bigint saisie_par FK
        timestamp created_at
        timestamp updated_at
    }
    bulletins {
        bigint id PK
        bigint inscription_id FK
        bigint periode_id FK
        bigint publication_id FK
        decimal moyenne_generale
        smallint rang
        smallint effectif
        varchar mention
        varchar distinction
        decimal moyenne_annuelle
        smallint rang_annuel
        text appreciation
        longtext lignes
        longtext assiduite
        smallint version
        bigint publie_par FK
        timestamp created_at
        timestamp updated_at
    }
    categories_depenses {
        bigint id PK
        varchar libelle UK
        varchar description
        timestamp created_at
        timestamp updated_at
    }
    changements_classe {
        bigint id PK
        bigint inscription_id FK
        bigint ancienne_classe_id FK
        bigint nouvelle_classe_id FK
        date date_effet
        varchar motif
        bigint user_id FK
        timestamp created_at
        timestamp updated_at
    }
    classes {
        bigint id PK
        bigint annee_scolaire_id FK
        bigint niveau_id FK
        varchar nom
        varchar serie
        smallint capacite
        bigint professeur_principal_id FK
        timestamp created_at
        timestamp updated_at
    }
    cours {
        bigint id PK
        bigint affectation_id FK
        varchar titre
        text description
        tinyint est_publie
        timestamp publie_at
        bigint created_by FK
        timestamp created_at
        timestamp updated_at
    }
    depenses {
        bigint id PK
        varchar numero UK
        bigint categorie_depense_id FK
        bigint annee_scolaire_id FK
        varchar libelle
        bigint montant
        date date_depense
        varchar mode
        varchar beneficiaire
        varchar reference_piece
        varchar justificatif_chemin
        bigint saisi_par FK
        varchar statut
        bigint annulee_par FK
        timestamp annulee_at
        varchar motif_annulation
        timestamp created_at
        timestamp updated_at
    }
    devoirs {
        bigint id PK
        bigint affectation_id FK
        bigint cours_id FK
        varchar titre
        text consignes
        varchar fichier_chemin
        varchar fichier_nom
        datetime date_limite
        decimal bareme
        tinyint remise_en_ligne
        tinyint accepte_retard
        tinyint est_publie
        timestamp publie_at
        bigint created_by FK
        timestamp created_at
        timestamp updated_at
    }
    documents_eleves {
        bigint id PK
        bigint eleve_id FK
        varchar libelle
        varchar fichier_chemin
        varchar fichier_nom
        varchar fichier_mime
        int fichier_taille
        bigint ajoute_par FK
        timestamp created_at
        timestamp updated_at
    }
    eleves {
        bigint id PK
        bigint user_id FK
        varchar matricule UK
        varchar matricule_national UK
        varchar nom
        varchar prenoms
        char sexe
        date date_naissance
        varchar lieu_naissance
        varchar nationalite
        varchar adresse
        varchar photo_chemin
        varchar statut
        text observations
        timestamp created_at
        timestamp updated_at
    }
    eleve_responsable {
        bigint eleve_id PK
        bigint responsable_id PK
        varchar lien
        tinyint est_contact_principal
        tinyint est_payeur
        timestamp created_at
        timestamp updated_at
    }
    emplois_du_temps {
        bigint id PK
        bigint affectation_id FK
        tinyint jour
        time heure_debut
        time heure_fin
        varchar salle
        timestamp created_at
        timestamp updated_at
    }
    enseignants {
        bigint id PK
        bigint user_id FK
        varchar matricule UK
        varchar specialite
        varchar diplome
        date date_embauche
        tinyint est_actif
        timestamp created_at
        timestamp updated_at
    }
    enseignant_matiere {
        bigint enseignant_id PK
        bigint matiere_id PK
        timestamp created_at
        timestamp updated_at
    }
    etablissement {
        bigint id PK
        varchar nom
        varchar sigle
        varchar adresse
        varchar ville
        varchar telephone
        varchar email
        varchar logo_chemin
        varchar devise
        varchar slogan
        varchar chef_etablissement
        longtext parametres
        timestamp created_at
        timestamp updated_at
    }
    evaluations {
        bigint id PK
        bigint affectation_id FK
        bigint periode_id FK
        varchar libelle
        varchar type
        date date_evaluation
        decimal bareme
        decimal poids
        varchar statut
        bigint validee_par FK
        timestamp validee_at
        timestamp deverrouillee_at
        bigint created_by FK
        timestamp created_at
        timestamp updated_at
    }
    frais_eleves {
        bigint id PK
        bigint inscription_id FK
        bigint type_frais_id FK
        bigint tranche_id FK
        varchar libelle
        bigint montant_du
        date date_limite
        timestamp created_at
        timestamp updated_at
    }
    grilles_frais {
        bigint id PK
        bigint annee_scolaire_id FK
        bigint niveau_id FK
        bigint type_frais_id FK
        varchar categorie
        bigint montant
        timestamp created_at
        timestamp updated_at
    }
    imputations {
        bigint id PK
        bigint paiement_id FK
        bigint frais_eleve_id FK
        bigint montant
        timestamp created_at
        timestamp updated_at
    }
    inscriptions {
        bigint id PK
        bigint eleve_id FK
        bigint annee_scolaire_id FK
        bigint classe_id FK
        date date_inscription
        varchar type
        varchar statut
        date date_sortie
        varchar motif_sortie
        varchar categorie_frais
        tinyint redoublant
        timestamp created_at
        timestamp updated_at
    }
    journal_activites {
        bigint id PK
        bigint user_id FK
        varchar action
        varchar sujet_type
        bigint sujet_id
        varchar description
        longtext anciennes_valeurs
        longtext nouvelles_valeurs
        varchar ip
        varchar user_agent
        timestamp created_at
    }
    justificatifs {
        bigint id PK
        bigint inscription_id FK
        date date_debut
        date date_fin
        varchar motif
        varchar fichier_chemin
        bigint saisi_par FK
        timestamp created_at
        timestamp updated_at
    }
    justificatif_presence {
        bigint justificatif_id PK
        bigint presence_id PK
    }
    matieres {
        bigint id PK
        varchar code UK
        varchar libelle
        varchar couleur
        timestamp created_at
        timestamp updated_at
    }
    model_has_permissions {
        bigint permission_id PK
        varchar model_type PK
        bigint model_id PK
    }
    model_has_roles {
        bigint role_id PK
        varchar model_type PK
        bigint model_id PK
    }
    niveaux {
        bigint id PK
        varchar libelle UK
        varchar cycle
        tinyint ordre
        timestamp created_at
        timestamp updated_at
    }
    notes {
        bigint id PK
        bigint evaluation_id FK
        bigint inscription_id FK
        decimal valeur
        tinyint est_absent
        varchar observation
        timestamp created_at
        timestamp updated_at
    }
    notifications {
        char id PK
        varchar type
        varchar notifiable_type
        bigint notifiable_id
        text data
        timestamp read_at
        timestamp created_at
        timestamp updated_at
    }
    paiements {
        bigint id PK
        varchar numero_recu UK
        bigint inscription_id FK
        bigint montant
        date date_paiement
        varchar mode
        varchar reference_externe
        varchar payeur_nom
        bigint encaisse_par FK
        varchar statut
        bigint annule_par FK
        timestamp annule_at
        varchar motif_annulation
        timestamp created_at
        timestamp updated_at
    }
    periodes {
        bigint id PK
        bigint annee_scolaire_id FK
        varchar libelle
        tinyint ordre
        date date_debut
        date date_fin
        varchar statut
        timestamp created_at
        timestamp updated_at
    }
    permissions {
        bigint id PK
        varchar name
        varchar guard_name
        timestamp created_at
        timestamp updated_at
    }
    presences {
        bigint id PK
        bigint appel_id FK
        bigint inscription_id FK
        varchar statut
        smallint minutes_retard
        varchar remarque
        timestamp created_at
        timestamp updated_at
    }
    publications {
        bigint id PK
        bigint classe_id FK
        bigint periode_id FK
        bigint publiee_par FK
        timestamp publiee_at
        timestamp depubliee_at
        timestamp created_at
        timestamp updated_at
    }
    remises {
        bigint id PK
        bigint frais_eleve_id FK
        bigint montant
        varchar motif
        bigint accordee_par FK
        timestamp annulee_at
        bigint annulee_par FK
        varchar motif_annulation
        timestamp created_at
        timestamp updated_at
    }
    remises_devoirs {
        bigint id PK
        bigint devoir_id FK
        bigint inscription_id FK
        text commentaire
        varchar fichier_chemin
        varchar fichier_nom
        varchar fichier_mime
        int fichier_taille
        timestamp remis_at
        tinyint en_retard
        decimal note
        text correction
        bigint corrige_par FK
        timestamp corrige_at
        timestamp created_at
        timestamp updated_at
    }
    responsables {
        bigint id PK
        bigint user_id FK
        varchar nom
        varchar prenoms
        varchar telephone
        varchar telephone_2
        varchar email
        varchar profession
        varchar adresse
        timestamp created_at
        timestamp updated_at
    }
    ressources_pedagogiques {
        bigint id PK
        bigint cours_id FK
        varchar type
        varchar titre
        longtext contenu
        varchar url
        varchar fichier_chemin
        varchar fichier_nom
        varchar fichier_mime
        int fichier_taille
        smallint ordre
        timestamp created_at
        timestamp updated_at
    }
    roles {
        bigint id PK
        varchar name
        varchar guard_name
        timestamp created_at
        timestamp updated_at
    }
    role_has_permissions {
        bigint permission_id PK
        bigint role_id PK
    }
    sequences {
        bigint id PK
        varchar cle UK
        bigint valeur
        timestamp created_at
        timestamp updated_at
    }
    tranches {
        bigint id PK
        bigint grille_frais_id FK
        varchar libelle
        bigint montant
        date date_limite
        tinyint ordre
        timestamp created_at
        timestamp updated_at
    }
    types_frais {
        bigint id PK
        varchar code UK
        varchar libelle
        tinyint est_obligatoire
        timestamp created_at
        timestamp updated_at
    }
    users {
        bigint id PK
        varchar name
        varchar email UK
        varchar telephone UK
        timestamp email_verified_at
        varchar password
        tinyint est_actif
        tinyint doit_changer_mot_de_passe
        timestamp derniere_connexion_at
        varchar remember_token
        timestamp created_at
        timestamp updated_at
    }
```

## Tables

### `affectations`

| Colonne | Type | Null | Clé | Référence (suppression) |
|---|---|---|---|---|
| `id` | bigint(20) unsigned | non | PK |  |
| `classe_id` | bigint(20) unsigned | non | index | `classes.id` (RESTRICT) |
| `matiere_id` | bigint(20) unsigned | non | index | `matieres.id` (RESTRICT) |
| `enseignant_id` | bigint(20) unsigned | oui | index | `enseignants.id` (SET NULL) |
| `coefficient` | decimal(4,2) | non |  |  |
| `ordre_bulletin` | tinyint(3) unsigned | non |  |  |
| `created_at` | timestamp | oui |  |  |
| `updated_at` | timestamp | oui |  |  |

Unicité composée : (classe_id, matiere_id)

### `annees_scolaires`

| Colonne | Type | Null | Clé | Référence (suppression) |
|---|---|---|---|---|
| `id` | bigint(20) unsigned | non | PK |  |
| `libelle` | varchar(20) | non | unique |  |
| `date_debut` | date | non |  |  |
| `date_fin` | date | non |  |  |
| `est_active` | tinyint(1) | non | index |  |
| `statut` | varchar(20) | non |  |  |
| `created_at` | timestamp | oui |  |  |
| `updated_at` | timestamp | oui |  |  |

### `annonces`

| Colonne | Type | Null | Clé | Référence (suppression) |
|---|---|---|---|---|
| `id` | bigint(20) unsigned | non | PK |  |
| `titre` | varchar(150) | non |  |  |
| `contenu` | text | non |  |  |
| `cible` | varchar(20) | non | index |  |
| `classe_id` | bigint(20) unsigned | oui | index | `classes.id` (SET NULL) |
| `publiee_at` | timestamp | non | index |  |
| `expire_le` | date | oui |  |  |
| `notifiee_at` | timestamp | oui |  |  |
| `auteur_id` | bigint(20) unsigned | oui | index | `users.id` (SET NULL) |
| `created_at` | timestamp | oui |  |  |
| `updated_at` | timestamp | oui |  |  |

### `appels`

| Colonne | Type | Null | Clé | Référence (suppression) |
|---|---|---|---|---|
| `id` | bigint(20) unsigned | non | PK |  |
| `classe_id` | bigint(20) unsigned | non | index | `classes.id` (RESTRICT) |
| `date_appel` | date | non |  |  |
| `creneau` | varchar(20) | non |  |  |
| `saisi_par` | bigint(20) unsigned | oui | index | `users.id` (SET NULL) |
| `created_at` | timestamp | oui |  |  |
| `updated_at` | timestamp | oui |  |  |

Unicité composée : (classe_id, date_appel, creneau)

### `appreciations`

| Colonne | Type | Null | Clé | Référence (suppression) |
|---|---|---|---|---|
| `id` | bigint(20) unsigned | non | PK |  |
| `inscription_id` | bigint(20) unsigned | non | index | `inscriptions.id` (RESTRICT) |
| `periode_id` | bigint(20) unsigned | non | index | `periodes.id` (RESTRICT) |
| `appreciation_generale` | text | oui |  |  |
| `mention` | varchar(50) | oui |  |  |
| `saisie_par` | bigint(20) unsigned | oui | index | `users.id` (SET NULL) |
| `created_at` | timestamp | oui |  |  |
| `updated_at` | timestamp | oui |  |  |

Unicité composée : (inscription_id, periode_id)

### `bulletins`

| Colonne | Type | Null | Clé | Référence (suppression) |
|---|---|---|---|---|
| `id` | bigint(20) unsigned | non | PK |  |
| `inscription_id` | bigint(20) unsigned | non | index | `inscriptions.id` (RESTRICT) |
| `periode_id` | bigint(20) unsigned | non | index | `periodes.id` (RESTRICT) |
| `publication_id` | bigint(20) unsigned | non | index | `publications.id` (RESTRICT) |
| `moyenne_generale` | decimal(6,3) | oui |  |  |
| `rang` | smallint(5) unsigned | oui |  |  |
| `effectif` | smallint(5) unsigned | non |  |  |
| `mention` | varchar(50) | oui |  |  |
| `distinction` | varchar(100) | oui |  |  |
| `moyenne_annuelle` | decimal(6,3) | oui |  |  |
| `rang_annuel` | smallint(5) unsigned | oui |  |  |
| `appreciation` | text | oui |  |  |
| `lignes` | longtext | non |  |  |
| `assiduite` | longtext | non |  |  |
| `version` | smallint(5) unsigned | non |  |  |
| `publie_par` | bigint(20) unsigned | oui | index | `users.id` (SET NULL) |
| `created_at` | timestamp | oui |  |  |
| `updated_at` | timestamp | oui |  |  |

Unicité composée : (inscription_id, periode_id)

### `categories_depenses`

| Colonne | Type | Null | Clé | Référence (suppression) |
|---|---|---|---|---|
| `id` | bigint(20) unsigned | non | PK |  |
| `libelle` | varchar(100) | non | unique |  |
| `description` | varchar(255) | oui |  |  |
| `created_at` | timestamp | oui |  |  |
| `updated_at` | timestamp | oui |  |  |

### `changements_classe`

| Colonne | Type | Null | Clé | Référence (suppression) |
|---|---|---|---|---|
| `id` | bigint(20) unsigned | non | PK |  |
| `inscription_id` | bigint(20) unsigned | non | index | `inscriptions.id` (RESTRICT) |
| `ancienne_classe_id` | bigint(20) unsigned | non | index | `classes.id` (RESTRICT) |
| `nouvelle_classe_id` | bigint(20) unsigned | non | index | `classes.id` (RESTRICT) |
| `date_effet` | date | non |  |  |
| `motif` | varchar(255) | oui |  |  |
| `user_id` | bigint(20) unsigned | oui | index | `users.id` (SET NULL) |
| `created_at` | timestamp | oui |  |  |
| `updated_at` | timestamp | oui |  |  |

### `classes`

| Colonne | Type | Null | Clé | Référence (suppression) |
|---|---|---|---|---|
| `id` | bigint(20) unsigned | non | PK |  |
| `annee_scolaire_id` | bigint(20) unsigned | non | index | `annees_scolaires.id` (RESTRICT) |
| `niveau_id` | bigint(20) unsigned | non | index | `niveaux.id` (RESTRICT) |
| `nom` | varchar(30) | non |  |  |
| `serie` | varchar(10) | oui |  |  |
| `capacite` | smallint(5) unsigned | oui |  |  |
| `professeur_principal_id` | bigint(20) unsigned | oui | index | `enseignants.id` (SET NULL) |
| `created_at` | timestamp | oui |  |  |
| `updated_at` | timestamp | oui |  |  |

Unicité composée : (annee_scolaire_id, nom)

### `cours`

| Colonne | Type | Null | Clé | Référence (suppression) |
|---|---|---|---|---|
| `id` | bigint(20) unsigned | non | PK |  |
| `affectation_id` | bigint(20) unsigned | non | index | `affectations.id` (RESTRICT) |
| `titre` | varchar(150) | non |  |  |
| `description` | text | oui |  |  |
| `est_publie` | tinyint(1) | non |  |  |
| `publie_at` | timestamp | oui |  |  |
| `created_by` | bigint(20) unsigned | oui | index | `users.id` (SET NULL) |
| `created_at` | timestamp | oui |  |  |
| `updated_at` | timestamp | oui |  |  |

### `depenses`

| Colonne | Type | Null | Clé | Référence (suppression) |
|---|---|---|---|---|
| `id` | bigint(20) unsigned | non | PK |  |
| `numero` | varchar(30) | non | unique |  |
| `categorie_depense_id` | bigint(20) unsigned | non | index | `categories_depenses.id` (RESTRICT) |
| `annee_scolaire_id` | bigint(20) unsigned | non | index | `annees_scolaires.id` (RESTRICT) |
| `libelle` | varchar(150) | non |  |  |
| `montant` | bigint(20) unsigned | non |  |  |
| `date_depense` | date | non | index |  |
| `mode` | varchar(20) | non |  |  |
| `beneficiaire` | varchar(150) | oui |  |  |
| `reference_piece` | varchar(100) | oui |  |  |
| `justificatif_chemin` | varchar(255) | oui |  |  |
| `saisi_par` | bigint(20) unsigned | non | index | `users.id` (RESTRICT) |
| `statut` | varchar(20) | non | index |  |
| `annulee_par` | bigint(20) unsigned | oui | index | `users.id` (SET NULL) |
| `annulee_at` | timestamp | oui |  |  |
| `motif_annulation` | varchar(255) | oui |  |  |
| `created_at` | timestamp | oui |  |  |
| `updated_at` | timestamp | oui |  |  |

### `devoirs`

| Colonne | Type | Null | Clé | Référence (suppression) |
|---|---|---|---|---|
| `id` | bigint(20) unsigned | non | PK |  |
| `affectation_id` | bigint(20) unsigned | non | index | `affectations.id` (RESTRICT) |
| `cours_id` | bigint(20) unsigned | oui | index | `cours.id` (SET NULL) |
| `titre` | varchar(150) | non |  |  |
| `consignes` | text | non |  |  |
| `fichier_chemin` | varchar(255) | oui |  |  |
| `fichier_nom` | varchar(255) | oui |  |  |
| `date_limite` | datetime | non |  |  |
| `bareme` | decimal(5,2) | non |  |  |
| `remise_en_ligne` | tinyint(1) | non |  |  |
| `accepte_retard` | tinyint(1) | non |  |  |
| `est_publie` | tinyint(1) | non |  |  |
| `publie_at` | timestamp | oui |  |  |
| `created_by` | bigint(20) unsigned | oui | index | `users.id` (SET NULL) |
| `created_at` | timestamp | oui |  |  |
| `updated_at` | timestamp | oui |  |  |

### `documents_eleves`

| Colonne | Type | Null | Clé | Référence (suppression) |
|---|---|---|---|---|
| `id` | bigint(20) unsigned | non | PK |  |
| `eleve_id` | bigint(20) unsigned | non | index | `eleves.id` (RESTRICT) |
| `libelle` | varchar(100) | non |  |  |
| `fichier_chemin` | varchar(255) | non |  |  |
| `fichier_nom` | varchar(255) | non |  |  |
| `fichier_mime` | varchar(100) | non |  |  |
| `fichier_taille` | int(10) unsigned | non |  |  |
| `ajoute_par` | bigint(20) unsigned | oui | index | `users.id` (SET NULL) |
| `created_at` | timestamp | oui |  |  |
| `updated_at` | timestamp | oui |  |  |

### `eleves`

| Colonne | Type | Null | Clé | Référence (suppression) |
|---|---|---|---|---|
| `id` | bigint(20) unsigned | non | PK |  |
| `user_id` | bigint(20) unsigned | oui | unique | `users.id` (SET NULL) |
| `matricule` | varchar(20) | non | unique |  |
| `matricule_national` | varchar(30) | oui | unique |  |
| `nom` | varchar(100) | non | index |  |
| `prenoms` | varchar(150) | non |  |  |
| `sexe` | char(1) | non |  |  |
| `date_naissance` | date | non |  |  |
| `lieu_naissance` | varchar(100) | oui |  |  |
| `nationalite` | varchar(50) | oui |  |  |
| `adresse` | varchar(255) | oui |  |  |
| `photo_chemin` | varchar(255) | oui |  |  |
| `statut` | varchar(20) | non |  |  |
| `observations` | text | oui |  |  |
| `created_at` | timestamp | oui |  |  |
| `updated_at` | timestamp | oui |  |  |

### `eleve_responsable`

| Colonne | Type | Null | Clé | Référence (suppression) |
|---|---|---|---|---|
| `eleve_id` | bigint(20) unsigned | non | PK | `eleves.id` (CASCADE) |
| `responsable_id` | bigint(20) unsigned | non | PK | `responsables.id` (CASCADE) |
| `lien` | varchar(20) | non |  |  |
| `est_contact_principal` | tinyint(1) | non |  |  |
| `est_payeur` | tinyint(1) | non |  |  |
| `created_at` | timestamp | oui |  |  |
| `updated_at` | timestamp | oui |  |  |

### `emplois_du_temps`

| Colonne | Type | Null | Clé | Référence (suppression) |
|---|---|---|---|---|
| `id` | bigint(20) unsigned | non | PK |  |
| `affectation_id` | bigint(20) unsigned | non | index | `affectations.id` (CASCADE) |
| `jour` | tinyint(3) unsigned | non | index |  |
| `heure_debut` | time | non |  |  |
| `heure_fin` | time | non |  |  |
| `salle` | varchar(50) | oui |  |  |
| `created_at` | timestamp | oui |  |  |
| `updated_at` | timestamp | oui |  |  |

### `enseignants`

| Colonne | Type | Null | Clé | Référence (suppression) |
|---|---|---|---|---|
| `id` | bigint(20) unsigned | non | PK |  |
| `user_id` | bigint(20) unsigned | non | unique | `users.id` (RESTRICT) |
| `matricule` | varchar(30) | oui | unique |  |
| `specialite` | varchar(100) | oui |  |  |
| `diplome` | varchar(100) | oui |  |  |
| `date_embauche` | date | oui |  |  |
| `est_actif` | tinyint(1) | non |  |  |
| `created_at` | timestamp | oui |  |  |
| `updated_at` | timestamp | oui |  |  |

### `enseignant_matiere`

| Colonne | Type | Null | Clé | Référence (suppression) |
|---|---|---|---|---|
| `enseignant_id` | bigint(20) unsigned | non | PK | `enseignants.id` (CASCADE) |
| `matiere_id` | bigint(20) unsigned | non | PK | `matieres.id` (RESTRICT) |
| `created_at` | timestamp | oui |  |  |
| `updated_at` | timestamp | oui |  |  |

### `etablissement`

| Colonne | Type | Null | Clé | Référence (suppression) |
|---|---|---|---|---|
| `id` | bigint(20) unsigned | non | PK |  |
| `nom` | varchar(255) | non |  |  |
| `sigle` | varchar(30) | oui |  |  |
| `adresse` | varchar(255) | oui |  |  |
| `ville` | varchar(100) | oui |  |  |
| `telephone` | varchar(30) | oui |  |  |
| `email` | varchar(255) | oui |  |  |
| `logo_chemin` | varchar(255) | oui |  |  |
| `devise` | varchar(10) | non |  |  |
| `slogan` | varchar(255) | oui |  |  |
| `chef_etablissement` | varchar(255) | oui |  |  |
| `parametres` | longtext | oui |  |  |
| `created_at` | timestamp | oui |  |  |
| `updated_at` | timestamp | oui |  |  |

### `evaluations`

| Colonne | Type | Null | Clé | Référence (suppression) |
|---|---|---|---|---|
| `id` | bigint(20) unsigned | non | PK |  |
| `affectation_id` | bigint(20) unsigned | non | index | `affectations.id` (RESTRICT) |
| `periode_id` | bigint(20) unsigned | non | index | `periodes.id` (RESTRICT) |
| `libelle` | varchar(100) | non |  |  |
| `type` | varchar(20) | non |  |  |
| `date_evaluation` | date | non |  |  |
| `bareme` | decimal(5,2) | non |  |  |
| `poids` | decimal(4,2) | non |  |  |
| `statut` | varchar(20) | non |  |  |
| `validee_par` | bigint(20) unsigned | oui | index | `users.id` (SET NULL) |
| `validee_at` | timestamp | oui |  |  |
| `deverrouillee_at` | timestamp | oui |  |  |
| `created_by` | bigint(20) unsigned | oui | index | `users.id` (SET NULL) |
| `created_at` | timestamp | oui |  |  |
| `updated_at` | timestamp | oui |  |  |

### `frais_eleves`

| Colonne | Type | Null | Clé | Référence (suppression) |
|---|---|---|---|---|
| `id` | bigint(20) unsigned | non | PK |  |
| `inscription_id` | bigint(20) unsigned | non | index | `inscriptions.id` (RESTRICT) |
| `type_frais_id` | bigint(20) unsigned | non | index | `types_frais.id` (RESTRICT) |
| `tranche_id` | bigint(20) unsigned | oui | index | `tranches.id` (SET NULL) |
| `libelle` | varchar(100) | non |  |  |
| `montant_du` | bigint(20) unsigned | non |  |  |
| `date_limite` | date | non |  |  |
| `created_at` | timestamp | oui |  |  |
| `updated_at` | timestamp | oui |  |  |

### `grilles_frais`

| Colonne | Type | Null | Clé | Référence (suppression) |
|---|---|---|---|---|
| `id` | bigint(20) unsigned | non | PK |  |
| `annee_scolaire_id` | bigint(20) unsigned | non | index | `annees_scolaires.id` (RESTRICT) |
| `niveau_id` | bigint(20) unsigned | non | index | `niveaux.id` (RESTRICT) |
| `type_frais_id` | bigint(20) unsigned | non | index | `types_frais.id` (RESTRICT) |
| `categorie` | varchar(30) | non |  |  |
| `montant` | bigint(20) unsigned | non |  |  |
| `created_at` | timestamp | oui |  |  |
| `updated_at` | timestamp | oui |  |  |

Unicité composée : (annee_scolaire_id, niveau_id, type_frais_id, categorie)

### `imputations`

| Colonne | Type | Null | Clé | Référence (suppression) |
|---|---|---|---|---|
| `id` | bigint(20) unsigned | non | PK |  |
| `paiement_id` | bigint(20) unsigned | non | index | `paiements.id` (RESTRICT) |
| `frais_eleve_id` | bigint(20) unsigned | non | index | `frais_eleves.id` (RESTRICT) |
| `montant` | bigint(20) unsigned | non |  |  |
| `created_at` | timestamp | oui |  |  |
| `updated_at` | timestamp | oui |  |  |

Unicité composée : (paiement_id, frais_eleve_id)

### `inscriptions`

| Colonne | Type | Null | Clé | Référence (suppression) |
|---|---|---|---|---|
| `id` | bigint(20) unsigned | non | PK |  |
| `eleve_id` | bigint(20) unsigned | non | index | `eleves.id` (RESTRICT) |
| `annee_scolaire_id` | bigint(20) unsigned | non | index | `annees_scolaires.id` (RESTRICT) |
| `classe_id` | bigint(20) unsigned | non | index | `classes.id` (RESTRICT) |
| `date_inscription` | date | non |  |  |
| `type` | varchar(20) | non |  |  |
| `statut` | varchar(20) | non |  |  |
| `date_sortie` | date | oui |  |  |
| `motif_sortie` | varchar(255) | oui |  |  |
| `categorie_frais` | varchar(30) | oui |  |  |
| `redoublant` | tinyint(1) | non |  |  |
| `created_at` | timestamp | oui |  |  |
| `updated_at` | timestamp | oui |  |  |

Unicité composée : (eleve_id, annee_scolaire_id)

### `journal_activites`

| Colonne | Type | Null | Clé | Référence (suppression) |
|---|---|---|---|---|
| `id` | bigint(20) unsigned | non | PK |  |
| `user_id` | bigint(20) unsigned | oui | index | `users.id` (SET NULL) |
| `action` | varchar(100) | non | index |  |
| `sujet_type` | varchar(255) | oui | index |  |
| `sujet_id` | bigint(20) unsigned | oui |  |  |
| `description` | varchar(255) | oui |  |  |
| `anciennes_valeurs` | longtext | oui |  |  |
| `nouvelles_valeurs` | longtext | oui |  |  |
| `ip` | varchar(45) | oui |  |  |
| `user_agent` | varchar(255) | oui |  |  |
| `created_at` | timestamp | non | index |  |

### `justificatifs`

| Colonne | Type | Null | Clé | Référence (suppression) |
|---|---|---|---|---|
| `id` | bigint(20) unsigned | non | PK |  |
| `inscription_id` | bigint(20) unsigned | non | index | `inscriptions.id` (RESTRICT) |
| `date_debut` | date | non |  |  |
| `date_fin` | date | non |  |  |
| `motif` | varchar(255) | non |  |  |
| `fichier_chemin` | varchar(255) | oui |  |  |
| `saisi_par` | bigint(20) unsigned | oui | index | `users.id` (SET NULL) |
| `created_at` | timestamp | oui |  |  |
| `updated_at` | timestamp | oui |  |  |

### `justificatif_presence`

| Colonne | Type | Null | Clé | Référence (suppression) |
|---|---|---|---|---|
| `justificatif_id` | bigint(20) unsigned | non | PK | `justificatifs.id` (CASCADE) |
| `presence_id` | bigint(20) unsigned | non | PK | `presences.id` (CASCADE) |

### `matieres`

| Colonne | Type | Null | Clé | Référence (suppression) |
|---|---|---|---|---|
| `id` | bigint(20) unsigned | non | PK |  |
| `code` | varchar(20) | non | unique |  |
| `libelle` | varchar(100) | non |  |  |
| `couleur` | varchar(7) | oui |  |  |
| `created_at` | timestamp | oui |  |  |
| `updated_at` | timestamp | oui |  |  |

### `model_has_permissions`

| Colonne | Type | Null | Clé | Référence (suppression) |
|---|---|---|---|---|
| `permission_id` | bigint(20) unsigned | non | PK | `permissions.id` (CASCADE) |
| `model_type` | varchar(255) | non | PK |  |
| `model_id` | bigint(20) unsigned | non | PK |  |

### `model_has_roles`

| Colonne | Type | Null | Clé | Référence (suppression) |
|---|---|---|---|---|
| `role_id` | bigint(20) unsigned | non | PK | `roles.id` (CASCADE) |
| `model_type` | varchar(255) | non | PK |  |
| `model_id` | bigint(20) unsigned | non | PK |  |

### `niveaux`

| Colonne | Type | Null | Clé | Référence (suppression) |
|---|---|---|---|---|
| `id` | bigint(20) unsigned | non | PK |  |
| `libelle` | varchar(30) | non | unique |  |
| `cycle` | varchar(20) | non |  |  |
| `ordre` | tinyint(3) unsigned | non |  |  |
| `created_at` | timestamp | oui |  |  |
| `updated_at` | timestamp | oui |  |  |

### `notes`

| Colonne | Type | Null | Clé | Référence (suppression) |
|---|---|---|---|---|
| `id` | bigint(20) unsigned | non | PK |  |
| `evaluation_id` | bigint(20) unsigned | non | index | `evaluations.id` (RESTRICT) |
| `inscription_id` | bigint(20) unsigned | non | index | `inscriptions.id` (RESTRICT) |
| `valeur` | decimal(5,2) | oui |  |  |
| `est_absent` | tinyint(1) | non |  |  |
| `observation` | varchar(255) | oui |  |  |
| `created_at` | timestamp | oui |  |  |
| `updated_at` | timestamp | oui |  |  |

Unicité composée : (evaluation_id, inscription_id)

### `notifications`

| Colonne | Type | Null | Clé | Référence (suppression) |
|---|---|---|---|---|
| `id` | char(36) | non | PK |  |
| `type` | varchar(255) | non |  |  |
| `notifiable_type` | varchar(255) | non | index |  |
| `notifiable_id` | bigint(20) unsigned | non |  |  |
| `data` | text | non |  |  |
| `read_at` | timestamp | oui |  |  |
| `created_at` | timestamp | oui |  |  |
| `updated_at` | timestamp | oui |  |  |

### `paiements`

| Colonne | Type | Null | Clé | Référence (suppression) |
|---|---|---|---|---|
| `id` | bigint(20) unsigned | non | PK |  |
| `numero_recu` | varchar(30) | non | unique |  |
| `inscription_id` | bigint(20) unsigned | non | index | `inscriptions.id` (RESTRICT) |
| `montant` | bigint(20) unsigned | non |  |  |
| `date_paiement` | date | non | index |  |
| `mode` | varchar(20) | non |  |  |
| `reference_externe` | varchar(100) | oui |  |  |
| `payeur_nom` | varchar(150) | oui |  |  |
| `encaisse_par` | bigint(20) unsigned | non | index | `users.id` (RESTRICT) |
| `statut` | varchar(20) | non | index |  |
| `annule_par` | bigint(20) unsigned | oui | index | `users.id` (SET NULL) |
| `annule_at` | timestamp | oui |  |  |
| `motif_annulation` | varchar(255) | oui |  |  |
| `created_at` | timestamp | oui |  |  |
| `updated_at` | timestamp | oui |  |  |

### `periodes`

| Colonne | Type | Null | Clé | Référence (suppression) |
|---|---|---|---|---|
| `id` | bigint(20) unsigned | non | PK |  |
| `annee_scolaire_id` | bigint(20) unsigned | non | index | `annees_scolaires.id` (RESTRICT) |
| `libelle` | varchar(50) | non |  |  |
| `ordre` | tinyint(3) unsigned | non |  |  |
| `date_debut` | date | non |  |  |
| `date_fin` | date | non |  |  |
| `statut` | varchar(20) | non |  |  |
| `created_at` | timestamp | oui |  |  |
| `updated_at` | timestamp | oui |  |  |

Unicité composée : (annee_scolaire_id, ordre)

### `permissions`

| Colonne | Type | Null | Clé | Référence (suppression) |
|---|---|---|---|---|
| `id` | bigint(20) unsigned | non | PK |  |
| `name` | varchar(255) | non | index |  |
| `guard_name` | varchar(255) | non |  |  |
| `created_at` | timestamp | oui |  |  |
| `updated_at` | timestamp | oui |  |  |

Unicité composée : (name, guard_name)

### `presences`

| Colonne | Type | Null | Clé | Référence (suppression) |
|---|---|---|---|---|
| `id` | bigint(20) unsigned | non | PK |  |
| `appel_id` | bigint(20) unsigned | non | index | `appels.id` (CASCADE) |
| `inscription_id` | bigint(20) unsigned | non | index | `inscriptions.id` (RESTRICT) |
| `statut` | varchar(20) | non |  |  |
| `minutes_retard` | smallint(5) unsigned | oui |  |  |
| `remarque` | varchar(255) | oui |  |  |
| `created_at` | timestamp | oui |  |  |
| `updated_at` | timestamp | oui |  |  |

Unicité composée : (appel_id, inscription_id)

### `publications`

| Colonne | Type | Null | Clé | Référence (suppression) |
|---|---|---|---|---|
| `id` | bigint(20) unsigned | non | PK |  |
| `classe_id` | bigint(20) unsigned | non | index | `classes.id` (RESTRICT) |
| `periode_id` | bigint(20) unsigned | non | index | `periodes.id` (RESTRICT) |
| `publiee_par` | bigint(20) unsigned | oui | index | `users.id` (SET NULL) |
| `publiee_at` | timestamp | non |  |  |
| `depubliee_at` | timestamp | oui |  |  |
| `created_at` | timestamp | oui |  |  |
| `updated_at` | timestamp | oui |  |  |

Unicité composée : (classe_id, periode_id)

### `remises`

| Colonne | Type | Null | Clé | Référence (suppression) |
|---|---|---|---|---|
| `id` | bigint(20) unsigned | non | PK |  |
| `frais_eleve_id` | bigint(20) unsigned | non | index | `frais_eleves.id` (RESTRICT) |
| `montant` | bigint(20) unsigned | non |  |  |
| `motif` | varchar(255) | non |  |  |
| `accordee_par` | bigint(20) unsigned | oui | index | `users.id` (SET NULL) |
| `annulee_at` | timestamp | oui |  |  |
| `annulee_par` | bigint(20) unsigned | oui | index | `users.id` (SET NULL) |
| `motif_annulation` | varchar(255) | oui |  |  |
| `created_at` | timestamp | oui |  |  |
| `updated_at` | timestamp | oui |  |  |

### `remises_devoirs`

| Colonne | Type | Null | Clé | Référence (suppression) |
|---|---|---|---|---|
| `id` | bigint(20) unsigned | non | PK |  |
| `devoir_id` | bigint(20) unsigned | non | index | `devoirs.id` (RESTRICT) |
| `inscription_id` | bigint(20) unsigned | non | index | `inscriptions.id` (RESTRICT) |
| `commentaire` | text | oui |  |  |
| `fichier_chemin` | varchar(255) | oui |  |  |
| `fichier_nom` | varchar(255) | oui |  |  |
| `fichier_mime` | varchar(100) | oui |  |  |
| `fichier_taille` | int(10) unsigned | oui |  |  |
| `remis_at` | timestamp | non |  |  |
| `en_retard` | tinyint(1) | non |  |  |
| `note` | decimal(5,2) | oui |  |  |
| `correction` | text | oui |  |  |
| `corrige_par` | bigint(20) unsigned | oui | index | `users.id` (SET NULL) |
| `corrige_at` | timestamp | oui |  |  |
| `created_at` | timestamp | oui |  |  |
| `updated_at` | timestamp | oui |  |  |

Unicité composée : (devoir_id, inscription_id)

### `responsables`

| Colonne | Type | Null | Clé | Référence (suppression) |
|---|---|---|---|---|
| `id` | bigint(20) unsigned | non | PK |  |
| `user_id` | bigint(20) unsigned | oui | unique | `users.id` (SET NULL) |
| `nom` | varchar(100) | non |  |  |
| `prenoms` | varchar(150) | non |  |  |
| `telephone` | varchar(30) | non | index |  |
| `telephone_2` | varchar(30) | oui |  |  |
| `email` | varchar(255) | oui |  |  |
| `profession` | varchar(100) | oui |  |  |
| `adresse` | varchar(255) | oui |  |  |
| `created_at` | timestamp | oui |  |  |
| `updated_at` | timestamp | oui |  |  |

### `ressources_pedagogiques`

| Colonne | Type | Null | Clé | Référence (suppression) |
|---|---|---|---|---|
| `id` | bigint(20) unsigned | non | PK |  |
| `cours_id` | bigint(20) unsigned | non | index | `cours.id` (CASCADE) |
| `type` | varchar(20) | non |  |  |
| `titre` | varchar(150) | non |  |  |
| `contenu` | longtext | oui |  |  |
| `url` | varchar(255) | oui |  |  |
| `fichier_chemin` | varchar(255) | oui |  |  |
| `fichier_nom` | varchar(255) | oui |  |  |
| `fichier_mime` | varchar(100) | oui |  |  |
| `fichier_taille` | int(10) unsigned | oui |  |  |
| `ordre` | smallint(5) unsigned | non |  |  |
| `created_at` | timestamp | oui |  |  |
| `updated_at` | timestamp | oui |  |  |

### `roles`

| Colonne | Type | Null | Clé | Référence (suppression) |
|---|---|---|---|---|
| `id` | bigint(20) unsigned | non | PK |  |
| `name` | varchar(255) | non | index |  |
| `guard_name` | varchar(255) | non |  |  |
| `created_at` | timestamp | oui |  |  |
| `updated_at` | timestamp | oui |  |  |

Unicité composée : (name, guard_name)

### `role_has_permissions`

| Colonne | Type | Null | Clé | Référence (suppression) |
|---|---|---|---|---|
| `permission_id` | bigint(20) unsigned | non | PK | `permissions.id` (CASCADE) |
| `role_id` | bigint(20) unsigned | non | PK | `roles.id` (CASCADE) |

### `sequences`

| Colonne | Type | Null | Clé | Référence (suppression) |
|---|---|---|---|---|
| `id` | bigint(20) unsigned | non | PK |  |
| `cle` | varchar(50) | non | unique |  |
| `valeur` | bigint(20) unsigned | non |  |  |
| `created_at` | timestamp | oui |  |  |
| `updated_at` | timestamp | oui |  |  |

### `tranches`

| Colonne | Type | Null | Clé | Référence (suppression) |
|---|---|---|---|---|
| `id` | bigint(20) unsigned | non | PK |  |
| `grille_frais_id` | bigint(20) unsigned | non | index | `grilles_frais.id` (CASCADE) |
| `libelle` | varchar(50) | non |  |  |
| `montant` | bigint(20) unsigned | non |  |  |
| `date_limite` | date | non |  |  |
| `ordre` | tinyint(3) unsigned | non |  |  |
| `created_at` | timestamp | oui |  |  |
| `updated_at` | timestamp | oui |  |  |

Unicité composée : (grille_frais_id, ordre)

### `types_frais`

| Colonne | Type | Null | Clé | Référence (suppression) |
|---|---|---|---|---|
| `id` | bigint(20) unsigned | non | PK |  |
| `code` | varchar(30) | non | unique |  |
| `libelle` | varchar(100) | non |  |  |
| `est_obligatoire` | tinyint(1) | non |  |  |
| `created_at` | timestamp | oui |  |  |
| `updated_at` | timestamp | oui |  |  |

### `users`

| Colonne | Type | Null | Clé | Référence (suppression) |
|---|---|---|---|---|
| `id` | bigint(20) unsigned | non | PK |  |
| `name` | varchar(255) | non |  |  |
| `email` | varchar(255) | oui | unique |  |
| `telephone` | varchar(30) | oui | unique |  |
| `email_verified_at` | timestamp | oui |  |  |
| `password` | varchar(255) | non |  |  |
| `est_actif` | tinyint(1) | non |  |  |
| `doit_changer_mot_de_passe` | tinyint(1) | non |  |  |
| `derniere_connexion_at` | timestamp | oui |  |  |
| `remember_token` | varchar(100) | oui |  |  |
| `created_at` | timestamp | oui |  |  |
| `updated_at` | timestamp | oui |  |  |

