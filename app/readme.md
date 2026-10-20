# PROJET : EDUGESTION — APPLICATION INTÉGRÉE DE GESTION SCOLAIRE

## 1. TON RÔLE

Tu es un développeur Laravel senior, architecte logiciel, analyste fonctionnel et concepteur de bases de données relationnelles.

Tu es chargé de concevoir et de développer une application web professionnelle appelée provisoirement **EduGestion**, destinée aux établissements scolaires : écoles primaires, collèges et lycées.

Tu dois travailler comme dans un véritable projet informatique professionnel : analyse des besoins, conception UML, architecture, développement progressif, tests, documentation et préparation au déploiement.

Ne te contente pas de produire des exemples de code ou des maquettes statiques. Tu dois travailler directement dans le projet local, créer les fichiers nécessaires, exécuter les commandes disponibles, tester le fonctionnement et corriger les erreurs constatées.

## 2. CONTEXTE DU PROJET

Les établissements scolaires doivent gérer de nombreuses activités :

- Les inscriptions et les dossiers des élèves.
- Les classes, les niveaux et les années scolaires.
- Les enseignants, les matières et les emplois du temps.
- Les notes, les moyennes et les bulletins.
- Les présences, les absences et les retards.
- Les frais de scolarité et les paiements.
- Les reçus, les dépenses et les rapports financiers.
- La communication entre l'administration, les enseignants, les parents et les élèves.
- Les cours, les exercices et les devoirs en ligne.

L'objectif est de regrouper ces activités dans une seule application web, avec des modules intégrés et des droits d'accès adaptés à chaque utilisateur.

## 3. TECHNOLOGIES IMPOSÉES

Utilise les technologies suivantes :

- Backend : Laravel et PHP.
- Base de données : MySQL.
- Environnement local : XAMPP sous Windows.
- Frontend : Blade, HTML, CSS et JavaScript.
- Framework CSS : Bootstrap, sauf si le projet existant utilise déjà une solution cohérente qu'il est préférable de conserver.
- Gestion de version : Git si disponible.
- Tests : PHPUnit ou Pest selon la configuration du projet.
- Authentification : solution compatible avec la version de Laravel installée.
- Génération de documents : solution adaptée à la génération de bulletins, reçus et rapports PDF.

Avant de commencer, vérifie les versions de PHP, Composer, Laravel et MySQL disponibles.

Choisis une version de Laravel compatible avec l'environnement installé. N'installe pas aveuglément une version qui nécessiterait une mise à niveau risquée de l'environnement.

N'utilise pas PostgreSQL, MongoDB ou une autre base de données à la place de MySQL.

Le projet doit fonctionner localement avec XAMPP et pouvoir être déployé ultérieurement sur un hébergement compatible avec Laravel.

## 4. ARCHITECTURE ET MODE DE DISTRIBUTION

L'application sera installée séparément pour chaque établissement scolaire.

Chaque installation disposera de sa propre base de données et de ses propres fichiers de configuration.

Il ne s'agit pas, dans cette première version, d'une plateforme SaaS multitenant.

Prévois néanmoins une entité Établissement pour enregistrer le nom, le logo, les coordonnées, les paramètres scolaires et les informations apparaissant sur les documents.

L'architecture doit rester modulaire et maintenable. Utilise une application Laravel organisée autour des domaines métier, sans microservices inutiles.

Sépare clairement les responsabilités entre :

- Modèles Eloquent.
- Contrôleurs.
- Services métier lorsque la logique le justifie.
- Form Requests pour les validations.
- Policies et middleware pour les autorisations.
- Vues Blade.
- Migrations, seeders et factories.
- Tests automatisés.

Évite les contrôleurs gigantesques, les requêtes SQL dispersées et les règles métier dupliquées dans les vues.

## 5. LES CINQ MODULES OBLIGATOIRES

### MODULE 1 — GESTION SCOLAIRE

Développe les fonctionnalités suivantes :

1. Gestion des années scolaires.
2. Gestion des niveaux : primaire, collège et lycée.
3. Gestion des classes.
4. Gestion des élèves.
5. Gestion des inscriptions et réinscriptions.
6. Gestion des dossiers scolaires.
7. Gestion des responsables légaux.
8. Gestion des enseignants.
9. Gestion des matières.
10. Gestion des affectations des enseignants aux classes et matières.
11. Gestion des périodes scolaires.
12. Gestion des évaluations.
13. Saisie et modification contrôlée des notes.
14. Calcul des moyennes et des résultats.
15. Gestion des coefficients et des barèmes.
16. Génération et impression des bulletins.
17. Gestion des présences, absences et retards.
18. Gestion des emplois du temps.
19. Recherche, filtres, pagination et export des données autorisées.

