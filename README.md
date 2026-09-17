# toDoList

Application **Symfony 8.1** + **Tailwind CSS 4**, base de données **MySQL 8.4** dans Docker.

---

## 1. Prérequis

| Outil | Version | Vérifier avec |
|---|---|---|
| PHP | ≥ 8.4 | `php -v` |
| Composer | 2.x | `composer -V` |
| Symfony CLI | dernière | `symfony version` |
| Docker | démarré | `docker info` |

Extensions PHP nécessaires : `ctype`, `iconv`, `pdo_mysql`, `intl`.

> **Node.js n'est pas nécessaire.** Le bundle `symfonycasts/tailwind-bundle`
> télécharge automatiquement le binaire Tailwind standalone.

Pour tout vérifier d'un coup :

```bash
symfony check:requirements
```

---

## 2. Installation (première fois)

```bash
# 1. Récupérer le projet
git clone <url-du-depot> toDoList
cd toDoList

# 2. Démarrer la base de données
docker compose up -d

# 3. Installer les dépendances PHP (+ le binaire Tailwind)
composer install

# 4. Créer les tables
php bin/console doctrine:migrations:migrate

# 5. Compiler le CSS une première fois
php bin/console tailwind:build
```

Si `docker compose up -d` vient de créer le conteneur, attends ~15 s que MySQL
finisse son initialisation avant l'étape 4. Pour vérifier :

```bash
docker compose ps        # la colonne STATUS doit afficher "healthy"
```

---

## 3. Lancer le projet au quotidien

Trois commandes, dans **deux terminaux** séparés à la racine du projet :

```bash
# une seule fois par session de travail
docker compose up -d
```

```bash
# terminal 1 — recompile le CSS à chaque modification de template
php bin/console tailwind:build --watch
```

```bash
# terminal 2 — serveur web
symfony serve
```

L'application est disponible sur **https://localhost:8000**.

> `symfony serve -d` lance le serveur en arrière-plan.
> `symfony server:stop` l'arrête, `symfony server:log` affiche ses logs.

### Arrêter

```bash
# Ctrl+C dans les deux terminaux, puis :
docker compose stop      # arrête MySQL, garde les données
```

---

## 4. Configuration

Toute la config d'infrastructure vit dans `.env` (versionné). Les identifiants
de la base y sont alignés sur `compose.yaml` :

| Paramètre | Valeur |
|---|---|
| Hôte | `127.0.0.1` |
| Port | `3306` |
| Base | `app` |
| Utilisateur / mot de passe | `app` / `app` |
| Mot de passe root | `app` |

```dotenv
DATABASE_URL="mysql://app:app@127.0.0.1:3306/app?serverVersion=8.4.0&charset=utf8mb4"
```

**Ne modifie pas `.env` pour tes réglages personnels.** Crée un fichier
`.env.local` (ignoré par git) et n'y mets que les lignes à surcharger :

```dotenv
# .env.local
DATABASE_URL="mysql://app:app@127.0.0.1:3307/app?serverVersion=8.4.0&charset=utf8mb4"
```

### Fichiers Docker

- `compose.yaml` — définit le service `database` (image, identifiants, volume,
  healthcheck).
- `compose.override.yaml` — publie le port `3306` sur la machine hôte. Chargé
  automatiquement, valable uniquement en local.

Les données MySQL sont dans le volume Docker `todolist_database_data`, elles
survivent à un `docker compose stop` / `down`.

---

## 5. Commandes utiles

```bash
# Base de données
php bin/console dbal:run-sql "SELECT 1"          # tester la connexion
php bin/console make:entity                      # créer / modifier une entité
php bin/console make:migration                   # générer une migration
php bin/console doctrine:migrations:migrate      # appliquer les migrations
php bin/console doctrine:schema:validate         # entités ↔ schéma en phase ?

# Assets
php bin/console tailwind:build --watch            # CSS en mode surveillance
php bin/console asset-map:compile                 # build de production

# Divers
php bin/console cache:clear
php bin/console debug:router
docker compose logs -f database                   # logs MySQL
```

---

## 6. Problèmes fréquents

**`SQLSTATE[HY000] [1045] Access denied for user 'app'`**
Le mot de passe de `DATABASE_URL` ne correspond pas à `MYSQL_PASSWORD` dans
`compose.yaml`. Vérifie aussi qu'aucun `DATABASE_URL` ne traîne dans
`.env.local` ou `.env.dev` : ces fichiers écrasent `.env`.

**`SQLSTATE[HY000] [2002] Connection refused`**
Le conteneur n'est pas démarré (`docker compose up -d`) ou MySQL finit encore
son initialisation. `docker compose ps` doit afficher `healthy`.

**`Ports are not available: bind 0.0.0.0:3306`**
Un MySQL local (WAMP, Laragon, service Windows) occupe déjà le port. Soit tu
l'arrêtes, soit tu changes le mapping dans `compose.override.yaml` en
`"3307:3306"` et le port dans `DATABASE_URL`.

**Le CSS ne se met pas à jour**
`php bin/console tailwind:build` doit tourner. Le fichier généré
`assets/styles/tailwind-built.css` ne doit pas être édité à la main.

**`composer install` refuse d'installer (contrainte `php >=8.4`)**
Ta version de PHP est trop ancienne. Mets PHP à jour ; n'utilise
`--ignore-platform-reqs` qu'en dernier recours, ça produit une installation
qui plantera à l'exécution.

**Après un `git pull`**

```bash
composer install
php bin/console doctrine:migrations:migrate
php bin/console tailwind:build
```

---

## 7. Structure

```
assets/            JS (Stimulus / Turbo) et CSS source
config/            configuration Symfony et des bundles
migrations/        migrations Doctrine
public/            racine web (index.php)
src/               code applicatif (Controller, Entity, Repository, Form…)
templates/         vues Twig
tests/             tests PHPUnit
compose.yaml       service MySQL
.env               variables d'environnement par défaut
```
