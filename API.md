<!--
  Source de vérité : villard-api/API.md.
  Dans villard-front/ et villard-appli/, ce fichier est synchronisé via `./scripts/sync-api-docs.sh`
  (ou `npm run sync:docs`). Ne pas éditer la copie : modifier villard-api/API.md, push, puis re-sync.
-->

# Les Marmottes — API Reference

Documentation de l'API backend (`villard-api`) à destination des clients (web `villard-front` + mobile `villard-appli`).
Ce fichier est synchronisé automatiquement depuis `villard-api` vers les repos consommateurs et lu directement par les
agents Claude.

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

L'API est **stateless**. Toutes les routes sous `/api` (sauf `/api/login`, `/api/token/refresh`, `/api/docs` et
`/api/app/version`) exigent un JWT valide.

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
- **Rotation activée** (`single_use: true`) : un refresh token ne sert qu'**une seule fois**. Stocker uniquement le
  dernier `refresh_token` reçu et écraser à chaque refresh.
- Un utilisateur inactif > 30 jours doit refaire un `POST /api/login`.

> ⚠️ Stratégie front recommandée : intercepteur HTTP qui sur `401` (autre que sur `/api/login`) tente **un seul** appel
> à `/api/token/refresh`, rejoue la requête initiale avec le nouveau token, et si le refresh lui-même renvoie `401` →
> déconnexion + redirection vers le login. Verrouiller les refreshs concurrents (mutex/queue) pour éviter qu'une vague de
> 401 ne fasse appeler `/api/token/refresh` 10× en parallèle : seul le premier réussit, les autres invalideront le nouveau
> refresh (rotation).

### 2.4 Rôles

**Rôles globaux :**

- `ROLE_USER` : utilisateur connecté (par défaut pour tout user).
- `ROLE_ADMIN` : super-rôle — création/suppression d'utilisateurs, et **accès complet à tous les logements** (voir §2.5).

**Rôles locaux, par logement** (portés par `PropertyMember`) :

- `manager` (gestionnaire) : administre le logement, ses membres, et les ressources de tous les membres.
- `occupant` : lecture complète du logement ; écriture sur ses propres séjours, notes et travaux, plus l'inventaire et les courses.

Voir la matrice de permissions par ressource ci-dessous.

### 2.5 Logements et cloisonnement — à lire avant de coder un client

Toutes les données métier (`Occupation`, `Work`, `InventoryItem`, `ShoppingItem`, `Note`) appartiennent à un **logement** (`Property`). Le serveur cloisonne, le client n'a rien à sécuriser :

| Situation | Comportement serveur |
|-----------|----------------------|
| `GET /api/notes` sans filtre | Renvoie **déjà** uniquement les notes des logements dont l'utilisateur est membre |
| `GET /api/notes?property=<IRI autorisée>` | Restreint au logement demandé — c'est l'usage normal du sélecteur de logement |
| `GET /api/notes?property=<IRI interdite>` | Collection **vide**, jamais les données de l'autre logement |
| `GET /api/notes/{id}` d'un autre logement | `404` (pas `403` : l'existence n'est même pas confirmée) |
| `POST` avec une `property` interdite | `400 Item not found` |
| `POST` **sans** `property`, utilisateur mono-logement | `201` — le logement est appliqué automatiquement |
| `POST` **sans** `property`, utilisateur multi-logements | `422` avec un message explicite |

Le repli mono-logement existe pour que les builds mobiles antérieurs continuent de fonctionner. **Un client à jour doit toujours envoyer `property`** en création, et propager `?property=` en lecture.

`Category` est **commune à tous les logements** : la même arborescence sert partout.

