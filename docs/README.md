# Documentation — Les Marmottes API

Documentation technique du backend `villard-api` (Symfony 8.1 + API Platform 4.x). Ce dossier rassemble tout ce qu'il faut pour comprendre, démarrer et faire évoluer l'API.

## Sommaire

| Fichier | Contenu |
|---------|---------|
| [getting-started.md](getting-started.md) | Installation locale, prérequis, premiers pas (Docker, migrations, fixtures, JWT). |
| [architecture.md](architecture.md) | Vue d'ensemble : API Platform, mode stateless, sérialisation, multi-clients. |
| [authentication.md](authentication.md) | Authentification JWT (LexikJWT) : firewall, login, `/api/me`, rôles, providers. |
| [resources.md](resources.md) | Référence des ressources exposées (entités, opérations, sécurité, groupes). |
| [configuration.md](configuration.md) | Variables d'environnement, CORS, base de données, JWT. |
| [development.md](development.md) | Commandes courantes, fixtures, migrations, commande `app:create-user`. |

## Documents complémentaires (racine du dépôt)

- [`README.md`](../README.md) — pitch projet, point d'entrée.
- [`API.md`](../API.md) — référence API destinée à l'agent du dépôt front (`villard-front`). Copie de travail à synchroniser quand l'API évolue.
- [`ROADMAP.md`](../ROADMAP.md) — feuille de route projet (backend + front + déploiement).
- [`CLAUDE.md`](../CLAUDE.md) — instructions pour l'agent Claude Code sur ce dépôt.

## Stack en un coup d'œil

- **PHP 8.4+** / **Symfony 8.1**
- **API Platform 4.3** (mode stateless, formats `application/json` et `application/ld+json`)
- **Doctrine ORM 3** avec mapping par attributs (`src/Entity/`)
- **MariaDB** (dev via Docker Compose)
- **LexikJWTAuthenticationBundle 3** pour l'auth stateless (JWT signé RSA)
- **nelmio/cors-bundle** pour le CORS
- **DoctrineFixturesBundle** (dev/test) pour les jeux de données fictifs
