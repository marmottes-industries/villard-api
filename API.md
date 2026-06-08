# Les Marmottes — API Reference

Documentation de l'API backend (`villard-api`) à destination du client front (`villard-front`).
Ce fichier est conçu pour être copié à la racine du repo front et lu directement par l'agent Claude du projet front.

> **Stack backend**: Symfony 8.1 + API Platform 4.x + Doctrine + MariaDB + LexikJWT + gesdinet/jwt-refresh-token-bundle.
> **Préfixe global**: toutes les routes API sont sous `/api`.

---

## 1. Base URL & environnements

| Env       | URL                                    | Notes                            |
|-----------|----------------------------------------|----------------------------------|
| Dev local | `http://127.0.0.1:8000/api`            | lancé via `symfony server:start` |
| Prod      | `https://villard-api.antoninpamart.fr` | herbergé infomaniak              |

CORS dev : tout `http(s)://localhost` ou `127.0.0.1` sur n'importe quel port est autorisé. Méthodes autorisées :
`GET, POST, PUT, PATCH, DELETE, OPTIONS`. Headers : `Content-Type`, `Authorization`.

Documentation interactive Swagger : `GET /api/docs` (accès public).

---

## 2. Authentification (JWT + refresh)

L'API est **stateless**. Toutes les routes sous `/api` (sauf `/api/login`, `/api/token/refresh` et `/api/docs`) exigent un JWT valide.

### 2.1 Login

```
POST /api/login
Content-Type: application/json

{
  "username": "alice",
  "password": "•••••"
}
```

**Réponse 200**

```json
{
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9....",
    "refresh_token": "7e1f741b392bcd1cf078276fa68327f0..."
}
```

**Réponse 401** : identifiants invalides.

### 2.2 Utilisation du token

Joindre le token sur **toutes** les requêtes API :

```
Authorization: Bearer <token>
```

- **TTL access token** : 3600 s (1 h). Au-delà → `401` (cf. §2.3 pour le refresh).
- Le claim d'identité du JWT est `uuid` (UUID immuable de l'utilisateur) → renommer son `username` n'invalide pas un
  token déjà émis.

### 2.3 Refresh token

À expiration du JWT, échanger le `refresh_token` contre un nouveau couple :

```
POST /api/token/refresh
Content-Type: application/json

{ "refresh_token": "7e1f741b392bcd1cf078276fa68327f0..." }
```

**Réponse 200** — nouveau couple :

```json
{
    "token": "eyJ0eXAi...",
    "refresh_token": "9273a5b26cea54234b63e4c37128fc2c..."
}
```

**Réponse 401** : refresh inconnu, expiré, ou déjà consommé.

Côté serveur :

- **TTL refresh token** : 30 jours, **glissant** — chaque refresh repart pour 30 jours d'activité.
- **Rotation activée** (`single_use: true`) : un refresh token ne sert qu'**une seule fois**. Stocker uniquement le dernier `refresh_token` reçu et écraser à chaque refresh.
- Un utilisateur inactif > 30 jours doit refaire un `POST /api/login`.

> ⚠️ Stratégie front recommandée : intercepteur HTTP qui sur `401` (autre que sur `/api/login`) tente **un seul** appel à `/api/token/refresh`, rejoue la requête initiale avec le nouveau token, et si le refresh lui-même renvoie `401` → déconnexion + redirection vers le login. Verrouiller les refreshs concurrents (mutex/queue) pour éviter qu'une vague de 401 ne fasse appeler `/api/token/refresh` 10× en parallèle : seul le premier réussit, les autres invalideront le nouveau refresh (rotation).

### 2.4 Rôles

- `ROLE_USER` : utilisateur connecté (par défaut pour tout user).
- `ROLE_ADMIN` : suppressions sensibles + création/suppression d'utilisateurs.

Voir la matrice de permissions par ressource ci-dessous.

---

## 3. Format des requêtes / réponses

API Platform expose deux formats. **Recommandé pour le front : JSON pur.**

| Header                                       | Format                                 |
|----------------------------------------------|----------------------------------------|
| `Accept: application/json`                   | JSON pur (sans hypermedia)             |
| `Accept: application/ld+json` (défaut)       | JSON-LD avec `@id`, `@type`, `hydra:*` |
| `Content-Type: application/json`             | pour `POST` / `PUT`                    |
| `Content-Type: application/merge-patch+json` | **obligatoire** pour `PATCH`           |

