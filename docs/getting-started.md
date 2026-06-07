# Démarrage rapide

Procédure complète pour faire tourner l'API en local, du clone jusqu'aux premières requêtes authentifiées.

## Prérequis

| Outil | Version | Pourquoi |
|-------|---------|----------|
| PHP | **8.4+** | Imposé par `composer.json` (`"php": ">=8.4"`). |
| Composer | 2.x | Gestion des dépendances PHP. |
| Docker + Docker Compose | récent | Service MariaDB de dev. |
| Symfony CLI | dernière | `symfony server:start` (HTTPS local, auto-start Docker). |
| OpenSSL | — | Génération de la paire de clés JWT. |

> **Note** : le `README.md` historique mentionnait PostgreSQL — c'est une trace d'une version antérieure du projet. Le stack actuel est **MariaDB** (cf. `compose.yaml` et `DATABASE_URL` dans `.env`).

## 1. Cloner et installer les dépendances

```bash
git clone <url-du-depot> villard-api
cd villard-api
composer install
```

## 2. Démarrer la base de données

La Symfony CLI démarre automatiquement Docker grâce à `.symfony.local.yaml`. Soit :

```bash
symfony server:start          # démarre Docker (DB) + serveur web Symfony
```

Soit manuellement :

```bash
docker compose up -d          # MariaDB sur 127.0.0.1:3306
symfony serve -d              # serveur web sur https://127.0.0.1:8000
```

La configuration `compose.yaml` lance MariaDB (latest LTS). `compose.override.yaml` expose le port `3306` côté hôte et ajoute Mailpit (pour de futurs envois de mails dev).

Identifiants DB par défaut (cf. `compose.yaml`) :
- base : `db`
- user : `db`
- password : `db`
- root password : `root`

## 3. Appliquer les migrations

```bash
php bin/console doctrine:migrations:migrate --no-interaction
```

Les migrations vivent dans `migrations/` et reflètent l'historique du schéma (5 migrations à ce jour).

## 4. Générer la paire de clés JWT

Le bundle Lexik attend les clés à `config/jwt/private.pem` et `config/jwt/public.pem` (chemins définis dans `.env`). La passphrase par défaut est dans `.env` (`JWT_PASSPHRASE`) — **change-la en production**.

```bash
php bin/console lexik:jwt:generate-keypair
```

> Si la commande n'est pas disponible, génère manuellement avec OpenSSL ; voir [`authentication.md`](authentication.md#génération-des-clés).

## 5. Charger les fixtures (dev)

Les fixtures créent 6 utilisateurs (1 admin + 5 standards), 8 catégories, l'inventaire de l'appartement, une liste de courses, des occupations sur juin 2026 et des notes.

```bash
php bin/console doctrine:fixtures:load --no-interaction
```

Utilisateurs créés (mot de passe = username) :

| Username | Rôles | Mot de passe |
|----------|-------|--------------|
| `admin` | `ROLE_ADMIN` | `admin` |
| `antonin` | `ROLE_USER` | `antonin` |
| `sophie` | `ROLE_USER` | `sophie` |
| `pierre` | `ROLE_USER` | `pierre` |
| `marie` | `ROLE_USER` | `marie` |
| `lucas` | `ROLE_USER` | `lucas` |

> Ces credentials sont des **données de dev uniquement**. Ne pas charger les fixtures en production.

## 6. Vérifier que tout tourne

```bash
# Documentation Swagger UI (publique)
open https://127.0.0.1:8000/api/docs

# Login → récupérer un JWT
curl -k -X POST https://127.0.0.1:8000/api/login \
  -H 'Content-Type: application/json' \
  -d '{"username":"admin","password":"admin"}'

# Appeler une ressource protégée
TOKEN="<colle ici>"
curl -k https://127.0.0.1:8000/api/me \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Accept: application/json'
```

## Commandes utiles

```bash
# Cache
php bin/console cache:clear

# Nouvelle migration depuis les changements d'entités
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate

# Créer un utilisateur en CLI (admin par défaut, --no-admin pour un user simple)
php bin/console app:create-user <username>

# Lister les routes
php bin/console debug:router
```

## Étapes suivantes

- Lire [`architecture.md`](architecture.md) pour comprendre le découpage et la conception multi-clients.
- Lire [`resources.md`](resources.md) pour la référence des endpoints exposés.
- Lire [`authentication.md`](authentication.md) pour les détails du flux JWT et de la sécurité par opération.
