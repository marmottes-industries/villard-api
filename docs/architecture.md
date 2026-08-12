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

API Platform expose **tout** par défaut. Pour éviter les fuites, `User`, `Property` et `PropertyMember` utilisent des **groupes de sérialisation** :

| Entité | Normalisation | Dénormalisation | Groupe transverse |
|--------|---------------|-----------------|-------------------|
| `User` | `user:read` | `user:write` | — |
| `Property` | `property:read` | `property:write` | `property:summary` |
| `PropertyMember` | `member:read` | `member:write` | `property:summary` |

Le `password` n'est dans aucun groupe → non exposable. `property:summary` est un sous-ensemble activé en plus de `user:read` sur l'opération `/api/me` : il embarque les logements dans `memberships` au lieu de simples IRIs, ce qui suffit à amorcer le sélecteur de logement d'un client en un appel.

Les autres entités (Category, Room, InventoryItem, ShoppingItem, Note, Occupation, Work) n'ont **pas encore** de groupes définis : tous leurs champs scalaires sont lus/écrits par défaut. C'est un point d'évolution prévu.

### 5. Cloisonnement multi-logements

Toutes les données métier appartiennent à un **logement** (`Property`). Le cloisonnement est appliqué côté serveur, jamais par la discipline du client :

```
              GET /api/notes?property=/api/properties/1
                             │
                             ▼
              ┌──────────────────────────────┐
   lecture    │  PropertyScopeExtension      │  AND n.property IN (:logements du user)
              │  collection + item           │  → collections filtrées, items en 404
              └──────────────────────────────┘
              ┌──────────────────────────────┐
   écriture   │  PropertyVoter               │  PROPERTY_VIEW / _CONTRIBUTE / _MANAGE
              │  + PropertyScopeProcessor    │  repli mono-logement au POST, sinon 422
              └──────────────────────────────┘
```

