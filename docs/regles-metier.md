# Règles métier

> Version 0.1 — 12/10/2026. Toutes les règles sont **[PROPOSÉ]** sauf mention contraire. Les valeurs entre crochets (ex. `[20]`) sont des paramètres configurables.

## RG-ANN — Années scolaires et périodes
- **RG-ANN-01** Une seule année scolaire est **active** à la fois ; c'est elle qui est affichée par défaut.
- **RG-ANN-02** Une année est découpée en périodes (`[3]` trimestres ou `[2]` semestres), ordonnées, sans chevauchement de dates.
- **RG-ANN-03** Une période a un statut : `ouverte` → `clôturée`. Aucune note ni présence ne peut être saisie dans une période clôturée.
- **RG-ANN-04** Une année clôturée est en lecture seule (sauf déverrouillage par l'administrateur, journalisé).

## RG-ELV — Élèves et inscriptions
- **RG-ELV-01** Le matricule est **unique** dans l'établissement, attribué à la création, jamais réutilisé. Format proposé : `AAAA-NNNNN` (année d'entrée + séquence) — **[QUESTION Q6]** s'il existe un matricule national imposé.
- **RG-ELV-02** Un élève a **au plus une inscription par année scolaire** (contrainte unique en base).
- **RG-ELV-03** Une inscription a un statut : `inscrit`, `transféré` (changement de classe), `parti` (abandon/départ, avec date et motif). Un élève parti ne figure plus dans l'appel ni les bulletins des périodes suivantes, mais son historique est conservé.
- **RG-ELV-04** Le changement de classe en cours d'année conserve les notes et présences déjà saisies dans l'ancienne classe.
- **RG-ELV-05** Un élève doit avoir **au moins un responsable légal** avec un numéro de téléphone.
- **RG-ELV-06** Un dossier élève n'est jamais supprimé physiquement dès qu'il a une inscription ; il peut être archivé.
- **RG-ELV-07** Capacité de classe : avertissement (non bloquant) au-delà de la capacité définie.
- **RG-ELV-08** *(mis en œuvre le 17/10/2026)* Le type d'inscription est déduit : « réinscription » si l'élève a déjà une inscription, sinon « nouvelle ». Aucune inscription ni modification d'inscription dans une année clôturée ; un dossier archivé doit être réactivé avant inscription.
- **RG-ELV-09** *(mis en œuvre)* Un départ peut être annulé (réintégration), action journalisée. Un dossier ne peut être archivé que si l'élève n'est pas inscrit pour l'année active (départ à enregistrer d'abord).
- **RG-ELV-10** *(mis en œuvre)* Un changement de classe se fait dans la même année ; s'il change de niveau, les frais dus ne sont pas recalculés (ajustement par remise, module Finance) et un avertissement l'indique.
- **RG-ELV-11** *(mis en œuvre)* Les pièces du dossier (PDF/JPG/PNG, taille maximale paramétrable) et les photos sont stockées hors du dossier public. Les pièces sont réservées au personnel autorisé ; la photo est aussi visible des responsables de l'élève et de l'élève lui-même.
- **RG-ELV-12** *(mis en œuvre)* Les frères et sœurs partagent la même fiche responsable, retrouvée par son téléphone ; un seul contact principal par élève.

