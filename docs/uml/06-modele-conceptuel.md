# 6. Modèle conceptuel des données (MCD)

Entités et associations avec leurs cardinalités, indépendamment de l'implémentation. Notation Mermaid « entité-association » : `||` exactement un, `o|` zéro ou un, `|{` un ou plusieurs, `o{` zéro ou plusieurs.

```mermaid
erDiagram
    ETABLISSEMENT ||--o{ ANNEE_SCOLAIRE : "organise"
    ANNEE_SCOLAIRE ||--|{ PERIODE : "se découpe en"
    ANNEE_SCOLAIRE ||--o{ CLASSE : "ouvre"
    NIVEAU ||--o{ CLASSE : "regroupe"
    ELEVE ||--o{ INSCRIPTION : "s'inscrit"
    CLASSE ||--o{ INSCRIPTION : "accueille"
    ELEVE }o--|{ RESPONSABLE : "est sous la responsabilité de"
    RESPONSABLE |o--o| UTILISATEUR : "dispose du compte"
    ELEVE |o--o| UTILISATEUR : "dispose du compte"
    ENSEIGNANT ||--|| UTILISATEUR : "dispose du compte"
    UTILISATEUR }o--o{ ROLE : "possède"
    ROLE }o--o{ PERMISSION : "accorde"
    CLASSE ||--o{ AFFECTATION : "comprend"
    MATIERE ||--o{ AFFECTATION : "est enseignée dans"
    ENSEIGNANT |o--o{ AFFECTATION : "est titulaire de"
    AFFECTATION ||--o{ CRENEAU : "est planifiée en"
    AFFECTATION ||--o{ EVALUATION : "donne"
    PERIODE ||--o{ EVALUATION : "contient"
    EVALUATION ||--o{ NOTE : "produit"
    INSCRIPTION ||--o{ NOTE : "obtient"
    CLASSE ||--o{ PUBLICATION : "est publiée pour"
    PERIODE ||--o{ PUBLICATION : "concerne"
    INSCRIPTION ||--o{ BULLETIN : "reçoit"
    PUBLICATION ||--|{ BULLETIN : "fige"
    CLASSE ||--o{ APPEL : "fait l'objet de"
    APPEL ||--|{ PRESENCE : "relève"
    INSCRIPTION ||--o{ PRESENCE : "concerne"
    INSCRIPTION ||--o{ JUSTIFICATIF : "fournit"
    TYPE_FRAIS ||--o{ GRILLE_FRAIS : "tarife"
    NIVEAU ||--o{ GRILLE_FRAIS : "est tarifé par"
    GRILLE_FRAIS ||--o{ ECHEANCE : "s'échelonne en"
    INSCRIPTION ||--o{ FRAIS_DU : "doit"
    FRAIS_DU ||--o{ REMISE : "est réduit par"
    INSCRIPTION ||--o{ PAIEMENT : "règle"
    PAIEMENT ||--|{ IMPUTATION : "se répartit en"
    FRAIS_DU ||--o{ IMPUTATION : "est soldé par"
    CATEGORIE_DEPENSE ||--o{ DEPENSE : "classe"
    AFFECTATION ||--o{ COURS : "propose"
    COURS ||--o{ RESSOURCE : "contient"
    AFFECTATION ||--o{ DEVOIR : "donne"
    DEVOIR ||--o{ REMISE_DEVOIR : "reçoit"
    INSCRIPTION ||--o{ REMISE_DEVOIR : "rend"
    UTILISATEUR ||--o{ NOTIFICATION : "reçoit"
    UTILISATEUR ||--o{ JOURNAL_AUDIT : "est l'auteur de"
    UTILISATEUR ||--o{ ANNONCE : "publie"
```

## Choix de modélisation

- **L'inscription (élève × année) est l'entité pivot** : notes, présences, frais, paiements, bulletins et devoirs s'y rattachent. L'historique d'un élève reste exact quand il change de classe ou d'année.
- **Parent = Responsable** : un responsable légal peut suivre plusieurs enfants, un enfant peut avoir plusieurs responsables. Le compte de connexion est facultatif.
- **Valeurs calculées non stockées** : les soldes (frais dus − remises − paiements valides) et les moyennes sont recalculés. **Exception explicite** : le bulletin publié est un instantané figé et versionné, pour que le document remis aux familles ne change pas sans republication tracée.
- **Pas de suppression des opérations financières** : paiement, remise et dépense portent un statut d'annulation.