`GET /api/me` renvoie les logements de l'utilisateur et son rôle local dans chacun : c'est tout ce qu'il faut pour amorcer un sélecteur de logement, sans second appel. Voir §7.

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
    "email": "alice@example.com",
    "roles": [
        "ROLE_USER"
    ]
}
```

**Écriture (`user:write`)** — `username` et `email` sont exposés en write par les groupes de sérialisation. Le mot de
passe n'est pas modifiable via cet endpoint.

- `email` (string, optionnel) — adresse de notification. Validée (`Assert\Email`). Sert à l'envoi des e-mails du système
  de notification (cf. §11). `null` = l'utilisateur ne recevra pas d'e-mail.

> ⚠️ **Conséquence** : `POST /api/users` ne peut pas définir de mot de passe → impossible de créer un compte utilisable
> directement par l'API tant que `password` n'est pas ajouté au groupe `user:write`. Pour créer un user en pratique,
> utiliser la commande CLI côté backend : `php bin/console app:create-user <username> [--email <email>]`
> (cf. `CreateUserCommand`).

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

| Op                      | Sécurité                                       |
|-------------------------|------------------------------------------------|
| GET collection          | `ROLE_USER` — cloisonné par logement (cf. §2.5) |
| GET item                | membre du logement                             |
| POST / PUT / PATCH      | membre du logement                             |
| DELETE                  | **gestionnaire** du logement                   |

```json
{
    "id": 12,
    "name": "Casseroles",
    "quantity": 3,
    "category": "/api/categories/1",
    "state": "ok",
    "note": "Une casserole a perdu son manche",
    "location": "Placard sous l'évier",
    "property": "/api/properties/1"
}
```

Champs :

- `category` (IRI, **requis**) — passer la catégorie en IRI (`"/api/categories/1"`), convention API Platform.
- `state` (enum, défaut `"ok"`) — valeurs possibles : `"ok"` (Bon état), `"worn"` (Abimé), `"replace"` (À remplacer).
- `note` (string, optionnel) — précision libre sur l'item (jusqu'à 255 caractères).
- `location` (string, optionnel) — emplacement physique dans le logement (255 caractères).
- `property` (IRI Property, **requis**) — le logement. Auto-rempli en `POST` si l'utilisateur n'en a qu'un (cf. §2.5).

### 4.4 ShoppingItem — `/api/shopping_items`

Liste de courses. `category` est **optionnelle**.

| Op                      | Sécurité                                       |
|-------------------------|------------------------------------------------|
| GET collection          | `ROLE_USER` — cloisonné par logement (cf. §2.5) |
| GET item                | membre du logement                             |
| POST / PUT / PATCH      | membre du logement                             |
| DELETE                  | **gestionnaire** du logement                   |

```json
{
    "id": 4,
    "name": "Lait",
    "quantity": 2,
    "purchased": false,
    "category": "/api/categories/1",
    "property": "/api/properties/1"
}
```

### 4.5 Note — `/api/notes`

| Op                      | Sécurité                                                            |
|-------------------------|---------------------------------------------------------------------|
| GET collection          | `ROLE_USER` — cloisonné par logement (cf. §2.5)                      |
| GET item                | membre du logement                                                   |
| POST                    | membre du logement                                                   |
| PUT / PATCH             | membre du logement **et** (gestionnaire **ou** auteur avant et après) |
| DELETE                  | gestionnaire du logement **ou** auteur de la note                    |

```json
{
    "id": 7,
    "title": "Code du portail",
    "content": "1234B",
    "createdAt": "2026-01-15T10:00:00+00:00",
    "author": "/api/users/2",
    "property": "/api/properties/1"
}
```

> En `POST`, `author` peut être **omis** : le serveur l'assigne à l'utilisateur courant. S'il est fourni, il doit
> pointer sur l'utilisateur courant (sauf gestionnaire du logement ou admin). **`createdAt` est auto-rempli côté
> serveur** (timestamp UTC à l'instant de la création) et lecture seule — toute valeur envoyée par le client est
> ignorée, y compris en `PUT`/`PATCH`. `property` est **requis**, avec repli mono-logement (cf. §2.5).

### 4.6 Occupation — `/api/occupations`

Calendrier d'occupation de l'appartement.

| Op                      | Sécurité                                                              |
|-------------------------|-----------------------------------------------------------------------|
| GET collection          | `ROLE_USER` — cloisonné par logement (cf. §2.5)                        |
| GET item                | membre du logement                                                     |
| POST                    | membre du logement **et** (gestionnaire **ou** `occupant` = user)      |
| PUT / PATCH             | membre du logement **et** (gestionnaire **ou** occupant avant et après) |
| DELETE                  | gestionnaire du logement **ou** occupant de la période                 |

```json
{
    "id": 3,
    "startDate": "2026-07-01",
    "endDate": "2026-07-15",
    "notes": "Vacances d'été",
    "occupant": "/api/users/2",
    "property": "/api/properties/1",
    "endNotifiedAt": null
}
```

Dates au format ISO 8601 (`YYYY-MM-DD` accepté pour les `date_immutable`).

- `endNotifiedAt` (datetime, **lecture seule**) — horodatage interne posé par la commande de notification de fin de
  séjour (cf. §11) pour garantir son idempotence. Toute valeur envoyée par le client est ignorée. Vaut `null` tant
  qu'aucune notification de fin n'a été émise pour cette occupation.

### 4.7 Work — `/api/works`

Travaux à réaliser dans l'appartement (bricolage à faire soi-même ou prestation externe). Suivi du cycle de vie via `status`,
priorisation et chiffrage estimé / réel.

| Op                      | Sécurité                                                            |
|-------------------------|---------------------------------------------------------------------|
| GET collection          | `ROLE_USER` — cloisonné par logement (cf. §2.5)                      |
| GET item                | membre du logement                                                   |
| POST                    | membre du logement                                                   |
| PUT / PATCH             | membre du logement **et** (gestionnaire **ou** auteur avant et après) |
| DELETE                  | gestionnaire du logement **ou** auteur des travaux                   |

```json
{
    "id": 5,
    "title": "Repeindre les volets",
    "description": "Décaper et appliquer 2 couches de peinture extérieure",
    "status": "planned",
    "type": "diy",
    "priority": "medium",
    "author": "/api/users/2",
    "property": "/api/properties/1",
    "createdAt": "2026-06-09T09:30:00+00:00",
    "scheduledFor": "2026-07-12",
    "completedAt": null,
    "estimatedCost": 120,
    "actualCost": null
}
```

Champs :

- `title` (string, **requis**, 255 caractères).
- `description` (text, optionnel) — texte long libre.
- `status` (enum, défaut `"suggested"`) : `"suggested"`, `"planned"`, `"in_progress"`, `"done"`, `"cancelled"`.
- `type` (enum, optionnel) : `"diy"` (à faire soi-même) ou `"pro"` (à faire faire).
- `priority` (enum, optionnel) : `"low"`, `"medium"`, `"high"`.
- `author` (IRI User, **requis**) — auto-rempli avec l'utilisateur courant en `POST` si omis.
- `property` (IRI Property, **requis**) — le logement. Auto-rempli en `POST` si l'utilisateur n'en a qu'un (cf. §2.5).
- `createdAt` (datetime, ISO 8601) — **auto-rempli côté serveur à la création**, lecture seule.
- `scheduledFor` (date, `YYYY-MM-DD`, optionnel) — date prévue.
- `completedAt` (datetime, optionnel) — **auto-rempli côté serveur dès que `status` passe à `"done"`** si non fourni
  (sur n'importe quelle opération, `POST` comme `PUT`/`PATCH`).
- `estimatedCost` (int, optionnel) — en euros.
- `actualCost` (int, optionnel) — en euros.

> Implémenté via le processor `App\State\WorkProcessor`. À la création (`POST`), il pose `createdAt = now()` et assigne
> `author = utilisateur courant` si le champ est vide. À chaque écriture (toutes opérations), si `status === "done"` et
> que `completedAt` n'est pas fourni, le serveur le pose à `now()`.

### 4.8 DeviceToken — `/api/device_tokens`

Cible de notification push enregistrée par un client (une ligne par appareil/install). Le `token` est un **Expo push
token** (`"ExponentPushToken[…]"`). Sert au système de notification push (cf. §11).

| Op            | Sécurité                                       |
|---------------|------------------------------------------------|
| GET collection| `ROLE_ADMIN`                                   |
| GET item      | `ROLE_ADMIN` **ou** propriétaire du token      |
| POST          | `ROLE_USER`                                     |
| DELETE        | `ROLE_ADMIN` **ou** propriétaire du token      |

Pas de `PUT`/`PATCH` : la mise à jour se fait par ré-enregistrement (upsert, voir ci-dessous).

**Lecture (`device_token:read`)**

```json
{
    "id": 9,
    "token": "ExponentPushToken[xxxxxxxxxxxxxxxxxxxxxx]",
    "platform": "ios",
    "owner": "/api/users/2",
    "createdAt": "2026-06-11T08:30:00+00:00",
    "lastSeenAt": "2026-06-11T08:30:00+00:00"
}
```

**Écriture (`device_token:write`)** — seuls `token` et `platform` sont acceptés :

```json
{
    "token": "ExponentPushToken[xxxxxxxxxxxxxxxxxxxxxx]",
    "platform": "ios"
}
```

Champs :

- `token` (string, **requis**, unique) — Expo push token de l'install.
- `platform` (enum, **requis**) : `"ios"`, `"android"` ou `"web"`.
- `owner` (IRI User, **lecture seule**) — auto-assigné à l'utilisateur courant en `POST`.
- `createdAt` / `lastSeenAt` (datetime, **lecture seule**) — auto-remplis côté serveur.

> **Upsert par `token`** (processor `App\State\DeviceTokenProcessor`) : ré-enregistrer le même token (typiquement à
> chaque login) **ne crée pas de doublon** — la ligne existante est réutilisée, son `owner` ré-assigné à l'utilisateur
> courant et `lastSeenAt` mis à `now()`. Le client peut donc `POST` le même token sans gérer l'existant lui-même.
> Pour désinscrire un appareil (logout, désactivation des notifs) : `DELETE /api/device_tokens/{id}`. La suppression
> d'un utilisateur supprime ses device tokens en cascade.

### 4.9 Property — `/api/properties`

Un logement. Porte aussi ses coordonnées météo (cf. §12).

| Op             | Sécurité                                        |
|----------------|-------------------------------------------------|
| GET collection | `ROLE_USER` — ne renvoie que ses propres logements |
| GET item       | membre du logement                              |
| POST           | `ROLE_ADMIN`                                    |
| PATCH          | **gestionnaire** du logement                    |
| DELETE         | `ROLE_ADMIN`                                    |

Pas de `PUT`.

```json
{
    "id": 1,
    "name": "Les Marmottes",
    "slug": "les-marmottes",
    "city": "Villard-de-Lans",
    "address": "12 rue des Clarines",
    "latitude": 45.064757765580204,
    "longitude": 5.548400944891808,
    "timezone": "Europe/Paris",
    "secondaryLocationName": "Côte 2000",
    "secondaryLatitude": 45.0186219050606,
    "secondaryLongitude": 5.571823469177524,
    "archived": false
}
```

Champs :

- `name` (string, **requis**, 255) — nom affiché.
- `slug` (string, **requis**, **unique**) — minuscules, chiffres et tirets uniquement (`^[a-z0-9]+(-[a-z0-9]+)*$`).
- `city` (string, **requis**, 255).
- `address` (string, optionnel, 255).
- `latitude` / `longitude` (float, **requis**) — point météo principal, le logement lui-même.
- `timezone` (string, défaut `"Europe/Paris"`) — identifiant de fuseau valide.
- `secondaryLocationName` / `secondaryLatitude` / `secondaryLongitude` (optionnels) — point météo secondaire, typiquement
  un domaine d'altitude. **Les trois vont ensemble** : le point n'est exploité que s'ils sont tous les trois renseignés.
- `archived` (bool, défaut `false`) — un logement archivé reste lisible ; au client de le masquer du sélecteur.

> ⚠️ Supprimer un logement qui porte encore des données échoue sur la contrainte de clé étrangère. Archiver, ou vider
> le logement d'abord.

### 4.10 PropertyMember — `/api/property_members`

Appartenance d'un utilisateur à un logement, avec son rôle local. C'est **la** source de vérité du cloisonnement.

| Op             | Sécurité                                                     |
|----------------|--------------------------------------------------------------|
| GET collection | `ROLE_USER` — appartenances de ses propres logements uniquement |
| GET item       | membre du logement                                           |
| POST           | **gestionnaire** du logement visé                            |
| PATCH          | **gestionnaire** du logement (avant **et** après)            |
| DELETE         | **gestionnaire** du logement                                 |

Pas de `PUT`.

```json
{
    "id": 8,
    "property": "/api/properties/1",
    "user": "/api/users/2",
    "role": "manager"
}
```

- `role` (enum, défaut `"occupant"`) : `"manager"` ou `"occupant"`.
- Contrainte d'unicité sur `(property, user)` : un utilisateur n'a qu'un rôle par logement. Un doublon renvoie `422`.

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
        occupant: '/api/users/2',      // IRI du user connecté
        property: '/api/properties/1', // logement actif — cf. §2.5
    }),
})
```