## RG-ENS — Enseignants et matières
- **RG-ENS-01** Pour une année donnée, un couple (classe, matière) a **un seul enseignant titulaire** et **un coefficient**.
- **RG-ENS-02** Un enseignant ne voit et ne saisit que les évaluations des couples (classe, matière) qui lui sont affectés.
- **RG-ENS-03** Les coefficients sont définis par **classe** (pré-remplis depuis le niveau) — **[QUESTION Q3]**. *Mis en œuvre* : pré-remplissage en reprenant les matières, coefficients et ordre d'une autre classe (même niveau ou année précédente), enseignants actifs repris en option.
- **RG-ENS-04** *(mis en œuvre le 17/10/2026)* Coefficient entre 0,5 et 20 (2 décimales). Toute modification d'enseignant, de coefficient ou d'ordre est journalisée avec les valeurs avant/après. Une matière ayant des évaluations, cours ou devoirs ne peut pas être retirée d'une classe.
- **RG-ENS-05** *(mis en œuvre)* Les matières enseignées déclarées sur la fiche guident les affectations : une affectation hors de ces matières est permise avec un avertissement. Un enseignant inactif ne reçoit plus de nouvelle affectation mais garde les siennes jusqu'à son remplacement ; son compte de connexion n'est désactivé que par l'administration.
- **RG-ENS-06** *(mis en œuvre)* Emploi du temps : créneaux hebdomadaires (lundi à samedi). Dans une même année, un créneau est refusé s'il chevauche un créneau de la même classe, du même enseignant ou de la même salle (créneaux contigus autorisés). Un changement d'enseignant qui crée un chevauchement est signalé par un avertissement.
- **RG-ENS-07** *(mis en œuvre)* Un enseignant voit la liste des élèves et l'emploi du temps des seules classes où il enseigne ou dont il est professeur principal, sans les coordonnées des familles.

## RG-PRE — Présences
- **RG-PRE-01** Un **appel** est identifié par (classe, date, créneau). Il est unique : refaire l'appel le modifie.
- **RG-PRE-02** Créneau MVP : `matin` / `après-midi` — **[QUESTION Q4]** (par journée, demi-journée ou par séance de cours).
- **RG-PRE-03** Un élève a **une seule ligne de présence par appel** (contrainte unique).
- **RG-PRE-04** Statuts : `présent`, `absent`, `retard` (avec minutes facultatives), `absent excusé`.
- **RG-PRE-05** Une absence devient `absent excusé` lorsqu'un justificatif est enregistré (motif obligatoire, pièce jointe facultative : PDF/JPG/PNG ≤ `[2]` Mo).
- **RG-PRE-06** Pas d'appel les jours non ouvrés ni dans une période clôturée ; pas d'appel dans le futur.
- **RG-PRE-07** Modification d'un appel autorisée à son auteur pendant `[48 h]`, puis réservée à la vie scolaire/direction.
- **RG-PRE-08** Les totaux (nombre d'absences, d'absences non justifiées, de retards) sont calculés par période et affichés sur le bulletin.
- **RG-PRE-09** *(mis en œuvre le 17/10/2026)* L'appel concerne les élèves inscrits à la date (un élève parti n'y figure plus après son départ) ; chaque élève doit avoir une réponse. Refaire un appel le modifie et journalise les changements. Le dimanche est exclu.
- **RG-PRE-10** *(mis en œuvre)* Après le délai paramétré, seuls les utilisateurs ayant le droit de faire tous les appels (vie scolaire) ou de justifier (direction) peuvent modifier un appel. Un enseignant ne fait l'appel que des classes où il enseigne ou dont il est professeur principal.
- **RG-PRE-11** *(mis en œuvre)* Chaque nouvelle absence ou nouveau retard est notifié aux comptes parents liés à l'élève (notification interne, si le paramètre « Notifier les parents des absences » est actif) ; aucun SMS n'est simulé. Supprimer un justificatif rétablit les absences qu'il était seul à couvrir.