Les années scolaires, périodes, coefficients et règles de calcul doivent être configurables.

Ne suppose pas que toutes les écoles utilisent les mêmes règles de notation.

Les calculs de moyennes doivent être centralisés dans des services métier testables.

Un bulletin publié doit pouvoir être verrouillé. Sa modification ultérieure doit être réservée aux utilisateurs autorisés et être historisée.

### MODULE 2 — ESPACE PARENTS ET ÉLÈVES

Développe un espace sécurisé permettant :

- Aux parents de consulter les informations de leurs enfants.
- Aux parents de consulter les notes et bulletins publiés.
- Aux parents de suivre les absences et retards.
- Aux parents de consulter les frais de scolarité, les paiements et les soldes.
- Aux parents de consulter les annonces.
- Aux élèves de consulter leurs résultats.
- Aux élèves d'accéder aux cours et aux devoirs autorisés.
- Aux élèves de consulter leur emploi du temps.
- Aux élèves de remettre leurs devoirs lorsque cette fonctionnalité est activée.

Un parent peut être associé à plusieurs enfants.

Un élève peut être associé à plusieurs responsables légaux.

La relation parent-enfant doit être explicitement enregistrée et vérifiée côté serveur. Il ne suffit jamais de cacher des informations dans l'interface.

Un parent ne doit jamais pouvoir accéder aux données d'un enfant qui ne lui est pas associé.

Un élève ne doit accéder qu'à ses propres informations.

### MODULE 3 — GESTION DES ENSEIGNANTS

Développe les fonctionnalités suivantes :

- Création et gestion des profils enseignants.
- Gestion des matières enseignées.
- Affectation aux classes et matières.
- Gestion des emplois du temps.
- Consultation des listes d'élèves des classes attribuées.
- Saisie des notes des évaluations autorisées.
- Gestion des présences et absences.
- Publication de cours et de documents.
- Création de devoirs.
- Consultation et correction des travaux remis par les élèves.
- Suivi des activités pédagogiques.

Un enseignant ne peut agir que dans le périmètre de ses affectations et des autorisations accordées par l'établissement.

### MODULE 4 — PLATEFORME D'APPRENTISSAGE

Développe un espace pédagogique comprenant :

- Création de cours.
- Publication de chapitres et de ressources.
- Ajout de documents PDF et d'autres formats autorisés.
- Gestion des exercices.
- Création de devoirs avec dates limites.
- Dépôt des travaux par les élèves.
- Consultation et téléchargement des ressources autorisées.
- Correction et notation des devoirs.
- Suivi de la remise des travaux.
- Historique des activités pédagogiques.

Les fichiers doivent être validés par leur type, leur taille et les règles de sécurité définies.

Stocke les documents privés dans un espace non accessible directement sans autorisation.

Les élèves ne doivent pas pouvoir consulter les travaux privés d'autres élèves.

Prévois une architecture permettant d'ajouter ultérieurement des quiz interactifs et des évaluations en ligne.

### MODULE 5 — GESTION FINANCIÈRE

Développe les fonctionnalités suivantes :

1. Configuration des frais d'inscription.
2. Configuration des frais de scolarité.
3. Gestion des autres frais éventuels.
4. Définition des échéances.
5. Attribution des frais aux élèves selon les règles de l'établissement.
6. Enregistrement des paiements complets ou partiels.
7. Gestion des différents modes de paiement.
8. Génération de reçus avec références uniques.
9. Consultation de l'historique des paiements.
10. Calcul du solde restant dû.
11. Liste des élèves en retard de paiement.
12. Gestion des dépenses de l'établissement.
13. Catégorisation des dépenses.
14. Rapports financiers.
15. Export des états financiers autorisés.
16. Historique des corrections et annulations.

Ne confonds pas une inscription scolaire avec un paiement.

Un élève peut être inscrit même si ses frais ne sont pas entièrement réglés, lorsque les règles de l'établissement le permettent.

Le montant restant dû doit être calculé à partir des frais applicables, des éventuelles réductions autorisées et des paiements valides.

Utilise des transactions de base de données pour les opérations financières sensibles.

Un paiement validé ne doit jamais être supprimé silencieusement. Prévois un mécanisme d'annulation ou de correction autorisé, traçable et cohérent.

Les références de paiement et de reçu doivent être uniques.

N'intègre aucun moyen de paiement en ligne sans spécification explicite. Prépare une architecture extensible pour une future intégration de services comme FedaPay.

