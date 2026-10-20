# Installation sous Windows avec XAMPP

Procédure vérifiée sur Windows 11, XAMPP (PHP 8.2, MariaDB 10.4), Node.js 24.

## 1. Prérequis

| Outil | Version | Vérification |
|---|---|---|
| XAMPP | PHP ≥ 8.2 | `php -v` |
| Composer | 2.x | `composer -V` |
| Node.js et npm | Node ≥ 20 | `node -v` |

Dans `C:\xampp\php\php.ini`, vérifiez que ces lignes **ne commencent pas** par `;` puis redémarrez Apache :

```ini
extension=pdo_mysql
extension=mbstring
extension=fileinfo
extension=zip
extension=gd      ; nécessaire pour afficher le logo sur les PDF (bulletins, reçus)
extension=intl    ; recommandé
```

Sans `gd`, l'application fonctionne mais le logo n'apparaît pas sur les PDF.

## 2. Récupérer le projet

Placez le dossier dans `C:\xampp\htdocs\gestion-scolaire`, puis dans un terminal ouvert dans ce dossier :

```bash
composer install
npm install
npm run build
copy .env.example .env
php artisan key:generate
```

## 3. Créer la base de données

1. Démarrez **Apache** et **MySQL** dans le panneau de contrôle XAMPP.
2. Créez la base (phpMyAdmin → « Nouvelle base de données », interclassement `utf8mb4_unicode_ci`), ou en ligne de commande :

```bash
C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE `gestion-ecole` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
```

3. Vérifiez dans `.env` :

```ini
APP_URL=http://localhost/gestion-scolaire/public
APP_TIMEZONE=Africa/Lome
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=gestion-ecole
DB_USERNAME=root
DB_PASSWORD=
SAUVEGARDE_MYSQLDUMP=C:\xampp\mysql\bin\mysqldump.exe
```

`APP_URL` doit correspondre à l'adresse réellement utilisée : il sert aux liens des notifications créées par les tâches planifiées.

## 4. Créer les tables et les données de référence

```bash
php artisan migrate --seed
```

Cette commande crée les tables, les niveaux (CP1 à Terminale), les matières, les types de frais, les catégories de dépenses, et les 8 rôles avec leurs permissions. Aucune donnée fictive n'est créée.

## 5. Créer l'administrateur

```bash
php artisan ecole:creer-admin
```

La commande demande le nom, l'e-mail et le mot de passe (8 caractères minimum, avec lettres et chiffres).

**Mot de passe administrateur oublié** : `php artisan tinker`, puis :
```php
App\Models\User::where('email', 'admin@exemple.tg')->first()->forceFill(['password' => 'NouveauMotDePasse2026', 'doit_changer_mot_de_passe' => true])->save();
```

## 6. Démarrer et se connecter

- Avec Apache (XAMPP) : **http://localhost/gestion-scolaire/public**
- Ou avec le serveur intégré : `php artisan serve`, puis **http://127.0.0.1:8000** (dans ce cas, mettez `APP_URL=http://127.0.0.1:8000`).

Premiers pas de l'administrateur :
1. **Administration > Établissement** : nom, logo, devise, paramètres de notation, d'appel et de paiement.
2. **Administration > Années et périodes** : créer l'année (découpage en trimestres ou semestres), puis l'**activer**.
3. **Administration > Utilisateurs** : créer les comptes du personnel (directeur, secrétaire, censeur, comptable, enseignants). Un mot de passe temporaire s'affiche une seule fois ; chacun le change à sa première connexion.
4. **Scolarité > Classes**, puis le secrétariat inscrit les élèves ; la comptabilité configure les frais (**Finances > Frais et échéances**).

## 7. Activer les tâches planifiées

Les rappels d'échéances, la diffusion des annonces programmées et la sauvegarde nocturne exigent que le planificateur tourne **chaque minute**.

Avec le **Planificateur de tâches Windows** :
1. « Créer une tâche… », nom : `EduGestion - planificateur`, cochez « Exécuter même si l'utilisateur n'est pas connecté ».
2. Déclencheur : « À l'ouverture de session » (ou « Au démarrage »), **répéter toutes les 1 minute** pendant **indéfiniment**.
3. Action : programme `C:\xampp\php\php.exe`, arguments `artisan schedule:run`, démarrer dans `C:\xampp\htdocs\gestion-scolaire`.

Vérification : `php artisan schedule:list` affiche les trois tâches et leur prochaine exécution.

## 8. Base de démonstration (facultatif)

Pour explorer l'application avec des données **entièrement fictives**, sans toucher à votre base :

```bash
php artisan ecole:demo
```

La commande crée la base séparée `gestion-ecole-demo` (le nom doit contenir « demo ») et affiche les comptes de démonstration (mot de passe `Demo2026`). Pour l'utiliser, mettez `DB_DATABASE=gestion-ecole-demo` dans `.env`, exécutez `php artisan config:clear`, puis revenez à `gestion-ecole` de la même façon.

| Rôle | Identifiant |
|---|---|
| Administrateur technique | `admin@demo.test` |
| Directeur | `direction@demo.test` |
| Secrétaire | `secretariat@demo.test` |
| Censeur | `censeur@demo.test` |
| Comptable | `comptabilite@demo.test` |
| Enseignant de maths (professeur principal 6e A) | `prof.math@demo.test` |
| Parent (6e A) | `90000001` (téléphone) |
| Élève (6e A) | son matricule, affiché par la commande (ex. `2026-00001`) |

## 9. Problèmes fréquents

| Symptôme | Solution |
|---|---|
| « SQLSTATE[HY000] [2002] … refusée » | MySQL n'est pas démarré dans XAMPP |
| Une migration reste bloquée | Arrêtez puis redémarrez MySQL dans XAMPP et relancez `php artisan migrate` |
| Page sans mise en forme | `npm run build` n'a pas été exécuté |
| « 419 Page expirée » | Session expirée : rechargez la page et recommencez |
| Liens des notifications vers une mauvaise adresse | Corrigez `APP_URL`, puis `php artisan config:clear` |
| Logo absent des PDF | Activez `extension=gd` dans `php.ini` et redémarrez Apache |
