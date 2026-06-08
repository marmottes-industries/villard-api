# Architecture

`villard-api` est une API REST/JSON-LD générée par **API Platform** à partir d'entités Doctrine. Elle est conçue pour être consommée par plusieurs clients indépendants (SPA Vue 3 aujourd'hui, mobile demain) sans état partagé côté serveur.

## Vue d'ensemble

```
                ┌───────────────────────────────┐
   HTTPS        │  Symfony 8.1 + API Platform   │
  ───────►     │  - Firewall JWT (/api/*)       │
                │  - CORS (nelmio_cors)          │
                │  - Sérialisation par groupes   │
                └──────────────┬────────────────┘
                               │ Doctrine ORM
                               ▼
                          ┌──────────┐
                          │ MariaDB  │
                          └──────────┘
```

## Principes structurants

### 1. Stateless

`config/packages/api_platform.yaml` impose `defaults.stateless: true`. Aucune session n'est créée côté serveur ; chaque requête porte son JWT. Les en-têtes `Vary: Content-Type, Authorization, Origin` permettent à des caches HTTP intermédiaires de différencier les réponses par client/utilisateur.

### 2. Authentification multi-clients

Le firewall `api` (cf. `config/packages/security.yaml`) est entièrement stateless et utilise LexikJWT. Le claim d'identité est l'**UUID** de l'utilisateur (`user_id_claim: uuid`) — un renommage de `username` n'invalide donc pas les tokens en circulation. Détails : [`authentication.md`](authentication.md).

### 3. Génération auto des endpoints

Chaque entité dans `src/Entity/` portant `#[ApiResource]` génère automatiquement le CRUD REST (`GET`, `POST`, `PUT`, `PATCH`, `DELETE`) sous `/api/<resource>`. Les opérations sont sécurisées une par une via les attributs `security:` et `securityPostDenormalize:`.

### 4. Sérialisation contrôlée

API Platform expose **tout** par défaut. Pour éviter les fuites, l'entité `User` utilise des **groupes de sérialisation** :
- `user:read` pour la normalisation (sortie),
- `user:write` pour la dénormalisation (entrée).

Le `password` n'est dans aucun groupe → non exposable. Les autres entités (Category, InventoryItem, ShoppingItem, Note, Occupation) n'ont **pas encore** de groupes définis : tous leurs champs scalaires sont lus/écrits par défaut. C'est un point d'évolution prévu.

### 5. Formats supportés

| Header | Format en sortie |
|--------|------------------|
| `Accept: application/ld+json` *(défaut)* | JSON-LD + Hydra (pagination, `@id`, `@type`, `hydra:member`, etc.) |
| `Accept: application/json` | JSON pur (tableau d'objets en collection) |

Pour `PATCH`, **seul** `application/merge-patch+json` est accepté (cf. `patch_formats` dans `api_platform.yaml`).

## Arborescence du code

```
src/
├── ApiResource/        # Ressources API découplées des entités Doctrine (vide pour l'instant)
├── Command/            # Commandes console
│   └── CreateUserCommand.php       # app:create-user
├── Controller/         # Vide — tout est généré par API Platform
├── DataFixtures/       # Fixtures dev/test
│   └── AppFixtures.php
├── Entity/             # Entités Doctrine = ressources API Platform
│   ├── Category.php
│   ├── InventoryItem.php
│   ├── Note.php
│   ├── Occupation.php
│   ├── ShoppingItem.php
│   └── User.php
├── Enum/
│   └── State.php       # État d'un InventoryItem (ok / worn / replace)
├── Repository/         # Repos Doctrine générés
├── State/
│   └── MeProvider.php  # Provider API Platform pour GET /api/me
└── Kernel.php
```

```
config/
├── bundles.php
├── jwt/                # private.pem + public.pem (non versionnés)
├── packages/
│   ├── api_platform.yaml          # stateless, formats, Swagger JWT api_key
│   ├── doctrine.yaml              # mapping attribut sur src/Entity
│   ├── lexik_jwt_authentication.yaml
│   ├── nelmio_cors.yaml           # origin_regex via env CORS_ALLOW_ORIGIN
│   ├── security.yaml              # firewalls login + api, providers, access_control
│   └── ...
└── routes.yaml         # /api/login route POST + auto-import des contrôleurs
```

## Conception multi-clients

L'API est volontairement le seul point de vérité. Conséquences :

- **Pas de breaking changes côté serveur** : si un client (front, mobile) a besoin d'un nouveau champ, on l'ajoute en mode rétrocompatible (champ nullable, nouveau endpoint, nouveau groupe de sérialisation), on ne casse pas l'existant.
- **`Vary` strict** : les en-têtes de cache HTTP différencient les réponses par utilisateur (`Authorization`) et par client (`Origin`, `Content-Type`).
- **CORS souple en dev, strict en prod** : `CORS_ALLOW_ORIGIN` est une regex pilotée par l'env (en dev : `^https?://(localhost|127\.0\.0\.1)(:[0-9]+)?$`). À adapter en `.env.prod.local` pour les domaines réels.

## Évolutions prévues

- Groupes de sérialisation sur les ressources autres que `User` (limite l'exposition par défaut).
- Nettoyage périodique des refresh tokens expirés (`gesdinet:jwt:clear`) — à câbler en cron une fois la mise en ligne effectuée.