## 6. UTILISATEURS ET AUTORISATIONS

Prévois au minimum les rôles suivants :

### Administrateur technique
- Configure l'application.
- Gère les comptes et les rôles dans le périmètre autorisé.
- Configure les paramètres techniques.
- Accède aux fonctions d'administration technique autorisées.

### Directeur
- Consulte les tableaux de bord.
- Supervise les activités scolaires.
- Consulte les rapports.
- Autorise certaines opérations selon les règles définies.
- Consulte les rapports financiers autorisés.

### Secrétaire
- Gère les dossiers des élèves.
- Effectue les inscriptions et réinscriptions.
- Gère les informations administratives.
- Prépare les documents scolaires autorisés.

### Censeur ou surveillant
- Suit les classes.
- Gère les présences, absences et retards.
- Consulte les informations scolaires nécessaires à ses fonctions.

### Enseignant
- Consulte ses classes et matières attribuées.
- Saisit les notes autorisées.
- Gère les présences de ses classes.
- Publie les cours et devoirs autorisés.

### Comptable ou caissier
- Consulte les frais applicables.
- Enregistre les paiements.
- Génère les reçus.
- Consulte les soldes.
- Enregistre les dépenses si son rôle le permet.
- Produit les rapports financiers autorisés.

### Parent
- Consulte uniquement les informations de ses enfants.
- Consulte les paiements et les soldes.
- Consulte les résultats et les bulletins publiés.

### Élève
- Consulte ses résultats et bulletins publiés.
- Consulte ses cours et devoirs.
- Remet ses travaux lorsque cela est autorisé.

Implémente les rôles et permissions avec des mécanismes explicites, en utilisant des Policies, Gates et middleware Laravel.

N'accorde pas automatiquement toutes les permissions à un utilisateur simplement parce qu'il est connecté.

Ne donne pas automatiquement à l'administrateur technique le droit de modifier des notes ou des opérations financières sans règle explicite.

## 7. TABLEAU DE BORD

Crée un tableau de bord adapté à chaque rôle.

### Direction
- Nombre d'élèves.
- Nombre d'enseignants.
- Nombre de classes.
- Effectifs par classe.
- Statistiques de présence.
- Recettes encaissées sur une période sélectionnée.
- Soldes restant dus.
- Statistiques scolaires.

### Secrétariat
- Inscriptions récentes.
- Dossiers incomplets.
- Recherche rapide d'élèves.
- Réinscriptions à traiter.

### Enseignant
- Classes attribuées.
- Matières enseignées.
- Évaluations à compléter.
- Présences à enregistrer.
- Devoirs à corriger.

### Comptabilité
- Paiements enregistrés.
- Recettes par période.
- Soldes et impayés.
- Dépenses.
- Reçus récents.

### Parent
- Informations de ses enfants.
- Derniers résultats publiés.
- Absences.
- Situation financière.
- Annonces récentes.

### Élève
- Résultats.
- Emploi du temps.
- Cours disponibles.
- Devoirs à rendre.

Les tableaux de bord doivent utiliser de vraies données issues de MySQL et respecter les autorisations.

Ne remplis pas l'application de statistiques fictives en production.

## 8. NOTIFICATIONS

Prévois un système de notifications internes pour :

- La publication des bulletins.
- Les nouvelles annonces.
- Les devoirs à rendre.
- Les absences, selon les paramètres de l'établissement.
- Les échéances de paiement, selon les règles définies.

L'envoi de SMS et les notifications via des services externes pourront être ajoutés ultérieurement.

Ne simule pas un envoi réel de SMS si aucun fournisseur n'est configuré.

## 9. CONCEPTION UML OBLIGATOIRE

Avant de développer les modules, réalise et documente les diagrammes suivants :

1. Diagramme de cas d'utilisation général.
2. Diagrammes de cas d'utilisation détaillés pour les cinq modules.
3. Diagramme de classes complet.
4. Diagrammes de séquence pour :
   - L'authentification.
   - L'inscription d'un élève.
   - La saisie et la publication des notes.
   - L'enregistrement d'un paiement.
   - La consultation des résultats par un parent.
   - La remise d'un devoir par un élève.
5. Diagrammes d'activité pour :
   - L'inscription d'un élève.
   - La génération d'un bulletin.
   - Le paiement des frais.
   - La remise et la correction d'un devoir.
6. Modèle conceptuel des données.
7. Modèle logique des données.
8. Schéma relationnel final.

Utilise Mermaid ou PlantUML pour produire les diagrammes en fichiers sources modifiables.

Enregistre la documentation dans un dossier `docs/uml/`.