### 5.6 Lister les données du logement actif

```ts
// L'IRI vient du store de logement actif, amorcé depuis /api/me (§7).
const q = new URLSearchParams({ property: activePropertyIri })

const [notes, items, works] = await Promise.all([
    api(`/notes?${q}`),
    api(`/inventory_items?${q}`),
    api(`/works?${q}`),
])
```

Omettre `property` ne fuite rien — le serveur renvoie alors les données de **tous** les logements de l'utilisateur,
ce qui n'est simplement pas ce qu'affiche un écran dédié à un logement.

---

## 6. Conventions importantes pour l'agent front

1. **Toujours utiliser les IRIs** (`"/api/users/2"`) pour les relations en écriture, pas les objets imbriqués ni les ids
   nus.
2. **PATCH** → `Content-Type: application/merge-patch+json` sinon `415`.
3. Préférer `Accept: application/json` pour des payloads plats ; passer en `application/ld+json` uniquement si on a
   besoin de l'hypermedia/pagination Hydra.
4. Le JWT expire au bout d'1 h : intercepteur qui sur `401` tente un refresh (§2.3), rejoue la requête, et déconnecte
   uniquement si le refresh échoue lui-même.
5. **Pas de breaking changes côté serveur** : si le front a besoin d'un champ supplémentaire ou d'un endpoint custom,
   ouvrir une issue plutôt que de bidouiller. L'API doit rester consommable par d'autres clients (mobile à venir).
