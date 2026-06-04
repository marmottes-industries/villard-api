# Les Marmottes — API Reference

Documentation de l'API backend (`villard-api`) à destination du client front (`appart-front`).
Ce fichier est conçu pour être copié à la racine du repo front et lu directement par l'agent Claude du projet front.

> **Stack backend**: Symfony 8.1 + API Platform 4.x + Doctrine + MariaDB + LexikJWT.
> **Préfixe global**: toutes les routes API sont sous `/api`.

---

## 1. Base URL & environnements

| Env | URL | Notes |
|-----|-----|-------|
| Dev local | `http://127.0.0.1:8000/api` | lancé via `symfony server:start` |
| Prod | _(à définir)_ | |

CORS dev : tout `http(s)://localhost` ou `127.0.0.1` sur n'importe quel port est autorisé. Méthodes autorisées : `GET, POST, PUT, PATCH, DELETE, OPTIONS`. Headers : `Content-Type`, `Authorization`.

Documentation interactive Swagger : `GET /api/docs` (accès public).

---

## 2. Authentification (JWT)

L'API est **stateless**. Toutes les routes sous `/api` (sauf `/api/login` et `/api/docs`) exigent un JWT valide.

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
{ "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...." }
```

**Réponse 401** : identifiants invalides.

### 2.2 Utilisation du token

Joindre le token sur **toutes** les requêtes API :

```
Authorization: Bearer <token>
```

- **TTL** : 3600 s (1 h). Au-delà → `401`. Pas de refresh token côté serveur pour l'instant : refaire un `POST /api/login`.
- Le claim d'identité du JWT est `uuid` (UUID immuable de l'utilisateur) → renommer son `username` n'invalide pas un token déjà émis.

### 2.3 Rôles

- `ROLE_USER` : utilisateur connecté (par défaut pour tout user).
- `ROLE_ADMIN` : suppressions sensibles + création/suppression d'utilisateurs.

Voir la matrice de permissions par ressource ci-dessous.

---

## 3. Format des requêtes / réponses

API Platform expose deux formats. **Recommandé pour le front : JSON pur.**

| Header | Format |
|--------|--------|
| `Accept: application/json` | JSON pur (sans hypermedia) |
| `Accept: application/ld+json` (défaut) | JSON-LD avec `@id`, `@type`, `hydra:*` |
| `Content-Type: application/json` | pour `POST` / `PUT` |
| `Content-Type: application/merge-patch+json` | **obligatoire** pour `PATCH` |

### 3.1 Pagination & collections

Les `GET` de collection (`/api/categories`, etc.) retournent par défaut un objet paginé Hydra (JSON-LD) :

```json
{
  "@context": "/api/contexts/Category",
  "@id": "/api/categories",
  "@type": "hydra:Collection",
  "hydra:member": [ { "@id": "/api/categories/1", "id": 1, "name": "Cuisine" } ],
  "hydra:totalItems": 1
}
```

En `Accept: application/json` la réponse est directement un tableau `[ { ... } ]`.

Paramètres de pagination standard d'API Platform : `?page=2&itemsPerPage=20`.

### 3.2 Erreurs

| Code | Sens |
|------|------|
| `400` | JSON invalide / contraintes de validation |
| `401` | Token absent, invalide, expiré |
| `403` | Authentifié mais pas autorisé (mauvais rôle / pas propriétaire) |
| `404` | Ressource inexistante |
| `415` | Mauvais `Content-Type` (typiquement PATCH sans `merge-patch+json`) |
| `422` | Validation Symfony (Hydra `ConstraintViolationList`) |

---

## 4. Ressources

URI = pluralisation d'API Platform (snake_case en pluriel anglais).
Chaque ressource expose les opérations REST standard : `GET /collection`, `GET /{id}`, `POST /collection`, `PUT /{id}`, `PATCH /{id}`, `DELETE /{id}`.

### 4.1 User — `/api/users`

Authentification système. **Identifiant URL = `id` numérique.** L'`uuid` est interne (JWT).

| Op | Sécurité |
|----|----------|
| GET (collection / item) | `ROLE_USER` |
| POST | `ROLE_ADMIN` |
| PUT / PATCH | `ROLE_ADMIN` **ou** être l'utilisateur lui-même |
| DELETE | `ROLE_ADMIN` |

**Lecture (`user:read`)**
```json
{
  "id": 1,
  "username": "alice",
  "roles": ["ROLE_USER"]
}
```

**Écriture (`user:write`)** — seul `username` est exposé en write par les groupes de sérialisation. Le mot de passe n'est pas modifiable via cet endpoint (à terme : commande CLI `app:create-user`, cf. `CreateUserCommand`).

### 4.2 Category — `/api/categories`

| Op | Sécurité |
|----|----------|
| GET / POST / PUT / PATCH / DELETE | `ROLE_USER` |

```json
{ "id": 1, "name": "Cuisine" }
```

Relations exposées par défaut (JSON-LD) : `inventoryItems`, `shoppingItems` (IRIs).

### 4.3 InventoryItem — `/api/inventory_items`

Inventaire de l'appartement. `category` est **obligatoire**.

| Op | Sécurité |
|----|----------|
| GET / POST / PUT / PATCH | `ROLE_USER` |
| DELETE | `ROLE_ADMIN` |

```json
{
  "id": 12,
  "name": "Casseroles",
  "quantity": 3,
  "category": "/api/categories/1"
}
```

Pour `POST` / `PUT` : passer la category en IRI (`"/api/categories/1"`) — convention API Platform.

### 4.4 ShoppingItem — `/api/shopping_items`

Liste de courses. `category` est **optionnelle**.

| Op | Sécurité |
|----|----------|
| GET / POST / PUT / PATCH | `ROLE_USER` |
| DELETE | `ROLE_ADMIN` |

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

| Op | Sécurité |
|----|----------|
| GET (collection / item) | `ROLE_USER` |
| POST | `ROLE_ADMIN` **ou** auteur = utilisateur courant |
| PUT / PATCH / DELETE | `ROLE_ADMIN` **ou** auteur de la note |

```json
{
  "id": 7,
  "title": "Code du portail",
  "content": "1234B",
  "createdAt": "2026-01-15T10:00:00+00:00",
  "author": "/api/users/2"
}
```

> ⚠️ En `POST`, `author` doit pointer sur l'utilisateur courant (sauf admin). `createdAt` n'est pas auto-rempli pour l'instant côté serveur → l'envoyer depuis le front.

### 4.6 Occupation — `/api/occupations`

Calendrier d'occupation de l'appartement.

| Op | Sécurité |
|----|----------|
| GET (collection / item) | `ROLE_USER` |
| POST | `ROLE_ADMIN` **ou** `occupant` = utilisateur courant |
| PUT / PATCH / DELETE | `ROLE_ADMIN` **ou** occupant de la période |

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
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      ...init.headers,
    },
  })
  if (!res.ok) throw new Error(`${res.status} ${res.statusText}`)
  return res.status === 204 ? (undefined as T) : res.json()
}
```