### 3.1 Pagination & collections

Les `GET` de collection (`/api/categories`, etc.) retournent par défaut un objet paginé Hydra (JSON-LD) :

```json
{
    "@context": "/api/contexts/Category",
    "@id": "/api/categories",
    "@type": "hydra:Collection",
    "hydra:member": [
        {
            "@id": "/api/categories/1",
            "id": 1,
            "name": "Cuisine"
        }
    ],
    "hydra:totalItems": 1
}
```

En `Accept: application/json` la réponse est directement un tableau `[ { ... } ]`.

Paramètres de pagination standard d'API Platform : `?page=2&itemsPerPage=20`.

### 3.2 Erreurs

| Code  | Sens                                                               |
|-------|--------------------------------------------------------------------|
| `400` | JSON invalide / contraintes de validation                          |
| `401` | Token absent, invalide, expiré                                     |
| `403` | Authentifié mais pas autorisé (mauvais rôle / pas propriétaire)    |
| `404` | Ressource inexistante                                              |
| `415` | Mauvais `Content-Type` (typiquement PATCH sans `merge-patch+json`) |
| `422` | Validation Symfony (Hydra `ConstraintViolationList`)               |

---

## 4. Ressources

URI = pluralisation d'API Platform (snake_case en pluriel anglais).
Chaque ressource expose les opérations REST standard : `GET /collection`, `GET /{id}`, `POST /collection`, `PUT /{id}`,
`PATCH /{id}`, `DELETE /{id}`.

### 4.1 User — `/api/users`

Authentification système. **Identifiant URL = `id` numérique.** L'`uuid` est interne (JWT).

| Op                      | Sécurité                                        |
|-------------------------|-------------------------------------------------|
| GET (collection / item) | `ROLE_USER`                                     |
| POST                    | `ROLE_ADMIN`                                    |
| PUT / PATCH             | `ROLE_ADMIN` **ou** être l'utilisateur lui-même |
| DELETE                  | `ROLE_ADMIN`                                    |

**Lecture (`user:read`)**

```json
{
    "id": 1,
    "username": "alice",
    "roles": [
        "ROLE_USER"
    ]
}
```

**Écriture (`user:write`)** — seul `username` est exposé en write par les groupes de sérialisation. Le mot de passe
n'est pas modifiable via cet endpoint.

> ⚠️ **Conséquence** : `POST /api/users` ne peut pas définir de mot de passe → impossible de créer un compte utilisable
> directement par l'API tant que `password` n'est pas ajouté au groupe `user:write`. Pour créer un user en pratique,
> utiliser la commande CLI côté backend : `php bin/console app:create-user <username>` (cf. `CreateUserCommand`).

### 4.2 Category — `/api/categories`

| Op                                | Sécurité    |
|-----------------------------------|-------------|
| GET / POST / PUT / PATCH / DELETE | `ROLE_USER` |

```json
{
    "id": 1,
    "name": "Cuisine"
}
```

Relations exposées par défaut (JSON-LD) : `inventoryItems`, `shoppingItems` (IRIs).

### 4.3 InventoryItem — `/api/inventory_items`

Inventaire de l'appartement. `category` est **obligatoire**.

| Op                       | Sécurité     |
|--------------------------|--------------|
| GET / POST / PUT / PATCH | `ROLE_USER`  |
| DELETE                   | `ROLE_ADMIN` |

```json
{
    "id": 12,
    "name": "Casseroles",
    "quantity": 3,
    "category": "/api/categories/1",
    "state": "ok",
    "note": "Une casserole a perdu son manche",
    "location": "Placard sous l'évier"
}
```

Champs :

- `category` (IRI, **requis**) — passer la catégorie en IRI (`"/api/categories/1"`), convention API Platform.
- `state` (enum, défaut `"ok"`) — valeurs possibles : `"ok"` (Bon état), `"worn"` (Abimé), `"replace"` (À remplacer).
- `note` (string, optionnel) — précision libre sur l'item (jusqu'à 255 caractères).
- `location` (string, optionnel) — emplacement physique dans le logement (255 caractères).

