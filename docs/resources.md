# Ressources API

Référence des entités exposées par API Platform. Toutes les routes sont préfixées par `/api`. La documentation interactive Swagger est disponible sur `GET /api/docs` (accès public).

> Pour un guide d'utilisation côté front (helpers fetch, conventions IRI, gestion des erreurs), voir [`API.md`](../API.md) à la racine du dépôt.

## Conventions

- **Pluralisation des URLs** : convention API Platform en snake_case anglais.
  `Category → /api/categories`, `InventoryItem → /api/inventory_items`, `ShoppingItem → /api/shopping_items`, `Note → /api/notes`, `Occupation → /api/occupations`, `User → /api/users`.
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

> Les contrôles « avant et après » sur Note/Occupation utilisent `securityPostDenormalize` : ils empêchent un utilisateur de réassigner un objet à un autre auteur/occupant (vérification simultanée de `object` et `previous_object`).

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
| `createdAt` | datetime (ISO 8601) | requis, **non auto-rempli côté serveur** → à fournir par le client |
| `author` | IRI User | **requis** |

### Occupation — `/api/occupations`

Calendrier d'occupation de l'appartement.

| Champ | Type | Contraintes |
|-------|------|-------------|
| `id` | int | — |
| `startDate` | date (`YYYY-MM-DD` accepté) | requis |
| `endDate` | date | requis |
| `notes` | text | optionnel |
| `occupant` | IRI User | **requis** |

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
