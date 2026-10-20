# 4. Diagrammes de séquence

## 4.1 Authentification

```mermaid
sequenceDiagram
    actor U as Utilisateur
    participant C as ConnexionController
    participant R as ConnexionRequest
    participant L as RateLimiter
    participant DB as MySQL
    participant J as JournalService
    U->>C: POST /connexion (identifiant, mot de passe)
    C->>R: authenticate()
    R->>L: tentatives < 5 pour (identifiant, IP) ?
    alt trop de tentatives
        L-->>U: erreur « Réessayez dans N secondes »
    end
    alt identifiant au format matricule AAAA-NNNNN
        R->>DB: élève par matricule, puis son compte
    else e-mail ou téléphone
        R->>DB: utilisateur par e-mail ou téléphone
    end
    R->>R: Hash::check(mot de passe)
    alt échec
        R->>L: hit()
        R->>J: connexion.echecs_repetes (à la 5e tentative)
        R-->>U: « Identifiant ou mot de passe incorrect »
    else compte désactivé
        R-->>U: « Compte désactivé »
    else succès
        R->>L: clear()
        C->>C: régénérer la session
        C->>DB: derniere_connexion_at
        C->>J: connexion
        alt mot de passe temporaire
            C-->>U: redirection vers changement de mot de passe
        else
            C-->>U: tableau de bord (ou espace famille / élève)
        end
    end
```

## 4.2 Inscription d'un élève

```mermaid
sequenceDiagram
    actor S as Secrétaire
    participant EC as EleveController
    participant ES as EleveService
    participant N as NumerotationService
    participant IS as InscriptionService
    participant FS as FraisService
    participant DB as MySQL
    participant J as JournalService
    S->>EC: POST /eleves (identité, responsable, classe)
    EC->>EC: EleveRequest (validation, eleves.gerer)
    EC->>DB: BEGIN
    EC->>ES: creer(identité, responsable)
    ES->>N: matricule(année) — SELECT … FOR UPDATE
    N-->>ES: AAAA-NNNNN
    ES->>DB: INSERT eleves, responsables, eleve_responsable
    ES->>J: eleve.cree, responsable.rattache
    EC->>IS: inscrire(élève, classe, options)
    IS->>DB: inscription existante pour l'année ? (verrou)
    alt déjà inscrit
        IS-->>EC: erreur de validation
        EC->>DB: ROLLBACK
        EC-->>S: formulaire avec erreur
    else
        IS->>DB: INSERT inscriptions (type nouvelle ou réinscription)
        IS->>FS: genererObligatoires(inscription)
        FS->>DB: INSERT frais_eleves (une ligne par tranche)
        IS->>IS: capacité dépassée ? (avertissement non bloquant)
        IS->>J: inscription.creee
        EC->>DB: COMMIT
        EC-->>S: fiche élève + message (et avertissement éventuel)
    end
```

## 4.3 Saisie et publication des notes

```mermaid
sequenceDiagram
    actor E as Enseignant
    actor D as Directeur
    participant EV as EvaluationController
    participant P as EvaluationPolicy
    participant ES as EvaluationService
    participant BC as BulletinController
    participant BS as BulletinService
    participant MS as MoyenneService
    participant DB as MySQL
    participant F as Familles
    E->>EV: PUT /evaluations/{id}/notes (notes, valider)
    EV->>P: update ? (enseignant de l'affectation)
    EV->>ES: saisirNotes()
    ES->>ES: période ouverte, classe non publiée, 0 ≤ note ≤ barème
    ES->>DB: upsert notes (historique si déverrouillée)
    EV->>ES: valider()
    ES->>ES: chaque élève a une note ou une absence ?
    ES->>DB: statut = validee
    D->>BC: POST /bulletins/.../publier
    BC->>BS: publier(classe, période)
    BS->>MS: resultatsClasse(seulementValidees)
    MS->>DB: évaluations validées et notes
    MS-->>BS: moyennes, rangs, mentions
    BS->>DB: BEGIN, publication, bulletins figés (version), COMMIT
    BS->>F: notification « Bulletin disponible »
    BC-->>D: N bulletins publiés
```

## 4.4 Enregistrement d'un paiement

```mermaid
sequenceDiagram
    actor C as Comptable
    participant PC as PaiementController
    participant PS as PaiementService
    participant SS as SoldeService
    participant N as NumerotationService
    participant DB as MySQL
    participant J as JournalService
    C->>PC: POST /finances/eleves/{inscription}/paiements
    PC->>PC: validation, permission paiements.enregistrer
    PC->>PS: encaisser(montant, date, mode, référence)
    PS->>DB: BEGIN
    PS->>DB: SELECT inscription FOR UPDATE
    PS->>SS: situation() — reste dû par échéance
    alt montant > reste dû
        PS->>DB: ROLLBACK (aucun numéro consommé)
        PS-->>C: erreur
    else
        PS->>PS: imputation : échéances les plus anciennes d'abord
        PS->>N: recu(année) — SELECT … FOR UPDATE
        N-->>PS: REC-AAAA-NNNNNN
        PS->>DB: INSERT paiements, imputations
        PS->>J: paiement.enregistre
        PS->>DB: COMMIT
        PC-->>C: confirmation + bouton « Imprimer le reçu » (PDF)
    end
```

## 4.5 Consultation des résultats par un parent

```mermaid
sequenceDiagram
    actor P as Parent
    participant SC as SuiviController
    participant POL as ElevePolicy
    participant SS as SuiviEleveService
    participant BP as BulletinPolicy
    participant DB as MySQL
    P->>SC: GET /famille/enfants/{élève}/resultats
    SC->>POL: suivre(parent, élève)
    POL->>DB: lien responsable–élève existe ?
    alt pas son enfant
        POL-->>P: 403 Accès refusé
    else
        SC->>SS: bulletinsPublies(), notesPubliees()
        SS->>DB: bulletins dont la publication est en vigueur
        SS->>DB: notes d'évaluations validées des classes publiées
        SC-->>P: résultats publiés uniquement
        P->>SC: GET /bulletins/{id}/pdf
        SC->>BP: view ? (publié + lien parent)
        SC-->>P: PDF du bulletin
    end
```

## 4.6 Remise d'un devoir par un élève

```mermaid
sequenceDiagram
    actor E as Élève
    participant AC as ApprentissageController
    participant DP as DevoirPolicy
    participant AS as ApprentissageService
    participant ST as Stockage privé
    participant DB as MySQL
    E->>AC: POST /mon-espace/devoirs/{id}/remise (fichier, réponse)
    AC->>DP: remettre ? (devoir publié, élève de la classe)
    AC->>AC: validation type et taille du fichier
    AC->>AS: remettre(devoir, inscription, fichier)
    AS->>AS: remise en ligne autorisée ? date limite ou retard accepté ?
    AS->>DB: BEGIN, remise existante (verrou)
    alt déjà corrigée
        AS-->>E: refus
    else
        AS->>ST: enregistrer le fichier (supprimer l'ancien)
        AS->>DB: upsert remises_devoirs (remis_at, en_retard), COMMIT
        AC-->>E: « Travail déposé le … »
    end
```