6. La pluralisation des URLs suit la convention API Platform : `Category → categories`,
   `InventoryItem → inventory_items`, `ShoppingItem → shopping_items`, `Note → notes`, `Occupation → occupations`,
   `Work → works`, `User → users`, `DeviceToken → device_tokens`, `Property → properties`,
   `PropertyMember → property_members`.
7. **Propager le logement actif** : `?property=<IRI>` sur toutes les lectures métier, champ `property` sur toutes les
   créations. Le serveur cloisonne de toute façon (§2.5), mais sans le paramètre un écran affiche les données de tous
   les logements mélangées.
8. **Recharger sur bascule de logement**, pas seulement au montage : planning, inventaire, courses, notes, travaux et
   météo dépendent tous du logement actif.

---

## 7. Endpoint `/api/me`

`GET /api/me` (sécurité `ROLE_USER`) retourne le profil de l'utilisateur courant, sérialisé avec les groupes `user:read`
et `property:summary`. L'IRI renvoyé pointe vers `/api/users/{id}`. Pas besoin de décoder le JWT côté front pour
connaître l'identité du user connecté.

`memberships` porte les logements de l'utilisateur **embarqués** (pas de simples IRIs) avec son rôle local dans chacun :
c'est la seule requête nécessaire pour amorcer un sélecteur de logement au démarrage.

