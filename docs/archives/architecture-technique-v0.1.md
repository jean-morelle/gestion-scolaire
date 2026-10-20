# Architecture technique

> Version 0.1 — 12/10/2026

## 1. Environnement constaté (inspection du 12/10/2026)

| Élément | Constat |
|---|---|
| Projet | Squelette **Laravel 12.69.3** fraîchement installé (aucun code métier) |
| PHP | 8.2.12 (XAMPP) — extensions présentes : `pdo_mysql`, `pdo_sqlite`, `mbstring`, `openssl`, `fileinfo`, `zip` ; **absentes : `gd`, `intl`** |
| Base de données | **MariaDB 10.4.32** (XAMPP), base `gestion-ecole` (vide), connexion `root` en local |
| Front | Vite 7, Tailwind CSS 4 (`@tailwindcss/vite`), axios — dépendances npm **non installées** (`node_modules` absent) |
| Node / npm | 24.12.0 / 11.7.0 |
| Tests | PHPUnit 11 ; `phpunit.xml` utilise SQLite en mémoire ; 2 tests d'exemple passent |
| Authentification | Aucune (pas de starter kit installé) |
| `.env` | Basculé de SQLite vers MySQL `gestion-ecole`, `APP_LOCALE=fr`, `APP_FAKER_LOCALE=fr_FR` |

**Incident constaté** : la création de la première table (`migrations`) est restée bloquée dans MariaDB (verrou interne InnoDB, `row0ins.cc` / `dict0dict.cc`). Le serveur MySQL de XAMPP doit être redémarré avant toute migration ; si le blocage persiste, une réparation de l'instance MariaDB sera nécessaire.

## 2. Choix techniques

| Sujet | Choix | Statut |
|---|---|---|
| Framework | Laravel 12, PHP ≥ 8.2 | Existant |
| Architecture | MVC Laravel + **services métier** pour les opérations complexes | [PROPOSÉ] |
| Vues | Blade + composants Blade, Tailwind CSS 4, JavaScript natif léger (Alpine.js possible, voir ci-dessous) | [PROPOSÉ] |
| Authentification | **Laravel Breeze (pile Blade)** : code généré lisible, sans React/Vue/Livewire | [PROPOSÉ — à valider] |
| Rôles et permissions | **spatie/laravel-permission** (standard, testé, gère rôles cumulables et permissions) + Policies Laravel | [PROPOSÉ — à valider] |
| PDF (bulletins, reçus) | **barryvdh/laravel-dompdf** (nécessite l'extension `gd` pour les images/logo) | [PROPOSÉ — à valider] |
| Exports | CSV natif (aucune dépendance) ; Excel en P2 | [PROPOSÉ] |
| Tests | PHPUnit (déjà présent) | Existant |
| Journal d'activité | Table et service maison (simple, sans dépendance) | [PROPOSÉ] |

Alpine.js : Breeze l'inclut déjà pour les menus déroulants et les modales. Ce n'est pas un framework applicatif ; il évite d'écrire du JavaScript ad hoc. À valider avec Breeze.

**Pas de** : React, Vue, Livewire, API REST séparée, Repository Pattern, multi-tenant.

## 3. Organisation du code

```
app/
  Http/
    Controllers/
      Admin/        (Etablissement, AnneeScolaire, Periode, Utilisateur, Journal)
      Scolarite/    (Eleve, Responsable, Inscription, Classe, Niveau)
      Pedagogie/    (Enseignant, Matiere, Affectation, Evaluation, Note, Bulletin, Publication)
      VieScolaire/  (Appel, Justificatif)
      Finance/      (TypeFrais, GrilleFrais, Paiement, Recu, Remise, RapportFinancier)
      Parent/       (Enfant, ...)  — contrôleurs en lecture seule
      DashboardController.php
    Requests/       (une Form Request par création/mise à jour)
    Middleware/
  Models/           (modèles Eloquent, noms en français, ex. Eleve, Inscription, Paiement)
  Policies/         (une Policy par ressource sensible)
  Services/
    CalculMoyenneService.php
    InscriptionService.php
    PaiementService.php        (imputation, numérotation, annulation — transactions SQL)
    NumerotationService.php    (séquences de reçus/matricules avec verrou)
    JournalService.php
  Enums/            (StatutPresence, StatutEvaluation, ModePaiement, ...)
resources/views/
  components/       (boutons, champs, tableaux, badges, modale de confirmation, état vide)
  layouts/          (app = personnel, parent = espace parents, guest)
  <module>/...
routes/web.php      (groupes par rôle : middleware auth + permissions)
database/
  migrations/ seeders/ factories/
tests/
  Unit/ (calculs)  Feature/ (parcours, autorisations)
```

Conventions :
- Noms métier en **français** (tables, modèles, routes), code technique Laravel en anglais (méthodes standard).
- Enums PHP natifs pour les statuts, castés dans les modèles.
- Toute écriture financière passe par `PaiementService` dans `DB::transaction()` avec `lockForUpdate()`.
- Aucune requête SQL dans les vues ; calculs dans les services ; contrôleurs minces.

## 4. Autorisations

1. Middleware `auth` + `actif` (compte non désactivé) sur toutes les routes internes.
2. Middleware `permission:...` (spatie) au niveau des groupes de routes.
3. **Policies** sur chaque ressource (ex. `EvaluationPolicy::update` vérifie l'affectation de l'enseignant et le statut `brouillon` ; `ElevePolicy::view` vérifie, pour un parent, le lien responsable–élève).
4. Requêtes de listes filtrées côté serveur (un enseignant ne reçoit que ses classes).
5. Tests Feature systématiques des refus (403) pour chaque rôle.

## 5. Déploiement (une instance par établissement)

| Scénario | Description | Accès parents |
|---|---|---|
| **A. Serveur local** | PC/serveur dans l'établissement (XAMPP/Laragon sous Windows ou Apache/Nginx sous Linux) | Réseau local ; Internet possible via nom de domaine + redirection de port / tunnel, avec HTTPS |
| **B. VPS / serveur dédié** | Un petit VPS Linux par établissement (ou un serveur hébergeant plusieurs **installations séparées** : dossiers, bases et utilisateurs MySQL distincts) | Internet, HTTPS (Let's Encrypt) |

Le scénario B reste conforme à la contrainte « pas de SaaS » tant que chaque établissement a son code, son `.env`, sa base et son utilisateur MySQL dédiés.

Production :
- `APP_ENV=production`, `APP_DEBUG=false`, utilisateur MySQL dédié avec mot de passe fort (pas `root`).
- Racine web pointant sur `public/`.
- `php artisan config:cache route:cache view:cache`, assets compilés (`npm run build`).
- Tâches planifiées (`schedule:run` via cron / Planificateur de tâches Windows) pour les sauvegardes.

## 6. Sauvegarde et restauration
- Commande artisan `backup:base` (P1) : `mysqldump` compressé + archive de `storage/app/private`, horodatés, rotation `[30]` jours.
- Copie externe à la charge de l'établissement (disque USB, cloud) — procédure documentée.
- Restauration : documentation pas à pas + test de restauration réalisé avant mise en production.

## 7. Prérequis à corriger sur le poste de développement
1. Redémarrer MySQL dans le panneau XAMPP (blocage InnoDB constaté).
2. Activer `extension=gd` (et `extension=intl`, utile pour le formatage) dans `C:\xampp\php\php.ini`, puis redémarrer Apache.
3. `npm install` puis `npm run build` (ou `npm run dev`).
4. Créer une base de test MySQL `gestion-ecole-test` (facultatif mais recommandé pour tester les verrous et contraintes propres à MySQL).