## RG-NOT — Notes et bulletins
- **RG-NOT-01** Une évaluation appartient à (classe, matière, période) et a un type `[devoir, interrogation, composition]`, une date, un barème (`[20]` par défaut) et un poids (`[1]`).
- **RG-NOT-02** Une note est comprise entre 0 et le barème, avec au plus `[2]` décimales. Elle est convertie sur 20 pour les calculs.
- **RG-NOT-03** Un élève peut être marqué **absent** à une évaluation (note vide) ; absent ≠ 0. Traitement dans la moyenne : **non compté** par défaut — **[QUESTION Q2]**.
- **RG-NOT-04** **Moyenne de matière** (période) = Σ(note/barème×20 × poids) / Σ(poids) sur les évaluations où l'élève a une note.
- **RG-NOT-05** **Moyenne générale** (période) = Σ(moyenne matière × coefficient) / Σ(coefficients des matières où l'élève a une moyenne).
- **RG-NOT-06** **Rang** : classement décroissant sur la moyenne générale à `[2]` décimales ; ex aequo → même rang, rang suivant sauté (1, 2, 2, 4).
- **RG-NOT-07** Arrondi d'affichage à `[2]` décimales ; les calculs intermédiaires ne sont pas arrondis.
- **RG-NOT-08** Moyenne annuelle (P2) : moyenne des périodes, éventuellement pondérée — **[QUESTION Q2]**.
- **RG-NOT-09** Cycle de vie d'une évaluation : `brouillon` (modifiable par l'enseignant) → `validée` (verrouillée). Seule la direction peut **déverrouiller**, avec motif journalisé.
- **RG-NOT-10** Publication : la direction publie les résultats d'une **classe pour une période** quand toutes les évaluations sont validées (ou en forçant, avec avertissement). Avant publication, rien n'est visible côté parent.
- **RG-NOT-11** Toute modification d'une note après validation (via déverrouillage) conserve l'ancienne valeur dans le journal.
- **RG-NOT-13** *(mis en œuvre le 17/10/2026)* Saisie : virgule ou point acceptés, 2 décimales au plus, 0 ≤ note ≤ barème ; une ligne vidée supprime la note. La date d'une évaluation doit être dans sa période. Validation possible seulement si chaque élève encore inscrit a une note ou est marqué absent. Saisie impossible si la période est clôturée ou si les résultats de la classe sont publiés pour la période. Le barème ne peut pas descendre sous une note déjà saisie ; une évaluation notée ne peut pas être supprimée.
- **RG-NOT-14** *(mis en œuvre)* Le rang tient compte de la moyenne arrondie au nombre de décimales paramétré (14,666 et 14,671 sont ex æquo à 2 décimales). Un élève sans aucune note n'est pas classé. Après un changement de classe, les notes de l'ancienne classe restent comptées dans la matière ; une matière suivie seulement dans l'ancienne classe garde le coefficient de celle-ci.
- **RG-NOT-15** *(mis en œuvre le 17/10/2026)* Publication par la direction (`bulletins.publier`) : seules les évaluations **validées** sont comptées. S'il reste des brouillons, la publication est refusée sauf choix explicite « publier quand même » (brouillons exclus, mention dans le journal). Chaque bulletin est un instantané figé (moyennes, rangs, mention, décision du conseil, appréciation, assiduité, lignes par matière avec moyenne/min/max de classe) ; modifier une note ensuite ne le change pas.
- **RG-NOT-16** *(mis en œuvre)* Correction : la direction retire la publication avec un motif journalisé (les familles ne voient plus le bulletin, les notes redeviennent corrigeables après déverrouillage), puis republie : nouvelle version du bulletin, anciennes et nouvelles moyennes/rangs journalisés, familles notifiées « bulletin corrigé ». Retrait impossible si la période est clôturée.
- **RG-NOT-17** *(mis en œuvre)* Appréciation générale et décision du conseil (félicitations, tableau d'honneur, avertissement… liste libre) saisies par la direction ou le professeur principal, figées à la publication. Sur la dernière période : moyenne annuelle = moyenne non pondérée des moyennes générales des périodes, avec rang annuel.
- **RG-NOT-12** Mentions proposées : ≥ 16 Très bien, ≥ 14 Bien, ≥ 12 Assez bien, ≥ 10 Passable, < 10 Insuffisant — **[QUESTION Q2]** (tableau d'honneur, avertissements…).

## RG-FIN — Frais et paiements
- **RG-FIN-01** Les montants sont stockés en **entiers dans l'unité monétaire** (ex. FCFA) — **[QUESTION Q5]** ; jamais de nombres flottants.
- **RG-FIN-02** Une **grille de frais** définit, pour une année et un niveau (et une catégorie si confirmé), le montant de chaque type de frais et son échéancier (tranches avec date limite, somme des tranches = montant).
- **RG-FIN-03** À l'inscription, les **frais dus** de l'élève sont générés à partir de la grille. Une **remise** (bourse, fratrie, personnel…) est un enregistrement distinct avec motif et auteur, jamais une modification silencieuse du montant.
- **RG-FIN-04** Un **paiement** est une transaction : montant > 0, date, mode `[espèces, Mobile Money, virement, chèque]`, référence externe facultative, payeur, encaisseur.
- **RG-FIN-05** Un paiement est **imputé** sur les frais dus dans l'ordre des échéances (la plus ancienne d'abord) sauf imputation manuelle ; un paiement ne peut pas dépasser le reste dû — **[QUESTION Q5]** (accepter les avances ?).
- **RG-FIN-06** **Solde** = Σ frais dus − Σ remises − Σ paiements **valides**. Il est toujours **calculé**.
- **RG-FIN-07** Chaque paiement reçoit un **numéro de reçu unique et séquentiel** `REC-AAAA-NNNNNN`, généré dans une transaction SQL avec verrou (pas de trou ni de doublon en concurrence).
- **RG-FIN-08** Un paiement n'est **jamais supprimé ni modifié**. Correction = **annulation** (motif obligatoire, droit `paiements.annuler`, date et auteur journalisés) puis nouveau paiement. Un reçu annulé reste consultable avec la mention « ANNULÉ ».
- **RG-FIN-09** Un élève est **en retard** si le cumul des tranches échues dépasse le cumul payé + remises.
- **RG-FIN-10** Un départ d'élève ne supprime pas ses dettes ; un éventuel remboursement est une opération P2.
- **RG-FIN-11** Clôture de caisse journalière (P2) : total des encaissements du jour par encaisseur et par mode.

- **RG-FIN-12** *(mis en œuvre le 17/10/2026)* Seuls les types de frais **obligatoires** sont facturés automatiquement à l'inscription ; les frais **facultatifs** (cantine, transport…) sont attribués élève par élève. Les frais facturés sont une copie de la grille : modifier la grille ensuite ne change pas les factures existantes. Un frais ne peut être retiré d'un élève que s'il n'a reçu aucun paiement ni aucune remise (même annulés). « Générer les frais manquants » ajoute à chaque élève inscrit les types obligatoires qu'il n'a pas encore, sans doublon.
- **RG-FIN-13** *(mis en œuvre)* Encaissement : montant entier > 0, date non future, référence obligatoire hors espèces, jamais au-delà du reste dû. L'inscription est verrouillée pendant l'opération (pas de double encaissement concurrent) ; si l'opération échoue, aucun numéro de reçu n'est consommé. Imputation automatique sur les échéances les plus anciennes, ou répartition manuelle dont la somme doit égaler le montant.
- **RG-FIN-14** *(mis en œuvre)* Annulation d'un paiement réservée à `paiements.annuler` (direction), d'une remise à `remises.accorder`, d'une dépense à `depenses.annuler` ; motif obligatoire, jamais de suppression. Une remise ne peut dépasser le reste à payer de sa ligne. Dépenses numérotées DEP-AAAA-NNNNN, rattachées à l'année scolaire couvrant leur date.

## RG-PAR — Espace parents
- **RG-PAR-01** Le compte parent est créé (ou activé) par l'établissement à partir d'un responsable légal ; identifiant : téléphone ou e-mail — **[QUESTION Q7]**.
- **RG-PAR-02** Un compte parent est lié à **un responsable**, lui-même lié à un ou plusieurs élèves. L'accès est accordé **uniquement** via ce lien (vérifié par Policy sur chaque requête).
- **RG-PAR-03** Le parent voit : notes et bulletins **publiés**, présences, frais/paiements/reçus. Aucune écriture.
- **RG-PAR-04** Le rattachement d'un parent à un élève est réservé au secrétariat et journalisé.
- **RG-PAR-05** Mot de passe initial temporaire, changement obligatoire à la première connexion.

- **RG-PAR-06** *(mis en œuvre le 17/10/2026)* L'espace famille et l'espace élève ne montrent que des informations publiées : bulletins dont la publication est en vigueur, notes des évaluations validées d'une classe publiée pour la période. Un retrait de publication les masque à nouveau.
- **RG-PAR-07** *(mis en œuvre)* L'élève voit ses résultats, ses absences et son emploi du temps, mais pas la situation financière (réservée aux responsables). Il se connecte avec son matricule ; son compte est créé par le secrétariat depuis son dossier (mot de passe temporaire). Chaque page vérifie côté serveur le lien responsable–élève ou l'identité de l'élève.

## RG-APP — Cours et devoirs *(mis en œuvre le 17/10/2026)*
- **RG-APP-01** Seul l'enseignant de l'affectation crée, modifie et publie les cours et devoirs de cette classe et matière. La direction et la vie scolaire (`rapports.scolaires`) consultent sans modifier.
- **RG-APP-02** Un cours ou un devoir non publié est invisible des élèves. Un cours ne peut être publié qu'avec au moins une ressource. La publication d'un devoir notifie les élèves et leurs responsables ayant un compte.
- **RG-APP-03** Fichiers : types autorisés (ressources : PDF, bureautique, images, audio, vidéo ; copies : PDF, Word, OpenDocument, texte, images), taille maximale paramétrée ; stockage privé, téléchargement après contrôle d'accès. Les liens n'acceptent que http(s).
- **RG-APP-04** Un élève ne voit que les cours et devoirs publiés de sa classe et **uniquement sa propre copie** ; l'enseignant du devoir et la direction voient toutes les copies.
- **RG-APP-05** Dépôt possible jusqu'à la date limite (remplacement autorisé, l'ancien fichier est supprimé) ; après, seulement si le devoir accepte le retard (dépôt marqué « en retard »). Un travail corrigé ne peut plus être remplacé. La remise en ligne peut être désactivée globalement (paramètre de l'établissement) ou par devoir ; l'enseignant note alors directement.
- **RG-APP-06** Les notes de devoirs sont un suivi pédagogique, distinct des évaluations du bulletin. Toute modification d'une note déjà attribuée est journalisée. Un devoir ayant des copies ou des notes ne peut plus être supprimé.

## RG-NOTIF — Annonces et notifications *(mis en œuvre le 17/10/2026)*
- **RG-NOTIF-01** Notifications internes uniquement (aucun SMS ni e-mail simulé) pour : absences et retards (selon le paramètre), bulletins publiés ou corrigés, nouveaux devoirs, annonces, échéances de paiement.
- **RG-NOTIF-02** Une annonce vise tout le monde, le personnel, les parents ou les élèves, éventuellement limitée à une classe (familles et élèves de cette classe). Les notifiés sont exactement ceux qui peuvent la lire. Une annonce programmée n'est visible et notifiée qu'à sa date (tâche `annonces:diffuser`, toutes les 15 minutes) ; une modification ne renvoie pas de notification. Publication réservée à `annonces.gerer`.
- **RG-NOTIF-03** Rappels d'échéances (tâche `finances:rappels`, chaque jour à 7 h) : un rappel « à venir » quand une échéance non réglée arrive dans le délai paramétré (`jours_rappel_echeance`), un rappel « dépassée » après la date. Chacun n'est envoyé qu'une fois par ligne de frais et par destinataire. Destinataires : responsables désignés payeurs, à défaut tous les responsables ayant un compte.
- **RG-NOTIF-04** Tableau de bord composé de blocs selon les droits (pilotage, vie scolaire, secrétariat, enseignement, comptabilité, administration, annonces) : l'administrateur technique ne voit aucune donnée financière ; parents et élèves sont dirigés vers leur espace. Les statistiques de résultats ne reprennent que des bulletins publiés.

## RG-SEC — Sécurité et traçabilité
- **RG-SEC-01** Actions journalisées : connexion échouée répétée, création/modification de compte et de rôles, validation/déverrouillage/publication des notes, paiements, remises, annulations, modification d'un appel validé, rattachement parent–élève, changement de paramètres.
- **RG-SEC-02** Le journal est en ajout seul (aucune interface de modification/suppression).
- **RG-SEC-03** Un utilisateur désactivé ne peut plus se connecter ; ses actions passées restent attribuées.