Les diagrammes doivent être cohérents avec les fonctionnalités réellement implémentées.

## 10. CONCEPTION DE LA BASE DE DONNÉES

Conçois une base MySQL relationnelle normalisée.

Étudie notamment les entités suivantes :

- Etablissement.
- Utilisateur.
- Role et Permission, ou les tables d'une solution d'autorisations adaptée.
- Eleve.
- Parent.
- Relation entre les parents et les élèves.
- Enseignant.
- AnneeScolaire.
- PeriodeScolaire.
- Niveau.
- Classe.
- Inscription.
- Matiere.
- AffectationEnseignant.
- Evaluation.
- Note.
- Bulletin.
- Presence.
- EmploiDuTemps.
- FraisScolarite.
- Echeance.
- Paiement.
- Depense.
- Cours.
- RessourcePedagogique.
- Devoir.
- RemiseDevoir.
- Notification.
- JournalAudit.

Cette liste constitue une base de réflexion. Analyse les relations et ajoute ou regroupe les entités lorsque cela est justifié.

Définis les clés primaires, les clés étrangères, les contraintes d'unicité, les index et les règles d'intégrité référentielle.

Utilise des types adaptés aux montants financiers et aux dates.

Ne stocke pas les mots de passe en clair.

Ne duplique pas inutilement les informations calculables.

Les soldes et moyennes doivent être calculés à partir de données cohérentes, avec une stratégie explicite pour les valeurs publiées ou historisées.

Prévois les migrations, les factories et les seeders nécessaires.

## 11. INTERFACE UTILISATEUR

Crée une interface professionnelle, claire, moderne et responsive.

Prévois :

- Une page de connexion.
- Une navigation adaptée au rôle connecté.
- Un menu latéral cohérent.
- Des tableaux avec recherche, filtres et pagination.
- Des formulaires avec validation et messages d'erreur explicites.
- Des confirmations avant les opérations sensibles.
- Des notifications de succès ou d'échec.
- Des pages de détail pour les élèves, enseignants, paiements et devoirs.
- Des documents imprimables : bulletins, reçus et listes autorisées.
- Des interfaces adaptées aux écrans mobiles.

Tous les boutons et liens doivent fonctionner.

Ne crée pas de boutons purement décoratifs ni de pages contenant seulement des données statiques.

Utilise des composants Blade réutilisables et conserve une cohérence visuelle sur l'ensemble du projet.

La langue initiale de l'interface sera le français.

La devise et les paramètres scolaires doivent être configurables.

## 12. SÉCURITÉ

La sécurité est obligatoire dès la conception.

Implémente notamment :

- Le hachage sécurisé des mots de passe.
- La protection CSRF.
- La validation serveur de toutes les entrées.
- Les protections fournies par Eloquent et le framework contre les injections SQL.
- Les politiques d'autorisation.
- La protection contre les accès directs aux dossiers non autorisés.
- La vérification des relations parent-enfant.
- La validation des fichiers téléversés.
- La protection des documents privés.
- La journalisation des opérations sensibles.
- La gestion correcte des sessions.
- Des sauvegardes et une procédure de restauration documentées.

Ne te contente pas de masquer les boutons dans le frontend pour protéger une action.

Vérifie les permissions sur les routes et dans la logique métier.

Ne publie jamais de clés secrètes ou de mots de passe dans le dépôt Git.

## 13. TESTS OBLIGATOIRES

Écris et exécute des tests pour vérifier au minimum :

- La connexion et la déconnexion.
- Les rôles et permissions.
- La création et la modification des élèves.
- Les inscriptions et réinscriptions.
- Les calculs des moyennes.
- Les limites des barèmes.
- La publication des bulletins.
- L'enregistrement des paiements.
- Le calcul des soldes.
- Les corrections et annulations financières.
- Les accès des parents à leurs enfants.
- Les restrictions appliquées aux enseignants.
- La remise et la correction des devoirs.
- La protection des documents privés.

Ajoute des tests de non-régression pour les règles métier critiques.

Utilise une base de test distincte et ne lance aucune commande destructive sur la base de données réelle ou locale existante sans vérifier son contenu et obtenir mon accord.

Ne prétends pas qu'un test a réussi si tu ne l'as pas réellement exécuté.

## 14. MÉTHODE DE TRAVAIL IMPOSÉE

Travaille par phases.

### PHASE 1 — AUDIT ET ANALYSE
- Examine le dossier courant.
- Vérifie si un projet Laravel existe déjà.
- Vérifie les versions des outils.
- Identifie les fichiers présents.
- N'écrase aucun fichier existant sans raison.
- Présente les décisions d'architecture et les éventuels risques.

