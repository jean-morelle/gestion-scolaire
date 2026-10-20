# Déploiement, sauvegarde et restauration

Une installation correspond à **un établissement** : son code, son `.env`, sa base et son utilisateur MySQL. Deux scénarios sont possibles : un poste ou serveur local dans l'établissement (XAMPP, voir [`installation-xampp.md`](installation-xampp.md)), ou un serveur Linux (VPS) accessible par Internet, décrit ci-dessous.

## 1. Serveur Linux (VPS) — mise en production

Prérequis : PHP 8.2+ (extensions `pdo_mysql`, `mbstring`, `xml`, `zip`, `gd`, `intl`, `fileinfo`), MySQL 8 ou MariaDB ≥ 10.4, Nginx ou Apache, Composer, Node.js (pour compiler les assets), certificat HTTPS (Let's Encrypt).

```bash
# 1. Base et utilisateur dédiés (jamais root en production)
mysql -u root -p -e "CREATE DATABASE gestion_ecole CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  CREATE USER 'ecole'@'localhost' IDENTIFIED BY 'mot-de-passe-long-et-unique';
  GRANT ALL PRIVILEGES ON gestion_ecole.* TO 'ecole'@'localhost';"

# 2. Code et dépendances
cd /var/www/gestion-scolaire
composer install --no-dev --optimize-autoloader
npm ci && npm run build
cp .env.example .env && php artisan key:generate
```

Dans `.env` :
```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://ecole.exemple.tg
APP_TIMEZONE=Africa/Lome
DB_DATABASE=gestion_ecole
DB_USERNAME=ecole
DB_PASSWORD=mot-de-passe-long-et-unique
SESSION_SECURE_COOKIE=true
SAUVEGARDE_MYSQLDUMP=/usr/bin/mysqldump
```

```bash
# 3. Tables, référentiel, administrateur
php artisan migrate --seed --force
php artisan ecole:creer-admin

# 4. Optimisations
php artisan config:cache && php artisan route:cache && php artisan view:cache

# 5. Droits : seul le serveur web écrit dans storage/ et bootstrap/cache/
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
```

Le serveur web doit pointer sur le dossier **`public/`** uniquement : `storage/` (fichiers privés, sauvegardes) n'est jamais exposé.

Planificateur (crontab de l'utilisateur web) :
```cron
* * * * * cd /var/www/gestion-scolaire && php artisan schedule:run >> /dev/null 2>&1
```

## 2. Mise à jour de l'application

```bash
php artisan down
php artisan ecole:sauvegarder           # toujours sauvegarder avant
git pull                                 # ou copie de la nouvelle version
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan up
```

## 3. Sauvegarde

```bash
php artisan ecole:sauvegarder
```

- Produit `storage/app/sauvegardes/sauvegarde-AAAA-MM-JJ_HHMMSS.zip`, qui contient `base.sql` (export complet par `mysqldump --single-transaction`, sans interruption du service) et `fichiers/` (photos, pièces des dossiers, justificatifs, ressources de cours, copies, pièces de dépenses).
- Option `--sans-fichiers` : base seule.
- Exécutée automatiquement **chaque nuit à 2 h** par le planificateur ; les archives de plus de `SAUVEGARDE_JOURS` jours (30 par défaut) sont supprimées.
- Chaque sauvegarde est inscrite au journal d'audit.
- **Copie externe obligatoire** : une sauvegarde restée sur le même disque ne protège ni d'une panne ni d'un vol. Copiez chaque semaine le dossier `storage/app/sauvegardes` sur un disque USB ou un stockage en ligne. Les archives contiennent des données personnelles d'élèves : conservez-les dans un lieu protégé.

## 4. Restauration

Procédure testée le 17/10/2026 : une archive restaurée dans une base vierge redonne utilisateurs, migrations et rôles.

1. Mettre l'application en maintenance : `php artisan down`.
2. Extraire l'archive (clic droit → « Extraire tout » sous Windows, ou `unzip sauvegarde-….zip -d restauration`).
3. Recréer la base vide puis importer :
   ```bash
   # Windows / XAMPP
   C:\xampp\mysql\bin\mysql.exe -u root -e "DROP DATABASE IF EXISTS `gestion-ecole`; CREATE DATABASE `gestion-ecole` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
   C:\xampp\mysql\bin\mysql.exe -u root gestion-ecole < restauration\base.sql
   # Linux
   mysql -u ecole -p gestion_ecole < restauration/base.sql
   ```
   ⚠ `DROP DATABASE` efface la base actuelle : en cas de doute, restaurez d'abord dans une base de test (par exemple `gestion-ecole-restauration`) et vérifiez son contenu.
4. Restaurer les fichiers privés : copier le contenu de `restauration/fichiers/` dans `storage/app/private/`.
5. `php artisan migrate --force` (applique les migrations éventuellement plus récentes que la sauvegarde), puis `php artisan up`.
6. Vérifier : connexion, quelques fiches élèves avec leur photo, un bulletin PDF, un reçu.

## 5. Sécurité en production — liste de contrôle

- [ ] `APP_DEBUG=false`, `APP_ENV=production`, HTTPS actif, `SESSION_SECURE_COOKIE=true`
- [ ] Utilisateur MySQL dédié avec mot de passe fort ; MySQL non exposé sur Internet
- [ ] Racine web = `public/` ; `.env` illisible depuis le web
- [ ] Planificateur actif (`php artisan schedule:list`)
- [ ] Sauvegarde nocturne vérifiée et copie externe hebdomadaire
- [ ] Comptes inutilisés désactivés (Administration > Utilisateurs) ; mot de passe administrateur unique
- [ ] Déclaration éventuelle du traitement de données de mineurs auprès de l'autorité nationale de protection des données (question Q12 du cahier des charges, à vérifier par GKAS)