```json
{
    "@id": "/api/users/2",
    "id": 2,
    "uuid": "80d900ea-28d8-4b3e-9342-f23611ed3fe6",
    "username": "antonin",
    "email": "antonin@example.com",
    "roles": ["ROLE_USER"],
    "memberships": [
        {
            "@id": "/api/property_members/8",
            "role": "manager",
            "property": {
                "@id": "/api/properties/1",
                "id": 1,
                "name": "Les Marmottes",
                "slug": "les-marmottes",
                "city": "Villard-de-Lans",
                "latitude": 45.064757765580204,
                "longitude": 5.548400944891808,
                "timezone": "Europe/Paris",
                "archived": false
            }
        }
    ]
}
```

```ts
type PropertySummary = {
    '@id': string
    id: number
    name: string
    slug: string
    city: string
    latitude: number
    longitude: number
    timezone: string
    archived: boolean
}

type Me = {
    '@id': string
    id: number
    uuid: string
    username: string
    email: string | null
    roles: string[]
    memberships: { role: 'manager' | 'occupant'; property: PropertySummary }[]
}

const me = await api<Me>('/me')
const activeProperty = me.memberships[0]?.property['@id'] // → '/api/properties/1'
```

Cas limites à gérer côté client :

- `memberships` **vide** : l'utilisateur n'a aucun logement. Afficher un état vide explicite, ne lancer aucun appel métier.
- **Un seul** logement : ne pas afficher de sélecteur encombrant, le nom du logement suffit.
- Logement actif mémorisé localement mais **absent** de `memberships` (retiré ou archivé) : replier sur le premier disponible.