- **`App\Doctrine\PropertyScopeExtension`** implémente `QueryCollectionExtensionInterface` **et** `QueryItemExtensionInterface`. L'extension de collection seule laisserait `GET /api/notes/42` accessible à n'importe qui : les deux sont obligatoires. Elle s'applique à toute ressource implémentant `App\Contract\PropertyScopedInterface`, plus `Property` et `PropertyMember`. Ajouter une ressource métier revient donc à implémenter cette interface — impossible d'oublier le filtre.
- **`App\Security\Voter\PropertyVoter`** garde les écritures, via les attributs `PROPERTY_VIEW`, `PROPERTY_CONTRIBUTE` et `PROPERTY_MANAGE`, résolus depuis `PropertyMemberRepository`. Cf. [`authentication.md`](authentication.md#rôles-locaux-et-propertyvoter).
- **`App\State\PropertyScopeProcessor`** décore `PersistProcessor` et applique le repli mono-logement au `POST`. Les processors `NoteProcessor` et `WorkProcessor` se chaînent dessus plutôt que sur `PersistProcessor` directement.

**Point d'ordonnancement à connaître** : dans le pipeline d'API Platform, `securityPostDenormalize` et la validation s'exécutent **avant** les processors. Un champ rempli par un processor ne peut donc pas être exigé par une contrainte de validation ni testé par une expression de sécurité. C'est pourquoi `property` n'a pas de `Assert\NotNull` et pourquoi les expressions `POST` tolèrent explicitement un logement nul, le processor tranchant ensuite (assignation ou 422).

**Exception à cette tolérance : `Room`.** Ses écritures exigent `PROPERTY_MANAGE`, et son `POST` ne tolère **pas** de logement nul. L'échappatoire n'existe chez les autres que pour les builds mobiles antérieurs au multi-logements ; or aucun client installé ne poste de pièce. La conserver dégraderait silencieusement `MANAGE` en `CONTRIBUTE`, puisque le repli du processor ne revérifie que `CONTRIBUTE`.

#### Cohérence pièce / logement

Une `Room` appartenant elle-même à un logement, un utilisateur membre de **deux** logements peut rattacher un article du logement A à une pièce du logement B : les deux IRI lui étant accessibles, ni l'extension ni le voter ne s'y opposent. Deux contrôles complémentaires ferment ce trou, sur les entités marquées `App\Contract\RoomScopedInterface` :

- **`App\Validator\RoomBelongsToProperty`** (contrainte de classe) couvre le `POST` avec logement explicite et tous les `PUT` / `PATCH` — ces derniers n'ont **aucun** processor sur `InventoryItem`, c'est donc le seul filet possible. Elle ne compare que si les deux valeurs sont non nulles : au `POST` où le client omet `property`, le repli n'a pas encore tourné et comparer produirait un faux 422 sur le cas nominal.
- **`PropertyScopeProcessor`** rattrape le cas restant, après résolution du repli : un `ROLE_ADMIN` par ailleurs membre d'un unique logement se voit appliquer ce logement, alors que le bypass du voter lui rend visibles les pièces de tous les autres.

Les deux comparent des identifiants, pas des instances : rien ne garantit que Doctrine serve le même objet `Property` pour un proxy non initialisé et pour l'IRI résolue depuis le payload.

`ROLE_ADMIN` court-circuite l'extension comme le voter : c'est un super-rôle global qui traverse tous les logements.

### 6. Formats supportés

| Header | Format en sortie |
|--------|------------------|
| `Accept: application/ld+json` *(défaut)* | JSON-LD + Hydra (pagination, `@id`, `@type`, `hydra:member`, etc.) |
| `Accept: application/json` | JSON pur (tableau d'objets en collection) |

Pour `PATCH`, **seul** `application/merge-patch+json` est accepté (cf. `patch_formats` dans `api_platform.yaml`).

## Arborescence du code

```
src/
├── ApiResource/        # Ressources API découplées des entités Doctrine
│   └── WeatherForecast.php
├── Command/            # Commandes console
│   ├── CreateUserCommand.php       # app:create-user
│   └── ImportRoomsFromCategoriesCommand.php # app:rooms:import-from-categories
├── Contract/
│   ├── PropertyScopedInterface.php # marque une entité comme rattachée à un logement
│   └── RoomScopedInterface.php     # ... et rattachable à une pièce de ce logement
├── Controller/         # Quasi vide — tout est généré par API Platform
├── DataFixtures/       # Fixtures dev/test (deux logements, appartenances disjointes)
│   └── AppFixtures.php
├── Doctrine/
│   └── PropertyScopeExtension.php  # cloisonnement des lectures, collections + items
├── Entity/             # Entités Doctrine = ressources API Platform
│   ├── Category.php
│   ├── InventoryItem.php
│   ├── Note.php
│   ├── Occupation.php
│   ├── Property.php
│   ├── PropertyMember.php
│   ├── Room.php
│   ├── ShoppingItem.php
│   ├── User.php
│   └── Work.php
├── Enum/
│   ├── PropertyRole.php # rôle local dans un logement (manager / occupant)
│   ├── RoomType.php     # nature d'une pièce (kitchen, bedroom, …), nullable
│   └── State.php        # État d'un InventoryItem (ok / worn / replace)
├── Repository/         # Repos Doctrine
├── Security/Voter/
│   └── PropertyVoter.php           # cloisonnement des écritures
├── State/
│   ├── MeProvider.php              # Provider API Platform pour GET /api/me
│   └── PropertyScopeProcessor.php  # repli mono-logement au POST
├── Validator/
│   └── RoomBelongsToProperty.php   # cohérence pièce / logement (cf. § 5)
└── Kernel.php
```

## Validation du cloisonnement

Aucune suite de tests n'est câblée (cf. `AUDIT.md`). Le script `bin/check-property-scope.sh` couvre les scénarios de cloisonnement en curl contre `https://127.0.0.1:8000`, à partir des fixtures à deux logements :

```bash
php bin/console doctrine:fixtures:load --no-interaction
bash bin/check-property-scope.sh
```

Il vérifie les collections sans filtre, le paramètre `?property=` forgé, l'accès item interdit, les écritures (repli mono-logement, 422 en multi-logements, logement non autorisé), la distinction gestionnaire/occupant et la traversée `ROLE_ADMIN`. Il **modifie la base** : recharger les fixtures avant chaque exécution.

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