### PHASE 2 — CONCEPTION
- Rédige le cahier des charges fonctionnel.
- Documente les acteurs et les permissions.
- Produis les diagrammes UML.
- Conçois la base de données.
- Définis les règles de gestion.
- Prépare un plan de développement ordonné.

### PHASE 3 — FONDATIONS TECHNIQUES
- Configure Laravel et MySQL.
- Configure l'authentification.
- Crée les rôles et permissions.
- Crée la configuration de l'établissement.
- Prépare les layouts Blade.
- Crée les migrations et les premiers tests.

### PHASE 4 — DÉVELOPPEMENT PROGRESSIF
Développe dans cet ordre :

1. Authentification, rôles et permissions.
2. Établissement, années scolaires et paramètres.
3. Élèves, parents, classes et inscriptions.
4. Enseignants, matières et affectations.
5. Présences, notes et bulletins.
6. Frais, paiements, reçus et dépenses.
7. Espaces parents et élèves.
8. Cours, ressources et devoirs.
9. Notifications, tableaux de bord et rapports.

À chaque étape :
- Implémente la fonctionnalité.
- Exécute les migrations sur la base de test appropriée.
- Lance les tests.
- Corrige les erreurs.
- Vérifie les autorisations.
- Mets à jour la documentation.
- Indique ce qui est terminé et ce qui reste à faire.

### PHASE 5 — VALIDATION FINALE
- Vérifie les parcours utilisateurs.
- Vérifie les calculs.
- Vérifie la sécurité des accès.
- Vérifie les relations de la base de données.
- Vérifie les documents générés.
- Exécute la suite de tests.
- Documente l'installation et le déploiement.
- Prépare des données de démonstration clairement identifiées comme fictives.

## 15. DOCUMENTATION ATTENDUE

Crée et maintiens les fichiers suivants :

- `README.md` : présentation et installation.
- `docs/cahier-des-charges.md` : cahier des charges fonctionnel.
- `docs/architecture.md` : architecture technique.
- `docs/roles-permissions.md` : matrice des permissions.
- `docs/uml/` : diagrammes UML.
- `docs/base-de-donnees.md` : structure et relations de la base.
- `docs/installation-xampp.md` : installation sous Windows avec XAMPP.
- `docs/tests.md` : procédures de test.
- `docs/deploiement.md` : procédure de déploiement.
- `.env.example` : exemple de configuration sans secrets.

Documente les commandes nécessaires pour créer la base de données, exécuter les migrations, créer un administrateur et démarrer l'application.

## 16. CRITÈRES DE RÉUSSITE

Le projet ne sera pas considéré comme terminé simplement parce que les pages existent.

Il devra satisfaire les conditions suivantes :

- L'application démarre correctement.
- La connexion fonctionne.
- Les permissions sont effectivement vérifiées.
- Les opérations principales enregistrent les données dans MySQL.
- Les notes et moyennes sont calculées correctement selon les règles configurées.
- Les paiements et soldes sont cohérents.
- Les reçus et bulletins peuvent être générés.
- Les parents ne peuvent pas accéder aux informations d'autres élèves.
- Les enseignants ne peuvent pas modifier des données hors de leurs autorisations.
- Les documents privés sont protégés.
- Les tests critiques ont été exécutés et leurs résultats sont documentés.
- La procédure d'installation est reproductible.

## 17. CONSIGNES FINALES

Commence par analyser le projet local et présenter un plan d'action concret.

Ne génère pas immédiatement tout le code dans un seul bloc.

Ne crée pas toutes les migrations sans avoir clarifié le modèle de données.

Ne supprime pas et ne remplace pas des fichiers existants sans inspection.

Ne déclare jamais une fonctionnalité terminée tant qu'elle n'a pas été vérifiée.

Privilégie une solution simple, fiable, sécurisée et maintenable plutôt qu'une architecture inutilement complexe.

Si un point fonctionnel essentiel est ambigu, formule une question précise avant de prendre une décision irréversible. Pour les détails non bloquants, propose une valeur par défaut raisonnable et documente-la.

Ta première réponse doit contenir :
1. Le résultat de l'audit du dossier et de l'environnement.
2. Les principales décisions d'architecture.
3. Les ambiguïtés fonctionnelles à résoudre.
4. Le plan de réalisation.
5. Les premières étapes concrètes.

Ensuite, attends ma validation du plan avant d'entreprendre des modifications structurelles importantes.

L'objectif final est de produire une véritable application de gestion scolaire utilisable, documentée, testée et prête à être déployée dans un établissement.