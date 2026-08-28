# Déploiement sur Railway

Ce guide décrit comment déployer E-Ticketing Sénégal sur [Railway](https://railway.com) : un service **web** (Nginx + PHP-FPM, servant l'app Laravel/Inertia) et un service **worker** (`php artisan queue:work`, pour `GenerateTicketsJob` et `SendTicketNotificationJob`), tous deux construits depuis le même `Dockerfile`.

## Fichiers concernés

| Fichier | Rôle |
|---|---|
| `Dockerfile` | Build multi-étapes : Composer → Vite (Node) → image runtime [`serversideup/php:8.3-fpm-nginx`](https://serversideup.net/open-source/docker-php/) |
| `.dockerignore` | Exclut `.env`, `node_modules`, `vendor`, les tests, etc. du build |
| `docker/entrypoint.d/40-app-deploy.sh` | Script exécuté à **chaque** démarrage de conteneur (web *et* worker), via le mécanisme `entrypoint.d` natif de l'image : `config:cache` + `view:cache` |
| `railway.toml` | Config Railway du service **web** — inclut `preDeployCommand` pour les migrations |
| `railway.worker.toml` | Config Railway du service **worker** (voir plus bas — nécessite une étape manuelle) |
| `.env.production.example` | Liste de référence des variables d'environnement à définir sur Railway |

### Pourquoi `serversideup/php:8.3-fpm-nginx`

Les deux versions précédentes du `Dockerfile` (image `php:8.3-fpm-alpine` avec compilation d'extensions via `docker-php-ext-install`) provoquaient des timeouts de build sur Railway. `serversideup/php` fournit PHP-FPM, Nginx et la supervision de process (S6 Overlay) **dans une seule image déjà construite**, avec un jeu d'extensions PHP précompilées — plus aucune compilation depuis les sources dans l'image runtime, sauf pour l'unique extension qu'il manque à ce projet.

### Extensions PHP dans l'image runtime

Confirmé contre la doc officielle ([Default Configurations](https://serversideup.net/open-source/docker-php/docs/getting-started/default-configurations#default-php-extensions)), l'image `serversideup/php:8.3-fpm-nginx` inclut déjà :

- ses propres ajouts : `opcache`, `pcntl`, `pdo_mysql`, `pdo_pgsql`, `redis`, `zip`
- fournies par les images PHP officielles sous-jacentes : `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `hash`, `mbstring`, `openssl`, `pcre`, `session`, `tokenizer`, `xml`

Cela couvre déjà `mbstring` (requis en dur par `laravel/framework`), `pdo_mysql` (connexion base de données) et `pcntl` (arrêt propre du worker de queue). Il ne manque que :

| Extension | Pourquoi | Installée via |
|---|---|---|
| `gd` | `QrCodeGenerator`/`endroid/qr-code` (`extension_loaded('gd')` vérifié en dur) | `install-php-extensions gd` (outil `mlocati/docker-php-extension-installer`, inclus dans l'image) |

`intl`, `bcmath` et `zip`-au-sens-applicatif restent inutiles ici pour les mêmes raisons que documentées précédemment (formatage de dates via les traductions de Carbon, calculs de commission en floats arrondis, aucune fonctionnalité ZIP dans l'app) — `zip` est en fait déjà présent par défaut dans cette image sans coût supplémentaire, simplement non utilisé par le code actuel.

OPcache est **désactivé par défaut** sur cette image (contrairement à l'ancienne) : le `Dockerfile` le réactive explicitement via `ENV PHP_OPCACHE_ENABLE=1`, substituable au runtime avec une variable Railway du même nom si besoin de le désactiver temporairement pour du debug.

## ⚠️ Avant de lire la suite : deux points qui contredisent la demande initiale

Je documente ces deux écarts explicitement plutôt que de les cacher, car ils changent concrètement la configuration à faire sur Railway.

### 1. La variable s'appelle `MYSQL_URL`, pas `DATABASE_URL`

Le plugin MySQL de Railway injecte automatiquement `MYSQLHOST`, `MYSQLPORT`, `MYSQLUSER`, `MYSQLPASSWORD`, `MYSQLDATABASE` et une URL de connexion complète nommée **`MYSQL_URL`** — pas `DATABASE_URL` (cette convention-là vient de Heroku/Postgres). Par ailleurs, `config/database.php` de ce projet lit l'URL de connexion depuis **`DB_URL`**, pas `DATABASE_URL` non plus.

La configuration correcte sur Railway (voir étape 4 ci-dessous) est donc :

```
DB_CONNECTION=mysql
DB_URL=${{MySQL.MYSQL_URL}}
```

où `MySQL` est le nom exact que vous avez donné au service MySQL dans votre projet Railway (`${{ServiceName.VARIABLE}}` est la syntaxe de référence entre services de Railway).

### 2. Un seul `railway.toml` ne peut pas définir deux services

Railway a supprimé cette possibilité : chaque fichier de config-as-code (`railway.toml`/`railway.json`) décrit **un seul** service déployable — il n'existe pas de syntaxe `[[services]]` pour en définir plusieurs dans le même fichier. C'est pourquoi il y a deux fichiers (`railway.toml` et `railway.worker.toml`) : vous créerez deux services Railway distincts à partir du même dépôt, et le second devra être pointé manuellement vers `railway.worker.toml` dans son onglet Settings (étape 3 ci-dessous). Railway note aussi que le config-as-code par fichier est en cours de dépréciation au profit d'un futur `.railway/railway.ts` (Infrastructure as Code) — `railway.toml` reste pleinement fonctionnel aujourd'hui, mais gardez ça en tête si vous revisitez cette configuration plus tard.

## Étape 0 — Publier le dépôt sur GitHub

Le dépôt Git local existe déjà (premier commit fait), mais rien n'est encore poussé sur GitHub — Railway se connecte à un dépôt GitHub, pas à votre machine.

**Option A — avec la CLI `gh` (si installée et authentifiée) :**

```bash
gh repo create e-ticketing-senegal --private --source=. --remote=origin --push
```

Cette seule commande crée le dépôt sur votre compte GitHub, ajoute le remote `origin` et pousse la branche `main` en une fois. Utilisez `--public` à la place de `--private` si vous voulez un dépôt public (déconseillé tant que `.env.production.example` ou l'historique pourraient évoluer avec des valeurs sensibles collées par erreur — vérifiez toujours avant de rendre public).

**Option B — depuis github.com :**

1. [github.com/new](https://github.com/new) → nommez le dépôt (ex. `e-ticketing-senegal`) → **ne cochez aucune case d'initialisation** (pas de README/`.gitignore`/licence — le dépôt local en a déjà) → **Create repository**.
2. Copiez l'URL affichée (HTTPS ou SSH), puis dans le projet local :

```bash
git remote add origin <URL-du-dépôt>
git branch -M main
git push -u origin main
```

**Vérification** : `git remote -v` doit afficher `origin` pointant vers votre dépôt GitHub, et le dépôt sur github.com doit lister les mêmes fichiers que localement (447 fichiers au premier commit).

Une fois poussé, revenez à l'étape 1 : c'est ce dépôt GitHub que vous connecterez à Railway.

## Étape 1 — Créer le projet et la base de données

1. Sur [railway.com](https://railway.com), **New Project** → **Deploy from GitHub repo** → sélectionnez ce dépôt.
2. Dans le même projet, **+ New** → **Database** → **Add MySQL**. Notez le nom du service créé (par défaut `MySQL`) — vous le réutiliserez dans les variables du service web/worker.

## Étape 2 — Le service web

Le premier service créé par Railway à partir du dépôt utilisera automatiquement `railway.toml` (à la racine) : builder `DOCKERFILE`, healthcheck sur `/up` (la route de santé intégrée à Laravel, déjà enregistrée dans `bootstrap/app.php`).

Renommez-le en `web` (Settings → nom du service) pour s'y retrouver.

## Étape 3 — Le service worker

1. Dans le même projet, **+ New** → **GitHub Repo** → le même dépôt.
2. Renommez-le en `worker`.
3. Onglet **Settings** → section **Config-as-code** (ou **Build**) → **Config File Path** → entrez `railway.worker.toml`.
   > Railway précise que ce chemin doit être **absolu par rapport à la racine du dépôt** et ne suit pas un éventuel "Root Directory" — `railway.worker.toml` (sans `./`) suffit puisque le fichier est à la racine.
4. Vérifiez dans l'onglet **Deploy** que la commande de démarrage effective est bien `php artisan queue:work ...` (celle définie dans `railway.worker.toml`) et non le `CMD` par défaut de l'image (nginx + php-fpm).

Le worker construit exactement la même image Docker que le web (même `Dockerfile`), mais son `startCommand` remplace le `CMD` de l'image : il ne lance ni Nginx ni PHP-FPM, uniquement `php artisan queue:work`. Le script `docker/entrypoint.d/40-app-deploy.sh` (config/view cache) s'exécute quand même avant, sur les deux services — c'est sans risque, ces commandes sont idempotentes. Les migrations, elles, ne tournent que côté web (`preDeployCommand` dans `railway.toml`, pas de `preDeployCommand` dans `railway.worker.toml`) — c'est voulu, pour éviter que les deux services ne les exécutent en parallèle à chaque déploiement.

## Étape 4 — Variables d'environnement

Ouvrez `.env.production.example` à la racine du dépôt : il liste toutes les variables utilisées par l'application avec des commentaires. Collez-les dans l'onglet **Variables** de **chaque** service (web et worker ont besoin des mêmes valeurs, à l'exception de `APP_URL`/`SANCTUM_STATEFUL_DOMAINS` qui n'ont de sens que côté web).

Points obligatoires avant le premier déploiement :

```
APP_KEY=                          # php artisan key:generate --show (en LOCAL, une seule fois)
APP_URL=https://<domaine-railway-ou-personnalisé>
DB_CONNECTION=mysql
DB_URL=${{MySQL.MYSQL_URL}}       # adaptez "MySQL" au nom réel du service
APP_TICKET_SECRET=                # php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"
PAYMENT_ENABLED=false             # tant que WAVE_SECRET_KEY / WAVE_WEBHOOK_SECRET ne sont pas renseignés
```

`APP_KEY` ne doit **jamais** être régénérée à chaque déploiement (aucun script de ce projet ne le fait) : la changer invalide toutes les sessions actives et rend illisibles les colonnes chiffrées existantes.

## Étape 5 — Premier déploiement

Pour le service **web**, Railway construit l'image puis, avant de router le trafic vers la nouvelle version :

1. Exécute `preDeployCommand` (`railway.toml`) : `php artisan migrate --force`, une seule fois pour ce déploiement.
2. Démarre le conteneur : l'entrypoint natif de `serversideup/php` exécute d'abord ses scripts internes (config Nginx, certificat SSL auto-signé, etc.) puis `docker/entrypoint.d/40-app-deploy.sh` (`config:cache` + `view:cache`), avant de lancer PHP-FPM et Nginx.
   > Pas de `route:cache` : `routes/api.php` a une route en Closure héritée du scaffolding Sanctum (`GET /user`), et `route:cache` échoue systématiquement dès qu'une route n'est pas liée à un contrôleur. Sans risque à ce nombre de routes — le cache ne devient vraiment utile qu'à partir de centaines de routes. C'est aussi pour cette raison qu'`AUTORUN_ENABLED` (le mécanisme d'automatisation intégré à l'image, qui lancerait `route:cache` par défaut) n'est **volontairement pas activé** — ne définissez pas cette variable sur Railway.

Le service **worker** passe directement par `docker/entrypoint.d/40-app-deploy.sh` (même cache, sans risque à relancer) puis démarre `php artisan queue:work` — pas de migrations de son côté, il réutilise la base déjà migrée par le web.

Suivez les logs de build/déploiement dans l'onglet **Deployments** de chaque service. Une fois le service web « Active » avec un healthcheck vert sur `/up`, ouvrez `APP_URL` dans un navigateur.

## ⚠️ Stockage persistant — à régler avant d'accepter de vrais paiements

Les conteneurs Railway sont **éphémères** par défaut (pas de disque partagé entre déploiements, ni entre services). Or cette application écrit sur `storage/app/public` (disque `local`) à deux endroits distincts :

- le service **web** : images de couverture d'événement uploadées depuis le back-office admin ;
- le service **worker** : QR codes générés par `GenerateTicketsJob` (`TicketDownloadResource`, PDF de billet, email de confirmation).

Comme web et worker sont **deux conteneurs séparés**, un fichier écrit par le worker (QR code) n'existe pas sur le disque du web — les liens `/storage/tickets/*.png` renverront une 404 en production dès qu'un billet est généré. Un redéploiement efface aussi tout ce qui a été uploadé entre-temps (images de couverture y compris), qu'il y ait un ou deux services.

**Recommandation** : basculer `FILESYSTEM_DISK` sur un stockage objet compatible S3 (Cloudflare R2, AWS S3, Backblaze B2...) avant la mise en production réelle — Laravel le supporte nativement via le driver `s3` (`composer require league/flysystem-aws-s3-v3`, puis `FILESYSTEM_DISK=s3` + les variables `AWS_*`/`AWS_URL`/`AWS_ENDPOINT` déjà présentes dans `config/filesystems.php`). Ce n'est pas fait automatiquement ici : cela demande un compte/bucket et des identifiants que vous seul pouvez fournir. Tant que ce n'est pas en place, gardez `PAYMENT_ENABLED=false` (mode billet gratuit) pour tester le parcours sans dépendre de fichiers qui vont disparaître.

## Le webhook Wave

Une fois `APP_URL` connu et `PAYMENT_ENABLED=true`, configurez chez Wave for Business l'URL de webhook :

```
https://<votre-domaine>/api/v1/webhooks/wave
```

`WAVE_WEBHOOK_SECRET` doit correspondre à celui fourni par Wave pour signer ses requêtes (vérifié par `WavePaymentGateway::verifyWebhookSignature()`).

## Dépannage

- **Le healthcheck `/up` échoue / 502** : vérifiez d'abord l'onglet **Deployments** → le `preDeployCommand` (migrations) — s'il échoue (base non accessible), le déploiement ne démarre jamais le conteneur. Si le pré-déploiement passe, regardez les logs du conteneur lui-même pour `docker/entrypoint.d/40-app-deploy.sh` puis le démarrage de PHP-FPM/Nginx par `serversideup/php`.
- **`env()` renvoie toujours `null` en PHP alors que la variable est bien définie sur Railway** : le piège classique PHP-FPM + Docker (`clear_env`) est déjà géré par la configuration par défaut de l'image `serversideup/php` — si ça se reproduit, vérifiez que la variable est bien définie sur le bon service (web **et** worker ont besoin de leurs propres copies, voir étape 4).
- **Erreur de connexion MySQL** : confirmez que `DB_URL` référence bien `${{<NomDuServiceMySQL>.MYSQL_URL}}` et que ce nom correspond exactement à celui affiché dans l'onglet du service MySQL (sensible à la casse).
- **Le worker ne redémarre pas après une heure** : normal si `restartPolicyType` a été changé — `railway.worker.toml` doit rester sur `ALWAYS` (pas `ON_FAILURE`), car `--max-time=3600` termine le process avec le code de sortie `0`, qu'`ON_FAILURE` ne relance pas.
- **Upload d'image refusé (413)** : la limite par défaut de `serversideup/php` est `100M` côté Nginx (`NGINX_CLIENT_MAX_BODY_SIZE`) et PHP (`PHP_UPLOAD_MAX_FILE_SIZE`/`PHP_POST_MAX_SIZE`) — largement au-dessus de la validation `max:2048` (2 Mo) du formulaire admin, donc ce message viendrait d'ailleurs (proxy externe, taille réelle du fichier) plutôt que de cette limite.
- **Build qui échoue sur `install-php-extensions gd`** : vérifiez les logs de build — cet outil résout et installe lui-même les dépendances système de `gd` (libpng, etc.), donc un échec ici est généralement un problème réseau transitoire du builder Railway plutôt qu'une dépendance manquante à ajouter à la main.
