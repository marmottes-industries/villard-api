# Ressources API

Référence des entités exposées par API Platform. Toutes les routes sont préfixées par `/api`. La documentation interactive Swagger est disponible sur `GET /api/docs` (accès public).

> Pour un guide d'utilisation côté front (helpers fetch, conventions IRI, gestion des erreurs), voir [`API.md`](../API.md) à la racine du dépôt.

## Conventions

- **Pluralisation des URLs** : convention API Platform en snake_case anglais.
  `Category → /api/categories`, `InventoryItem → /api/inventory_items`, `ShoppingItem → /api/shopping_items`, `Note → /api/notes`, `Occupation → /api/occupations`, `Work → /api/works`, `User → /api/users`.
- **Identifiants URL** : `id` numérique auto-incrémenté (sauf l'UUID interne du `User`, utilisé pour le JWT mais pas dans les URLs).
- **Relations en écriture** : passer l'IRI (`"/api/categories/1"`), pas un id nu ni un objet imbriqué.
- **PATCH** : `Content-Type: application/merge-patch+json` obligatoire (sinon 415).

## Matrice des opérations

Chaque entité expose : `GET /collection`, `GET /{id}`, `POST /collection`, `PUT /{id}`, `PATCH /{id}`, `DELETE /{id}`.

| Ressource | GET | POST | PUT / PATCH | DELETE |
|-----------|-----|------|-------------|--------|
| **User** (`/api/users`) | `ROLE_USER` | `ROLE_ADMIN` | `ROLE_ADMIN` **ou** `object == user` | `ROLE_ADMIN` |
| **Category** (`/api/categories`) | `ROLE_USER` | `ROLE_USER` | `ROLE_USER` | `ROLE_USER` |
| **InventoryItem** (`/api/inventory_items`) | `ROLE_USER` | `ROLE_USER` | `ROLE_USER` | `ROLE_ADMIN` |
| **ShoppingItem** (`/api/shopping_items`) | `ROLE_USER` | `ROLE_USER` | `ROLE_USER` | `ROLE_ADMIN` |
| **Note** (`/api/notes`) | `ROLE_USER` | `ROLE_ADMIN` **ou** auteur = user courant | `ROLE_ADMIN` **ou** auteur (avant **et** après) = user courant | `ROLE_ADMIN` **ou** auteur = user courant |
| **Occupation** (`/api/occupations`) | `ROLE_USER` | `ROLE_ADMIN` **ou** occupant = user courant | `ROLE_ADMIN` **ou** occupant (avant **et** après) = user courant | `ROLE_ADMIN` **ou** occupant = user courant |
| **Work** (`/api/works`) | `ROLE_USER` | `ROLE_ADMIN` **ou** auteur = user courant | `ROLE_ADMIN` **ou** auteur (avant **et** après) = user courant | `ROLE_ADMIN` **ou** auteur = user courant |

> Les contrôles « avant et après » sur Note/Occupation/Work utilisent `securityPostDenormalize` : ils empêchent un utilisateur de réassigner un objet à un autre auteur/occupant (vérification simultanée de `object` et `previous_object`).

## Endpoints custom

### `GET /api/me`

Retourne l'utilisateur courant (sécurité `ROLE_USER`). Sérialisé avec le groupe `user:read`, IRI renvoyé sous la forme `/api/users/{id}`.

Implémenté via le provider `App\State\MeProvider` ; voir [`authentication.md`](authentication.md#lendpoint-apime).

### `POST /api/login`

Login JSON (cf. `routes.yaml` et le firewall `login`). Retourne `{ "token": "..." }`. Voir [`authentication.md`](authentication.md#flux-dutilisation).

## Détail des ressources

### User — `/api/users`

```php
// Identifiant URL : id (numérique). UUID interne pour le JWT.
```

| Champ | Type | Lecture (`user:read`) | Écriture (`user:write`) |
|-------|------|-----------------------|--------------------------|
| `id` | int | ✓ | — (auto) |
| `uuid` | string (UUID) | ✓ | — (auto à la création) |
| `username` | string | ✓ | ✓ |
| `roles` | string[] | ✓ | — |
| `password` | string (hashé) | — | — *(non exposé)* |

> **Conséquence** : impossible de définir/modifier le mot de passe via l'API. Utilise `php bin/console app:create-user`.

Relations (présentes en JSON-LD) : `occupations`, `notes`.

### Category — `/api/categories`

| Champ | Type | Notes |
|-------|------|-------|
| `id` | int | — |
| `name` | string (255) | — |
| `inventoryItems` | IRIs[] | inverse `OneToMany` |
| `shoppingItems` | IRIs[] | inverse `OneToMany` |

*Pas de groupes de sérialisation → tous les champs scalaires sont lus/écrits par défaut.*

### InventoryItem — `/api/inventory_items`

Inventaire de l'appartement.

| Champ | Type | Contraintes |
|-------|------|-------------|
| `id` | int | — |
| `name` | string (255) | requis |
| `quantity` | int | défaut 1 |
| `category` | IRI Category | **requis** (`JoinColumn(nullable: false)`) |
| `state` | `App\Enum\State` (`ok` / `worn` / `replace`) | défaut `ok` |
| `note` | string (255) | optionnel |
| `location` | string (255) | optionnel |

Enum `State` (`src/Enum/State.php`) :

| Valeur | Label FR |
|--------|----------|
| `ok` | Bon état |
| `worn` | Abimé |
| `replace` | À remplacer |

### ShoppingItem — `/api/shopping_items`

Liste de courses.

| Champ | Type | Contraintes |
|-------|------|-------------|
| `id` | int | — |
| `name` | string (255) | requis |
| `quantity` | int | défaut 1 |
| `purchased` | bool | défaut `false` |
| `category` | IRI Category | **optionnel** |

### Note — `/api/notes`

| Champ | Type | Contraintes |
|-------|------|-------------|
| `id` | int | — |
| `title` | string (255) | requis |
| `content` | text | requis |
| `createdAt` | datetime (ISO 8601) | **auto-rempli côté serveur à la création**, lecture seule (toute valeur envoyée par le client est ignorée) |
| `author` | IRI User | **requis** |

> Implémenté via le processor `App\State\NoteProcessor` qui wrappe le `PersistProcessor` Doctrine et pose `createdAt = now()` sur l'opération `POST`. Le champ est marqué `#[ApiProperty(writable: false)]` pour éviter toute écriture client (y compris en PUT/PATCH).

### Occupation — `/api/occupations`

Calendrier d'occupation de l'appartement.

| Champ | Type | Contraintes |
|-------|------|-------------|
| `id` | int | — |
| `startDate` | date (`YYYY-MM-DD` accepté) | requis |
| `endDate` | date | requis |
| `notes` | text | optionnel |
| `occupant` | IRI User | **requis** |

### Work — `/api/works`

Travaux à réaliser dans l'appartement (bricolage / prestation pro). Suivi de cycle de vie + chiffrage.

| Champ | Type | Contraintes |
|-------|------|-------------|
| `id` | int | — |
| `title` | string (255) | requis |
| `description` | text | optionnel |
| `status` | `App\Enum\WorkStatus` | défaut `suggested` |
| `type` | `App\Enum\WorkType` | optionnel |
| `priority` | `App\Enum\WorkPriority` | optionnel |
| `author` | IRI User | **requis** — auto-rempli avec le user courant en `POST` si omis |
| `createdAt` | datetime (ISO 8601) | **auto-rempli côté serveur à la création**, lecture seule (`#[ApiProperty(writable: false)]`) |
| `scheduledFor` | date (`YYYY-MM-DD`) | optionnel |
| `completedAt` | datetime | **auto-rempli côté serveur dès que `status` passe à `done`** si non fourni (toutes opérations) |
| `estimatedCost` | int | optionnel — en euros |
| `actualCost` | int | optionnel — en euros |

Enum `WorkStatus` (`src/Enum/WorkStatus.php`) :

| Valeur | Label FR |
|--------|----------|
| `suggested` | Suggestion |
| `planned` | Planifié |
| `in_progress` | En cours |
| `done` | Fait |
| `cancelled` | Abandonné |

Enum `WorkType` (`src/Enum/WorkType.php`) :

| Valeur | Label FR |
|--------|----------|
| `diy` | À faire soi-même |
| `pro` | À faire faire |

Enum `WorkPriority` (`src/Enum/WorkPriority.php`) :

| Valeur | Label FR |
|--------|----------|
| `low` | Faible |
| `medium` | Moyen |
| `high` | Élevé |

> Implémenté via le processor `App\State\WorkProcessor` qui wrappe le `PersistProcessor` Doctrine : (a) sur `POST`, pose `createdAt = now()` et assigne l'auteur courant si `author` est vide ; (b) sur toute opération, si `status === done` et `completedAt` est vide, pose `completedAt = now()`.

## Filtres & tri

Les collections (`GET /api/<resource>`) acceptent des paramètres de recherche, de filtrage temporel et de tri via les filtres API Platform. Tous les paramètres sont combinables (AND).

### Stratégies `SearchFilter`

- `exact` : égalité stricte (par défaut sur les relations).
- `ipartial` : `LIKE %valeur%` insensible à la casse — utilisé sur les champs texte libres.
- Pour une relation, on accepte soit l'IRI (`?category=/api/categories/1`), soit l'id (`?category=1`).

### `DateFilter`

Pour chaque champ filtrable, suffixes `[before]`, `[strictly_before]`, `[after]`, `[strictly_after]` :
`?createdAt[after]=2026-01-01&createdAt[strictly_before]=2026-07-01`.

### `OrderFilter`

Paramètre `order` sous forme d'objet : `?order[createdAt]=desc&order[title]=asc`.

### `BooleanFilter`

Booléen accepté en `true` / `false` (ou `1` / `0`) : `?purchased=false`.

### Récapitulatif par ressource

| Ressource | SearchFilter | DateFilter | OrderFilter | Autres |
|-----------|--------------|------------|-------------|--------|
| **Category** | `name` (ipartial) | — | `name` | — |
| **InventoryItem** | `name` (ipartial), `category` (exact), `state` (exact), `note` (ipartial), `location` (ipartial) | — | `name`, `quantity`, `state` | — |
| **ShoppingItem** | `name` (ipartial), `category` (exact) | — | `name`, `purchased` | `BooleanFilter` sur `purchased` |
| **Note** | `title` (ipartial), `content` (ipartial), `author` (exact), `author.uuid` (exact) | `createdAt` | `createdAt`, `title` | — |
| **Occupation** | `occupant` (exact), `occupant.uuid` (exact), `notes` (ipartial) | `startDate`, `endDate` | `startDate`, `endDate` | — |
| **Work** | `title` (ipartial), `description` (ipartial), `author.uuid` (exact), `status` (exact), `type` (exact), `priority` (exact) | `createdAt`, `scheduledFor` | `createdAt`, `scheduledFor`, `priority`, `status` | — |

### Exemples

```http
# Notes contenant "chauffage", écrites par un user donné, du plus récent au plus ancien
GET /api/notes?content=chauffage&author=/api/users/3&order[createdAt]=desc

# Occupations chevauchant juillet 2026
GET /api/occupations?startDate[before]=2026-07-31&endDate[after]=2026-07-01

# Inventaire d'une catégorie, items à remplacer
GET /api/inventory_items?category=/api/categories/2&state=replace

# Courses restantes triées par nom
GET /api/shopping_items?purchased=false&order[name]=asc

# Travaux planifiés ou en cours, prioritaires d'abord
GET /api/works?status=planned&order[priority]=desc&order[scheduledFor]=asc
```

## Pagination & format

Par défaut (JSON-LD), un `GET` de collection renvoie un objet `hydra:Collection` paginé. Paramètres standard : `?page=2&itemsPerPage=20`. En `Accept: application/json`, la réponse est un tableau plat.

## Codes d'erreur

| Code | Sens |
|------|------|
| `400` | JSON invalide / contraintes de validation |
| `401` | Token absent, invalide, expiré |
| `403` | Authentifié mais pas autorisé (rôle insuffisant / pas propriétaire) |
| `404` | Ressource inexistante |
| `415` | Mauvais `Content-Type` (typiquement PATCH sans `merge-patch+json`) |
| `422` | Validation Symfony (Hydra `ConstraintViolationList`) |