Implémenté via `App\State\MeProvider` (cf. `src/State/MeProvider.php`). Si l'utilisateur n'est pas authentifié → `401`.

---

## 8. Endpoint `/api/app/version`

`GET /api/app/version` — **public** (pas d'`Authorization` requis). Renvoie la version publiée et la version minimale
supportée du client mobile, utilisées par l'app pour décider d'afficher un modal de mise à jour au démarrage.

```
GET /api/app/version
Accept: application/json
```

**Réponse 200** :

```json
{
    "latestVersion": "1.0.0",
    "minVersion": "1.0.0",
    "iosStoreUrl": "https://apps.apple.com/app/idXXXXXXXXXX",
    "androidStoreUrl": "https://play.google.com/store/apps/details?id=fr.antoninpamart.villardappli"
}
```

| Champ             | Type   | Sémantique                                                                                     |
|-------------------|--------|------------------------------------------------------------------------------------------------|
| `latestVersion`   | string | Dernière version publiée. Si app < `latestVersion` → modal **dismissible** (« Plus tard »).    |
| `minVersion`      | string | Version minimale supportée. Si app < `minVersion` → modal **bloquant** (pas de « Plus tard »). |
| `iosStoreUrl`     | string | URL App Store ouverte par le bouton « Mettre à jour » sur iOS.                                 |
| `androidStoreUrl` | string | URL Play Store ouverte par le bouton « Mettre à jour » sur Android.                            |

Format des versions : `MAJOR.MINOR.PATCH` (semver simplifié, pas de suffixe pré-release).

> Réponse en `application/json` plat — **pas** une collection JSON-LD (`@type`/`member`). Le client traite l'objet tel
> quel.

### Comparaison côté front

- La version « courante » comparée est `Application.nativeApplicationVersion` (lib `expo-application`), qui provient de
  `app.json` → `expo.version` au moment du build natif.
- Cette version s'affiche sur l'écran **À propos** de l'app (`app/apropos.tsx`).
- Le check est lancé au démarrage en parallèle de l'hydratation de l'auth ; si l'appel échoue (réseau, endpoint
  indisponible), on ne bloque pas l'app.
