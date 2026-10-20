# 2. Cas d'utilisation détaillés par module

`include` = étape obligatoire du cas ; `extend` = variante facultative.

## Module 1 — Gestion scolaire

```mermaid
flowchart LR
    Admin([Administrateur])
    Dir([Directeur])
    Sec([Secrétaire])
    Cen([Censeur])
    Ens([Enseignant])

    A1(Créer / activer / clôturer une année)
    A2(Gérer les périodes : clôturer, rouvrir)
    A3(Gérer niveaux et matières)
    A4(Créer les classes / recopier d'une année)
    B1(Créer le dossier élève)
    B2(Ajouter un responsable)
    B3(Inscrire / réinscrire)
    B4(Réinscription en lot)
    B5(Changer de classe)
    B6(Enregistrer un départ)
    B7(Ajouter les pièces du dossier)
    B8(Créer les comptes parent / élève)
    C1(Gérer les fiches enseignants)
    C2(Affecter matières, enseignants, coefficients)
    C3(Construire l'emploi du temps)
    D1(Créer une évaluation)
    D2(Saisir les notes)
    D3(Valider l'évaluation)
    D4(Déverrouiller avec motif)
    E1(Faire l'appel)
    E2(Saisir un justificatif)
    F1(Saisir les appréciations)
    F2(Publier les bulletins)
    F3(Retirer pour correction)
    G1(Générer les frais dus)
    H1(Détecter les conflits d'horaire)

    Admin --> A1 & A2 & A3 & A4
    Dir --> A4 & C1 & C2 & C3 & D4 & F1 & F2 & F3
    Sec --> B1 & B2 & B3 & B4 & B5 & B6 & B7 & B8
    Cen --> C3 & E1 & E2
    Ens --> D1 & D2 & D3 & E1 & F1

    B1 -. include .-> B2
    B1 -. extend .-> B3
    B3 -. include .-> G1
    D2 -. extend .-> D3
    C3 -. include .-> H1
    F2 -. include .-> F1
```

## Module 2 — Espace parents et élèves

```mermaid
flowchart LR
    Par([Parent])
    Elv([Élève])
    P0(Voir la liste de ses enfants)
    P1(Consulter les bulletins publiés et télécharger le PDF)
    P2(Consulter les notes des périodes publiées)
    P3(Suivre absences et retards)
    P4(Consulter frais, échéances, paiements, reçus)
    P5(Consulter l'emploi du temps)
    P6(Lire les annonces)
    V(Vérifier le lien responsable–élève)
    E1(Se connecter avec son matricule)

    Par --> P0 & P1 & P2 & P3 & P4 & P5 & P6
    Elv --> E1 & P1 & P2 & P3 & P5 & P6
    P1 & P2 & P3 & P4 & P5 -. include .-> V
```

Règle : rien n'est visible avant publication ; l'élève ne voit pas les finances.

## Module 3 — Gestion des enseignants

```mermaid
flowchart LR
    Dir([Directeur])
    Ens([Enseignant])
    K1(Créer le profil et le compte)
    K2(Déclarer les matières enseignées)
    K3(Affecter aux classes et matières)
    K4(Consulter « Mes classes »)
    K5(Consulter la liste des élèves de ses classes)
    K6(Consulter son emploi du temps)
    K7(Saisir notes et appel de ses classes)
    K8(Suivre ses activités : brouillons, copies à corriger)

    Dir --> K1 & K2 & K3
    Ens --> K4 & K5 & K6 & K7 & K8
    K3 -. extend : avertissement si matière non déclarée .-> K2
```

## Module 4 — Plateforme d'apprentissage

```mermaid
flowchart LR
    Ens([Enseignant])
    Elv([Élève])
    Dir([Direction / Censeur])
    L1(Créer un cours)
    L2(Ajouter chapitres, documents, exercices, liens)
    L3(Publier / retirer le cours)
    L4(Créer et publier un devoir)
    L5(Suivre les remises)
    L6(Corriger et noter)
    L7(Consulter les cours publiés de sa classe)
    L8(Télécharger les ressources)
    L9(Remettre / remplacer son travail)
    L10(Voir sa correction)
    L11(Consulter l'activité pédagogique)
    N(Notifier élèves et parents)

    Ens --> L1 & L2 & L3 & L4 & L5 & L6
    Elv --> L7 & L8 & L9 & L10
    Dir --> L11
    L4 -. include .-> N
    L1 -. include .-> L2
```

## Module 5 — Gestion financière

```mermaid
flowchart LR
    Comp([Comptable])
    Dir([Directeur])
    Sys([Système])
    Par([Parent])
    R1(Configurer types de frais et grilles)
    R2(Définir l'échéancier en tranches)
    R3(Attribuer un frais facultatif)
    R4(Encaisser un paiement)
    R5(Éditer le reçu PDF)
    R6(Annuler un paiement)
    R7(Accorder / annuler une remise)
    R8(Enregistrer une dépense)
    R9(Annuler une dépense)
    R10(Consulter situation, rapports, exports)
    R11(Rappeler les échéances)
    R12(Consulter ses paiements et reçus)

    Comp --> R1 & R2 & R3 & R4 & R5 & R8 & R10
    Dir --> R6 & R7 & R9 & R10
    Sys --> R11
    Par --> R12
    R1 -. include .-> R2
    R4 -. include .-> R5
```
