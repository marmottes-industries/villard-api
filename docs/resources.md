# Ressources API

Référence des entités exposées par API Platform. Toutes les routes sont préfixées par `/api`. La documentation interactive Swagger est disponible sur `GET /api/docs` (accès public).

> Pour un guide d'utilisation côté front (helpers fetch, conventions IRI, gestion des erreurs), voir [`API.md`](../API.md) à la racine du dépôt.

## Conventions

- **Pluralisation des URLs** : convention API Platform en snake_case anglais.
  `Category → /api/categories`, `InventoryItem → /api/inventory_items`, `ShoppingItem → /api/shopping_items`, `Note → /api/notes`, `Occupation → /api/occupations`, `Work → /api/works`, `User → /api/users`, `Property → /api/properties`, `PropertyMember → /api/property_members`.
- **Identifiants URL** : `id` numérique auto-incrémenté (sauf l'UUID interne du `User`, utilisé pour le JWT mais pas dans les URLs).
- **Relations en écriture** : passer l'IRI (`"/api/categories/1"`), pas un id nu ni un objet imbriqué.
- **PATCH** : `Content-Type: application/merge-patch+json` obligatoire (sinon 415).

## Cloisonnement par logement

Les cinq ressources métier — `Occupation`, `Work`, `InventoryItem`, `ShoppingItem`, `Note` — sont rattachées à un **logement** (`Property`) et cloisonnées côté serveur. Trois conséquences pour un client :

1. **Aucun filtre à envoyer pour être en sécurité.** `GET /api/notes` ne renvoie déjà que les notes des logements dont l'utilisateur est membre. Le paramètre `?property=<IRI>` sert uniquement à restreindre à un logement précis (le logement actif du sélecteur), jamais à élargir : une IRI forgée sur un logement non autorisé renvoie une collection vide.
2. **Les items d'un autre logement n'existent pas.** `GET /api/notes/42` sur une note d'un logement non autorisé renvoie `404`, pas `403` — la ressource est masquée avant même le contrôle de sécurité, ce qui évite de confirmer son existence. Idem quand une telle IRI apparaît dans un payload d'écriture : la réponse est `400 Item not found`.
3. **Le champ `property` est requis en création**, avec un repli : si le payload l'omet et que l'utilisateur n'est membre que d'un seul logement, celui-ci est appliqué automatiquement (compatibilité des builds mobiles antérieurs). Au-delà d'un logement, la réponse est `422` avec un message explicite.

`Category` reste **commune à tous les logements** : c'est un choix assumé, la même arborescence sert partout et un renommage impacte tout le monde.

Le cloisonnement est appliqué par `App\Doctrine\PropertyScopeExtension` (lectures) et `App\Security\Voter\PropertyVoter` (écritures) ; voir [`architecture.md`](architecture.md#cloisonnement-multi-logements) et [`authentication.md`](authentication.md#rôles-locaux-et-propertyvoter).

## Matrice des opérations

Chaque entité expose : `GET /collection`, `GET /{id}`, `POST /collection`, `PUT /{id}`, `PATCH /{id}`, `DELETE /{id}` (sauf `Property` et `PropertyMember`, sans `PUT`).

Dans le tableau, `MEMBRE` et `GESTIONNAIRE` désignent les attributs `PROPERTY_CONTRIBUTE` et `PROPERTY_MANAGE` du voter, évalués sur le logement de la ressource. **`ROLE_ADMIN` satisfait toujours les deux**, sur tous les logements.

| Ressource | GET collection | GET item | POST | PUT / PATCH | DELETE |
|-----------|----------------|----------|------|-------------|--------|
| **User** (`/api/users`) | `ROLE_USER` | `ROLE_USER` | `ROLE_ADMIN` | `ROLE_ADMIN` **ou** `object == user` | `ROLE_ADMIN` |
| **Category** (`/api/categories`) | `ROLE_USER` | `ROLE_USER` | `ROLE_USER` | `ROLE_USER` | `ROLE_USER` |
| **Property** (`/api/properties`) | `ROLE_USER` (cloisonné) | `MEMBRE` | `ROLE_ADMIN` | `GESTIONNAIRE` | `ROLE_ADMIN` |
| **PropertyMember** (`/api/property_members`) | `ROLE_USER` (cloisonné) | `MEMBRE` | `GESTIONNAIRE` | `GESTIONNAIRE` (avant **et** après) | `GESTIONNAIRE` |
| **InventoryItem** (`/api/inventory_items`) | `ROLE_USER` (cloisonné) | `MEMBRE` | `MEMBRE` | `MEMBRE` (avant **et** après) | `GESTIONNAIRE` |
| **ShoppingItem** (`/api/shopping_items`) | `ROLE_USER` (cloisonné) | `MEMBRE` | `MEMBRE` | `MEMBRE` (avant **et** après) | `GESTIONNAIRE` |
| **Note** (`/api/notes`) | `ROLE_USER` (cloisonné) | `MEMBRE` | `MEMBRE` | `MEMBRE` **et** (`GESTIONNAIRE` **ou** auteur avant **et** après = user courant) | `GESTIONNAIRE` **ou** auteur = user courant |
| **Occupation** (`/api/occupations`) | `ROLE_USER` (cloisonné) | `MEMBRE` | `MEMBRE` | `MEMBRE` **et** (`GESTIONNAIRE` **ou** occupant avant **et** après = user courant) | `GESTIONNAIRE` **ou** occupant = user courant |
| **Work** (`/api/works`) | `ROLE_USER` (cloisonné) | `MEMBRE` | `MEMBRE` | `MEMBRE` **et** (`GESTIONNAIRE` **ou** auteur avant **et** après = user courant) | `GESTIONNAIRE` **ou** auteur = user courant |

> Les contrôles « avant et après » sur Note/Occupation/Work utilisent `securityPostDenormalize` : ils empêchent un utilisateur de réassigner un objet à un autre auteur/occupant, ou de le déplacer vers un logement dont il n'est pas membre (vérification simultanée de `object` et `previous_object`).

**Changements de comportement introduits par le multi-logements :**

- La suppression d'un `InventoryItem` ou d'un `ShoppingItem` passe de `ROLE_ADMIN` au **gestionnaire local** du logement. `ROLE_ADMIN` la conserve via le bypass du voter.
- Le gestionnaire local peut modifier les notes, séjours et travaux des autres membres de son logement — capacité qui n'existait qu'au niveau `ROLE_ADMIN`.
- `POST /api/notes` et `POST /api/works` acceptent désormais un `author` omis. C'était déjà l'intention des processors `NoteProcessor` / `WorkProcessor`, mais `securityPostDenormalize` s'exécutant **avant** eux, `object.getAuthor() == user` était toujours faux et la création renvoyait `403` à tout utilisateur non `ROLE_ADMIN` — ce que font pourtant le front Vue comme l'appli Expo, qui ne transmettent pas `author`.

## Endpoints custom

### `GET /api/me`

Retourne l'utilisateur courant (sécurité `ROLE_USER`). Sérialisé avec les groupes `user:read` **et** `property:summary`, IRI renvoyé sous la forme `/api/users/{id}`.

Le second groupe embarque les logements de l'utilisateur dans `memberships`, au lieu de simples IRIs : un client dispose ainsi de tout ce qu'il faut pour amorcer son sélecteur de logement sans second appel.

```json
{
  "@id": "/api/users/2",
  "id": 2,
  "uuid": "80d900ea-…",
  "username": "antonin",
  "roles": ["ROLE_USER"],
  "memberships": [
    {
      "@id": "/api/property_members/8",
      "role": "manager",
      "property": {
        "@id": "/api/properties/1", "id": 1,
        "name": "Les Tennis", "slug": "les-tennis", "city": "Villard-de-Lans",
        "latitude": 45.0647, "longitude": 5.5484,
        "timezone": "Europe/Paris",
        "accentColor": "forest", "accentHex": "#2E4A39",
        "archived": false
      }
    }
  ]
}
```

Implémenté via le provider `App\State\MeProvider` ; voir [`authentication.md`](authentication.md#lendpoint-apime).

### `GET /api/weather`

Météo du logement, alimentée par Open-Meteo, cachée 30 min **par logement** (`weather_property_{id}`).
`?property=<IRI>` facultatif tant que l'utilisateur n'a qu'un logement. Deux points au maximum, de clés stables
`main` (le logement) et `secondary` (point d'altitude optionnel).

Ressource non-Doctrine (`src/ApiResource/WeatherForecast.php`) : l'extension de cloisonnement ne s'y appliquant pas,
c'est `App\State\WeatherProvider` qui contrôle explicitement l'appartenance. Détail du payload dans
[`API.md`](../API.md#12-endpoint-apiweather--météo-du-logement).

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

Relations (présentes en JSON-LD) : `occupations`, `notes`, `memberships`.

`memberships` est dans le groupe `user:read` : sur `/api/users/{id}` il sort en IRIs, sur `/api/me` les logements sont embarqués (cf. plus haut).

### Property — `/api/properties`

Un logement géré par l'application. Porte aussi ses coordonnées météo : le point principal et, optionnellement, un point secondaire d'altitude.

| Champ | Type | Lecture (`property:read`) | Écriture (`property:write`) | Contraintes |
|-------|------|---------------------------|------------------------------|-------------|
| `id` | int | ✓ | — (auto) | — |
| `name` | string (255) | ✓ | ✓ | requis |
| `slug` | string (255) | ✓ | ✓ | requis, **unique**, `^[a-z0-9]+(-[a-z0-9]+)*$` |
| `city` | string (255) | ✓ | ✓ | requis |
| `address` | string (255) | ✓ | ✓ | optionnel |
| `latitude` | float | ✓ | ✓ | requis, −90 → 90 |
| `longitude` | float | ✓ | ✓ | requis, −180 → 180 |
| `timezone` | string (64) | ✓ | ✓ | défaut `Europe/Paris`, fuseau valide |
| `secondaryLocationName` | string (255) | ✓ | ✓ | optionnel — ex. « Côte 2000 » |
| `secondaryLatitude` | float | ✓ | ✓ | optionnel |
| `secondaryLongitude` | float | ✓ | ✓ | optionnel |
| `accentColor` | enum `AccentColor` | ✓ | ✓ | défaut `forest` — `forest`, `lake`, `wood`, `slate`, `plum`, `lichen` |
| `accentHex` | string | ✓ | — (dérivé) | hexadécimal de `accentColor` |
| `archived` | bool | ✓ | ✓ | défaut `false` |

> Le point secondaire n'est exploité par la météo que si ses **trois** champs sont renseignés ; sinon il est ignoré.

> `accentColor` est une palette **fermée** (`App\Enum\AccentColor`) et non un hexadécimal libre : les clients posent du texte blanc sur cet accent, chaque teinte est donc calibrée pour tenir le contraste AA. `accentHex` évite aux clients de dupliquer la table de correspondance.

Un sous-ensemble (`id`, `name`, `slug`, `city`, `latitude`, `longitude`, `timezone`, `accentColor`, `accentHex`, `archived`) porte aussi le groupe `property:summary`, utilisé par `/api/me`.

### PropertyMember — `/api/property_members`

Appartenance d'un utilisateur à un logement — la seule source de vérité du cloisonnement.

| Champ | Type | Lecture (`member:read`) | Écriture (`member:write`) | Contraintes |
|-------|------|-------------------------|----------------------------|-------------|
| `id` | int | ✓ | — (auto) | — |
| `property` | IRI Property | ✓ | ✓ | requis |
| `user` | IRI User | ✓ | ✓ | requis |
| `role` | `App\Enum\PropertyRole` | ✓ | ✓ | défaut `occupant` |

Contrainte d'unicité sur le couple `(property, user)` : un utilisateur ne peut avoir qu'un rôle par logement.

Enum `PropertyRole` (`src/Enum/PropertyRole.php`) :

| Valeur | Label FR | Capacités |
|--------|----------|-----------|
| `manager` | Gestionnaire | Tout dans son logement : administration, membres, ressources des autres membres |
| `occupant` | Occupant | Lecture complète, écriture sur ses propres séjours/notes/travaux, inventaire et courses |

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
| `property` | IRI Property | **requis** — cf. [Cloisonnement par logement](#cloisonnement-par-logement) ; auto-rempli en `POST` si l'utilisateur n'a qu'un logement |

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
| `property` | IRI Property | **requis** — cf. [Cloisonnement par logement](#cloisonnement-par-logement) ; auto-rempli en `POST` si l'utilisateur n'a qu'un logement |

### Note — `/api/notes`

| Champ | Type | Contraintes |
|-------|------|-------------|
| `id` | int | — |
| `title` | string (255) | requis |
| `content` | text | requis |
| `createdAt` | datetime (ISO 8601) | **auto-rempli côté serveur à la création**, lecture seule (toute valeur envoyée par le client est ignorée) |
| `author` | IRI User | **requis** |
| `property` | IRI Property | **requis** — cf. [Cloisonnement par logement](#cloisonnement-par-logement) ; auto-rempli en `POST` si l'utilisateur n'a qu'un logement |

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
| `property` | IRI Property | **requis** — cf. [Cloisonnement par logement](#cloisonnement-par-logement) ; auto-rempli en `POST` si l'utilisateur n'a qu'un logement |

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
| `property` | IRI Property | **requis** — cf. [Cloisonnement par logement](#cloisonnement-par-logement) ; auto-rempli en `POST` si l'utilisateur n'a qu'un logement |

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
| **Property** | `name` (ipartial), `slug` (exact), `city` (ipartial) | — | `name`, `city` | `BooleanFilter` sur `archived` |
| **PropertyMember** | `property` (exact), `user` (exact), `user.uuid` (exact), `role` (exact) | — | — | — |
| **InventoryItem** | `property` (exact), `name` (ipartial), `category` (exact), `state` (exact), `note` (ipartial), `location` (ipartial) | — | `name`, `quantity`, `state` | — |
| **ShoppingItem** | `property` (exact), `name` (ipartial), `category` (exact) | — | `name`, `purchased` | `BooleanFilter` sur `purchased` |
| **Note** | `property` (exact), `title` (ipartial), `content` (ipartial), `author` (exact), `author.uuid` (exact) | `createdAt` | `createdAt`, `title` | — |
| **Occupation** | `property` (exact), `occupant` (exact), `occupant.uuid` (exact), `notes` (ipartial) | `startDate`, `endDate` | `startDate`, `endDate` | — |
| **Work** | `property` (exact), `title` (ipartial), `description` (ipartial), `author.uuid` (exact), `status` (exact), `type` (exact), `priority` (exact) | `createdAt`, `scheduledFor` | `createdAt`, `scheduledFor`, `priority`, `status` | — |

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