- ⚠️ Sous **Expo Go**, `nativeApplicationVersion` renvoie la version d'Expo Go, pas celle de l'app — utiliser un dev
  build EAS pour tester réellement.

### Stratégie de bump (backend)

Variables d'env côté `villard-api` :

```dotenv
APP_VERSION_LATEST=1.0.0
APP_VERSION_MIN=1.0.0
APP_STORE_IOS_URL=https://apps.apple.com/app/idXXXXXXXXXX
APP_STORE_ANDROID_URL=https://play.google.com/store/apps/details?id=fr.antoninpamart.villardappli
```

- **Update non bloquant** : bump `APP_VERSION_LATEST` uniquement.
- **Update forcé** (breaking change d'API, retrait d'un endpoint…) : bump `APP_VERSION_MIN` *en plus* de
  `APP_VERSION_LATEST`.

---

## 9. Filtres, recherche & tri

Toutes les collections (`GET /api/<resource>`) acceptent des paramètres de filtrage et de tri via la query string. Les
paramètres se combinent en AND.

### Stratégies courantes

- **SearchFilter `exact`** : égalité stricte. Sur une relation, on accepte l'IRI (`?category=/api/categories/1`) **ou**
  l'id nu (`?category=1`).
- **SearchFilter `ipartial`** : `LIKE %valeur%` insensible à la casse, pour les recherches en texte libre.
- **DateFilter** : suffixes `[before]`, `[strictly_before]`, `[after]`, `[strictly_after]`. Exemple :
  `?createdAt[after]=2026-01-01`.
- **BooleanFilter** : `true` / `false` (ou `1` / `0`).
- **OrderFilter** : `?order[champ]=asc|desc`, plusieurs champs autorisés.

### Récapitulatif par ressource

| Ressource       | Search                                                                                           | Date                   | Order                       | Booléen     |
|-----------------|--------------------------------------------------------------------------------------------------|------------------------|-----------------------------|-------------|
| `Category`      | `name` (ipartial)                                                                                | —                      | `name`                      | —           |
| `Property`      | `name` (ipartial), `slug` (exact), `city` (ipartial)                                             | —                      | `name`, `city`              | `archived`  |
| `PropertyMember`| `property` (exact), `user` (exact), `user.uuid` (exact), `role` (exact)                          | —                      | —                           | —           |
| `InventoryItem` | `property` (exact), `name` (ipartial), `category` (exact), `state` (exact), `note` (ipartial), `location` (ipartial) | —                      | `name`, `quantity`, `state` | —           |
| `ShoppingItem`  | `property` (exact), `name` (ipartial), `category` (exact)                                                            | —                      | `name`, `purchased`         | `purchased` |
| `Note`          | `property` (exact), `title` (ipartial), `content` (ipartial), `author` (exact), `author.uuid` (exact)                | `createdAt`            | `createdAt`, `title`        | —           |
| `Occupation`    | `property` (exact), `occupant` (exact), `occupant.uuid` (exact), `notes` (ipartial)                                  | `startDate`, `endDate` | `startDate`, `endDate`      | —           |
| `Work`          | `property` (exact), `title` (ipartial), `description` (ipartial), `author.uuid` (exact), `status` (exact), `type` (exact), `priority` (exact) | `createdAt`, `scheduledFor` | `createdAt`, `scheduledFor`, `priority`, `status` | —           |

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

