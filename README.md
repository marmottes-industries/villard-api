# Les Marmottes — API backend (`villard-api`)

Application personnelle et auto-hébergée de gestion d'un appartement familial : planning d'occupation (qui occupe le logement et quand) et suivi d'inventaire (linge, vaisselle, équipement, courses récurrentes, état des lieux).

Ce dépôt contient **uniquement le backend API**. La SPA Vue 3 vit dans un dépôt séparé (`appart-front`) ; un client mobile pourra être ajouté ultérieurement sans modification serveur.

Projet à double objectif : usage réel et auto-hébergement (ownership des données, sortie progressive des services GAFAM), tout en servant de pièce de portfolio démontrant une architecture découplée moderne.

> **Note vie privée** : ce dépôt public ne contient aucune donnée personnelle (adresse, noms, contenu réel). Seeds et fixtures utilisent des données fictives.

---

## Stack

- **PHP 8.4+** / **Symfony 8.1**
- **API Platform 4.3** — exposition REST automatique des entités Doctrine, mode stateless
- **Doctrine ORM 3** — mapping attribut sur `src/Entity/`
- **MariaDB** (dev, via Docker Compose)
- **LexikJWTAuthenticationBundle 3** — authentification JWT signée RSA
- **nelmio/cors-bundle** — CORS configurable par environnement
- **DoctrineFixturesBundle** (dev/test) — données fictives prêtes à l'emploi

---

## Architecture en deux dépôts

```
                 ┌─────────────────────┐
                 │ villard-api         │ ← ce dépôt
                 │ Symfony 8 + API     │
                 │ Platform (JSON)     │
                 └──────────┬──────────┘
                            │
            ┌───────────────┼───────────────┐
            │               │               │
     ┌──────┴──────┐ ┌──────┴──────┐ ┌──────┴────────┐
     │  appart-    │ │  mobile     │ │  (futurs      │
     │  front      │ │  (à venir)  │ │   clients)    │
     │  (Vue 3)    │ │             │ │               │
     └─────────────┘ └─────────────┘ └───────────────┘
```

Deux dépôts plutôt qu'un monorepo : l'API et le front se déploient, se versionnent et se testent séparément. Le client mobile (à venir) consommera la même API sans modification côté backend.

---

## Démarrage rapide

Procédure détaillée : [`docs/getting-started.md`](docs/getting-started.md).

```bash
git clone <url-du-depot> villard-api
cd villard-api
composer install

# 1. DB (auto-démarrée si tu utilises symfony server:start)
docker compose up -d

# 2. Schéma
php bin/console doctrine:migrations:migrate --no-interaction

# 3. Clés JWT (passphrase dans .env)
php bin/console lexik:jwt:generate-keypair

# 4. Données fictives (dev)
php bin/console doctrine:fixtures:load --no-interaction

# 5. Serveur web (lance Docker en parallèle via .symfony.local.yaml)
symfony server:start
```

Test rapide :

```bash
curl -k -X POST https://127.0.0.1:8000/api/login \
  -H 'Content-Type: application/json' \
  -d '{"username":"admin","password":"admin"}'
```

Documentation interactive Swagger : `https://127.0.0.1:8000/api/docs` (publique).

---

## Documentation

Toute la documentation technique vit dans [`docs/`](docs/) :

| Document | Contenu |
|----------|---------|
| [`docs/getting-started.md`](docs/getting-started.md) | Installation, prérequis, premiers pas. |
| [`docs/architecture.md`](docs/architecture.md) | API Platform, mode stateless, sérialisation, multi-clients. |
| [`docs/authentication.md`](docs/authentication.md) | JWT (LexikJWT), firewall, `/api/me`, rôles. |
| [`docs/resources.md`](docs/resources.md) | Ressources exposées : entités, opérations, sécurité, payloads. |
| [`docs/configuration.md`](docs/configuration.md) | Variables d'environnement, CORS, JWT, base de données. |
| [`docs/development.md`](docs/development.md) | Commandes Doctrine, fixtures, `app:create-user`, Swagger. |

Autres documents racine :

- [`API.md`](API.md) — référence API destinée à l'agent du dépôt front (`appart-front`).
- [`ROADMAP.md`](ROADMAP.md) — feuille de route projet (backend + front + déploiement).
- [`CLAUDE.md`](CLAUDE.md) — instructions pour l'agent Claude Code travaillant sur ce dépôt.

---

## Commandes courantes

```bash
# Schéma
php bin/console doctrine:migrations:diff           # nouvelle migration depuis les entités
php bin/console doctrine:migrations:migrate        # appliquer

# Fixtures (dev)
php bin/console doctrine:fixtures:load

# Utilisateurs
php bin/console app:create-user <username>             # admin par défaut
php bin/console app:create-user <username> --no-admin

# Outillage Symfony
php bin/console debug:router
php bin/console cache:clear
```

---

## Perspectives

- **Client mobile** — choix techno à arbitrer (Hotwire Native, React Native, Flutter, PWA). La même API sera consommée sans changement.
- **Notifications** — rappels (arrivée prochaine, courses) via Symfony Messenger + workers.
- **Multi-logements** — généralisation du modèle si d'autres biens s'ajoutent.
- **Refresh token JWT** — actuellement absent ; le client refait un `POST /api/login` à expiration.
- **Groupes de sérialisation** sur l'ensemble des entités (seul `User` les a aujourd'hui).

---

## Statut

Projet en cours de développement actif. Documentation et API évoluent au rythme du projet.