### 5.2 Login

```ts
const { token } = await api<{ token: string }>('/login', {
  method: 'POST',
  body: JSON.stringify({ username, password }),
})
localStorage.setItem('jwt', token)
```

### 5.3 Lister les courses

```ts
const items = await api<ShoppingItem[]>('/shopping_items')
```

### 5.4 Cocher un item (PATCH)

```ts
await api(`/shopping_items/${id}`, {
  method: 'PATCH',
  headers: { 'Content-Type': 'application/merge-patch+json' },
  body: JSON.stringify({ purchased: true }),
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

1. **Toujours utiliser les IRIs** (`"/api/users/2"`) pour les relations en écriture, pas les objets imbriqués ni les ids nus.
2. **PATCH** → `Content-Type: application/merge-patch+json` sinon `415`.
3. Préférer `Accept: application/json` pour des payloads plats ; passer en `application/ld+json` uniquement si on a besoin de l'hypermedia/pagination Hydra.
4. Le JWT expire au bout d'1 h : prévoir un intercepteur qui, sur `401`, déconnecte et redirige vers le login.
5. **Pas de breaking changes côté serveur** : si le front a besoin d'un champ supplémentaire ou d'un endpoint custom, ouvrir une issue plutôt que de bidouiller. L'API doit rester consommable par d'autres clients (mobile à venir).
6. La pluralisation des URLs suit la convention API Platform : `Category → categories`, `InventoryItem → inventory_items`, `ShoppingItem → shopping_items`, `Note → notes`, `Occupation → occupations`, `User → users`.

---

## 7. Endpoints non encore exposés

À demander au backend si besoin côté front :

- `GET /api/me` (profil de l'utilisateur courant) — actuellement il faut récupérer l'id depuis le JWT décodé ou faire `GET /api/users` + filtrer.
- Refresh token.
- Auto-remplissage de `createdAt` sur `Note` côté serveur.
- Filtres / recherche (API Platform `SearchFilter`) sur les ressources.