# Travaux planifiés ou en cours, prioritaires d'abord
GET /api/works?status=planned&order[priority]=desc&order[scheduledFor]=asc
```

Les filtres apparaissent aussi dans `/api/docs` (Swagger UI) pour chaque collection.

---

## 10. Endpoints non encore exposés

À demander au backend si besoin côté front :

- **Création de mot de passe via `POST /api/users`** — actuellement le champ `password` n'est pas dans `user:write` (
  cf. § 4.1).

---

## 11. Notifications (push + e-mail)

Système de notification multi-canal introduit en **v1.2.0**. Deux canaux : **push** (via Expo) et **e-mail** (via le
mailer Symfony). L'envoi est piloté côté serveur ; le front n'a qu'à **enregistrer son device token** pour recevoir le
push.

### 11.1 Ce que le client doit faire

1. Obtenir l'Expo push token de l'install (côté app mobile, via `expo-notifications`).
2. L'enregistrer : `POST /api/device_tokens` avec `{ "token": "ExponentPushToken[…]", "platform": "ios" }` (cf. §4.8).
   À refaire à chaque login — l'upsert serveur évite les doublons.
3. (Optionnel) Renseigner l'`email` du user (cf. §4.1) pour recevoir aussi les notifications par e-mail.
4. Au logout / refus des notifications : `DELETE /api/device_tokens/{id}`.

### 11.2 Notifications émises

| Notification        | Déclencheur                              | Canaux        | Destinataire      |
|---------------------|------------------------------------------|---------------|-------------------|
| Fin de séjour       | Occupation dont `endDate` = aujourd'hui  | push + e-mail | l'`occupant`      |

- Le push est envoyé à **tous les device tokens** de l'occupant ; l'e-mail à son `email` s'il est renseigné. Un canal
  qui échoue n'empêche pas les autres (`Notifier` tolère les erreurs par canal).
- L'envoi de la notification de fin de séjour est **idempotent** : l'occupation est estampillée `endNotifiedAt`
  (cf. §4.6) une fois notifiée, donc relancer la commande ne renvoie jamais deux fois.

### 11.3 Déclenchement serveur (cron)

La notification de fin de séjour est dispatchée **une fois par jour**. Deux points d'entrée équivalents, qui partagent
la même logique (`OccupationEndNotificationDispatcher`) :

**a) Commande CLI** — pour un cron disposant d'un shell (SSH / VPS) ou pour tester :

```bash
php bin/console app:notifications:dispatch-occupation-end
# option de test : --date=2026-07-15 pour rejouer un jour de référence donné
```

**b) Endpoint HTTP** — `GET /api/cron/occupation-end-notifications` — pour un planificateur qui ne sait qu'appeler une
URL (cas du planificateur de tâches Infomaniak en hébergement mutualisé).

| Élément        | Valeur                                                                              |
|----------------|-------------------------------------------------------------------------------------|
| Méthode        | `GET`                                                                               |
| Auth           | **Public** (`PUBLIC_ACCESS`), protégé par un secret partagé en query string         |
| Query          | `token` — doit être égal à `APP_CRON_SECRET` côté serveur (comparé en constant-time) |
| `200`          | `{ "date": "2026-07-15", "dispatched": 2 }`                                         |
| `403`          | token absent ou invalide                                                            |

```bash
curl "https://<api>/api/cron/occupation-end-notifications?token=<APP_CRON_SECRET>"
# -> {"date":"2026-06-11","dispatched":0}
```

Configuration côté Infomaniak (Hébergement → Web → Planificateur de tâches) : URL à exécuter =
`https://<api>/api/cron/occupation-end-notifications?token=<APP_CRON_SECRET>`, fréquence **1×/jour**. Le secret en clair
dans l'URL doit être identique à `APP_CRON_SECRET` (défini en prod via `.env.prod.local`, jamais commité).

> Le front n'appelle ni la commande ni cet endpoint — c'est purement backend. Côté client, seule l'inscription du device
> token (§4.8) et l'`email` (§4.1) conditionnent la réception. L'envoi reste **idempotent** quel que soit le point
> d'entrée, donc déclencher plusieurs fois par jour est sans risque.