### 4.4 ShoppingItem — `/api/shopping_items`

Liste de courses. `category` est **optionnelle**.

| Op                       | Sécurité     |
|--------------------------|--------------|
| GET / POST / PUT / PATCH | `ROLE_USER`  |
| DELETE                   | `ROLE_ADMIN` |

```json
{
    "id": 4,
    "name": "Lait",
    "quantity": 2,
    "purchased": false,
    "category": "/api/categories/1"
}
```

### 4.5 Note — `/api/notes`

| Op                      | Sécurité                                         |
|-------------------------|--------------------------------------------------|
| GET (collection / item) | `ROLE_USER`                                      |
| POST                    | `ROLE_ADMIN` **ou** auteur = utilisateur courant |
| PUT / PATCH / DELETE    | `ROLE_ADMIN` **ou** auteur de la note            |

```json
{
    "id": 7,
    "title": "Code du portail",
    "content": "1234B",
    "createdAt": "2026-01-15T10:00:00+00:00",
    "author": "/api/users/2"
}
```

> En `POST`, `author` doit pointer sur l'utilisateur courant (sauf admin). **`createdAt` est auto-rempli côté serveur**
> (timestamp UTC à l'instant de la création) et lecture seule — toute valeur envoyée par le client est ignorée, y
> compris en `PUT`/`PATCH`.

### 4.6 Occupation — `/api/occupations`

Calendrier d'occupation de l'appartement.

| Op                      | Sécurité                                             |
|-------------------------|------------------------------------------------------|
| GET (collection / item) | `ROLE_USER`                                          |
| POST                    | `ROLE_ADMIN` **ou** `occupant` = utilisateur courant |
| PUT / PATCH / DELETE    | `ROLE_ADMIN` **ou** occupant de la période           |

```json
{
    "id": 3,
    "startDate": "2026-07-01",
    "endDate": "2026-07-15",
    "notes": "Vacances d'été",
    "occupant": "/api/users/2"
}
```

Dates au format ISO 8601 (`YYYY-MM-DD` accepté pour les `date_immutable`).

---

## 5. Exemples d'appels (front)

### 5.1 Helper fetch minimal

```ts
const API = import.meta.env.VITE_API_URL ?? 'http://127.0.0.1:8000/api'

async function api<T>(path: string, init: RequestInit = {}): Promise<T> {
    const token = localStorage.getItem('jwt')
    const res = await fetch(`${API}${path}`, {
        ...init,
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            ...(token ? {Authorization: `Bearer ${token}`} : {}),
            ...init.headers,
        },
    })
    if (!res.ok) throw new Error(`${res.status} ${res.statusText}`)
    return res.status === 204 ? (undefined as T) : res.json()
}
```

### 5.2 Login

```ts
const {token, refresh_token} = await api<{ token: string; refresh_token: string }>('/login', {
    method: 'POST',
    body: JSON.stringify({username, password}),
})
localStorage.setItem('jwt', token)
localStorage.setItem('refresh', refresh_token)
```

### 5.2 bis Refresh

```ts
const {token, refresh_token} = await api<{ token: string; refresh_token: string }>('/token/refresh', {
    method: 'POST',
    body: JSON.stringify({refresh_token: localStorage.getItem('refresh')}),
})
localStorage.setItem('jwt', token)
localStorage.setItem('refresh', refresh_token) // ⚠️ écraser : l'ancien refresh est invalidé
```

### 5.3 Lister les courses

```ts
const items = await api<ShoppingItem[]>('/shopping_items')
```

### 5.4 Cocher un item (PATCH)

```ts
await api(`/shopping_items/${id}`, {
    method: 'PATCH',
    headers: {'Content-Type': 'application/merge-patch+json'},
    body: JSON.stringify({purchased: true}),
})
```

### 5.5 Créer une occupation

```ts
await api('/occupations', {
    method: 'POST',
    body: JSON.stringify({
        startDate: '2026-07-01',
        endDate: '2026-07-15',
        notes: 'Vacances',
        occupant: '/api/users/2', // IRI du user connecté
    }),
})
```

---

## 6. Conventions importantes pour l'agent front

1. **Toujours utiliser les IRIs** (`"/api/users/2"`) pour les relations en écriture, pas les objets imbriqués ni les ids
   nus.
2. **PATCH** → `Content-Type: application/merge-patch+json` sinon `415`.
3. Préférer `Accept: application/json` pour des payloads plats ; passer en `application/ld+json` uniquement si on a
   besoin de l'hypermedia/pagination Hydra.
4. Le JWT expire au bout d'1 h : intercepteur qui sur `401` tente un refresh (§2.3), rejoue la requête, et déconnecte uniquement si le refresh échoue lui-même.
5. **Pas de breaking changes côté serveur** : si le front a besoin d'un champ supplémentaire ou d'un endpoint custom,
   ouvrir une issue plutôt que de bidouiller. L'API doit rester consommable par d'autres clients (mobile à venir).
6. La pluralisation des URLs suit la convention API Platform : `Category → categories`,
   `InventoryItem → inventory_items`, `ShoppingItem → shopping_items`, `Note → notes`, `Occupation → occupations`,
   `User → users`.

---

## 7. Endpoint `/api/me`

`GET /api/me` (sécurité `ROLE_USER`) retourne le profil de l'utilisateur courant, sérialisé avec le groupe `user:read`.
L'IRI renvoyé pointe vers `/api/users/{id}`. Pas besoin de décoder le JWT côté front pour connaître l'identité du user
connecté.

```ts
const me = await api<User>('/me')
// → { id: 2, uuid: "...", username: "antonin", roles: ["ROLE_USER"] }
```

Implémenté via `App\State\MeProvider` (cf. `src/State/MeProvider.php`). Si l'utilisateur n'est pas authentifié → `401`.

---

## 8. Filtres, recherche & tri

Toutes les collections (`GET /api/<resource>`) acceptent des paramètres de filtrage et de tri via la query string. Les paramètres se combinent en AND.

### Stratégies courantes

- **SearchFilter `exact`** : égalité stricte. Sur une relation, on accepte l'IRI (`?category=/api/categories/1`) **ou** l'id nu (`?category=1`).
- **SearchFilter `ipartial`** : `LIKE %valeur%` insensible à la casse, pour les recherches en texte libre.
- **DateFilter** : suffixes `[before]`, `[strictly_before]`, `[after]`, `[strictly_after]`. Exemple : `?createdAt[after]=2026-01-01`.
- **BooleanFilter** : `true` / `false` (ou `1` / `0`).
- **OrderFilter** : `?order[champ]=asc|desc`, plusieurs champs autorisés.

### Récapitulatif par ressource

| Ressource | Search | Date | Order | Booléen |
|-----------|--------|------|-------|---------|
| `Category` | `name` (ipartial) | — | `name` | — |
| `InventoryItem` | `name` (ipartial), `category` (exact), `state` (exact), `note` (ipartial), `location` (ipartial) | — | `name`, `quantity`, `state` | — |
| `ShoppingItem` | `name` (ipartial), `category` (exact) | — | `name`, `purchased` | `purchased` |
| `Note` | `title` (ipartial), `content` (ipartial), `author` (exact), `author.uuid` (exact) | `createdAt` | `createdAt`, `title` | — |
| `Occupation` | `occupant` (exact), `occupant.uuid` (exact), `notes` (ipartial) | `startDate`, `endDate` | `startDate`, `endDate` | — |

### Exemples

```http
# Notes contenant "chauffage", auteur donné, du plus récent au plus ancien
GET /api/notes?content=chauffage&author=/api/users/3&order[createdAt]=desc

# Occupations chevauchant juillet 2026
GET /api/occupations?startDate[before]=2026-07-31&endDate[after]=2026-07-01

# Inventaire d'une catégorie, items à remplacer
GET /api/inventory_items?category=/api/categories/2&state=replace

# Courses restantes triées par nom
GET /api/shopping_items?purchased=false&order[name]=asc
```

Les filtres apparaissent aussi dans `/api/docs` (Swagger UI) pour chaque collection.

---

## 9. Endpoints non encore exposés

À demander au backend si besoin côté front :

- **Création de mot de passe via `POST /api/users`** — actuellement le champ `password` n'est pas dans `user:write` (
  cf. § 4.1).
