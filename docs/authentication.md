# Authentification

L'API est entièrement **stateless**. L'authentification repose sur **LexikJWTAuthenticationBundle 3** (JWT signé RSA) couplé à **gesdinet/jwt-refresh-token-bundle** (refresh tokens avec rotation). Login par username/password → couple `token` + `refresh_token`. Le `token` est ensuite passé en `Authorization: Bearer` sur toutes les requêtes. Quand il expire (1 h), le client échange son `refresh_token` contre un nouveau couple via `POST /api/token/refresh`.

## Firewall Symfony

`config/packages/security.yaml` définit trois firewalls :

```yaml
firewalls:
    login:
        pattern: ^/api/login
        stateless: true
        provider: app_user_provider          # cherche le user par username
        json_login:
            check_path: /api/login
            success_handler: lexik_jwt_authentication.handler.authentication_success
            failure_handler: lexik_jwt_authentication.handler.authentication_failure
    token_refresh:
        pattern: ^/api/token/refresh
        stateless: true
        provider: app_jwt_provider           # cherche le user par UUID
        refresh_jwt:
            check_path: /api/token/refresh
    api:
        pattern: ^/api
        stateless: true
        provider: app_jwt_provider           # cherche le user par UUID
        jwt: ~
```

Deux user providers Doctrine :

- `app_user_provider` — `property: username`. Utilisé uniquement au login (l'utilisateur s'identifie par son nom).
- `app_jwt_provider` — `property: uuid`. Utilisé pour résoudre l'utilisateur à partir du claim du JWT.

`access_control` :

```yaml
- { path: ^/api/login,          roles: PUBLIC_ACCESS }
- { path: ^/api/token/refresh,  roles: PUBLIC_ACCESS }
- { path: ^/api/docs,           roles: PUBLIC_ACCESS }
- { path: ^/api,                roles: IS_AUTHENTICATED_FULLY }
```

`/api/token/refresh` est public au sens du firewall (pas de JWT requis pour l'appeler) ; la sécurité repose sur la possession d'un refresh token valide non encore consommé.

## Configuration JWT

`config/packages/lexik_jwt_authentication.yaml` :

```yaml
lexik_jwt_authentication:
    secret_key: '%env(resolve:JWT_SECRET_KEY)%'
    public_key: '%env(resolve:JWT_PUBLIC_KEY)%'
    pass_phrase: '%env(JWT_PASSPHRASE)%'
    token_ttl: '%env(JWT_TOKEN_TTL)%'
    user_id_claim: uuid
```

Variables d'environnement correspondantes (`.env`) :

```dotenv
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=<changer-en-prod>
JWT_TOKEN_TTL=3600     # 1 heure
```

`user_id_claim: uuid` signifie que le JWT porte l'UUID immuable de l'utilisateur, pas son `username`. Conséquence : renommer un user n'invalide pas ses tokens en circulation.

## Génération des clés

Commande Lexik (recommandée) :

```bash
php bin/console lexik:jwt:generate-keypair
```

Génération manuelle si la commande n'est pas disponible :

```bash
mkdir -p config/jwt
openssl genrsa -aes256 -passout pass:"$JWT_PASSPHRASE" -out config/jwt/private.pem 4096
openssl rsa -pubout -in config/jwt/private.pem -passin pass:"$JWT_PASSPHRASE" -out config/jwt/public.pem
```

> Les clés ne sont **jamais** versionnées. En production, monter le dossier `config/jwt/` depuis un volume Docker ou injecter les PEM via un secret manager.

## Flux d'utilisation

### Login

```http
POST /api/login
Content-Type: application/json

{ "username": "admin", "password": "admin" }
```

Réponse 200 :

```json
{
    "token": "eyJ0eXAiOiJKV1Qi...",
    "refresh_token": "7e1f741b392bcd1cf078276fa68327f0..."
}
```

Réponse 401 si identifiants invalides.

### Requêtes authentifiées

```http
GET /api/me
Authorization: Bearer eyJ0eXAiOi...
Accept: application/json
```

### Expiration & refresh

`JWT_TOKEN_TTL=3600` → 1 heure. Au-delà, l'API répond `401`. Le client échange alors son `refresh_token` contre un nouveau couple :

```http
POST /api/token/refresh
Content-Type: application/json

{ "refresh_token": "7e1f741b392bcd1cf078276fa68327f0..." }
```

Réponse 200 — **nouveau** couple :

```json
{
    "token": "eyJ0eXAiOiJKV1Qi...",
    "refresh_token": "9273a5b26cea54234b63e4c37128fc2c..."
}
```

Réponse 401 si le refresh est inconnu, expiré, ou déjà consommé.

## Refresh tokens

Géré par `gesdinet/jwt-refresh-token-bundle`. Configuration : `config/packages/gesdinet_jwt_refresh_token.yaml`.

```yaml
gesdinet_jwt_refresh_token:
    refresh_token_class: App\Entity\RefreshToken
    ttl: 2592000           # 30 jours
    ttl_update: true       # à chaque refresh, le nouveau token repart pour 30 jours
    single_use: true       # rotation : un refresh token ne sert qu'une fois
    token_parameter_name: refresh_token
```

### Rotation (`single_use: true`)

Chaque appel à `/api/token/refresh` :
1. Valide le refresh token reçu (existe, pas expiré, non révoqué).
2. Émet un nouveau JWT **et** un nouveau refresh token.
3. Supprime / invalide l'ancien.

Conséquence : un refresh token volé devient inutilisable dès que la victime l'a utilisé une fois. À l'inverse, si l'attaquant frappe le premier, le prochain refresh du légitime échouera en 401 — signal côté front qu'on peut traiter comme une compromission (forcer un re-login complet).

### Fenêtre glissante (`ttl_update: true`)

Le nouveau refresh token émis lors d'un refresh repart pour 30 jours. Un utilisateur actif ne se reconnecte jamais ; un utilisateur inactif > 30 jours doit refaire un `POST /api/login`.

### Stockage

Table `refresh_tokens` (entité `App\Entity\RefreshToken` qui étend `Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken`).

- `refresh_token` (string, unique) — la valeur en clair (pas hashée par défaut côté bundle).
- `username` (string) — **contient l'UUID de l'utilisateur**, pas le username. Nom de colonne legacy du bundle ; cohérent avec le `app_jwt_provider` qui résout les users par UUID.
- `valid` (datetime) — date d'expiration.

### Nettoyage

Les refresh tokens expirés ne sont pas purgés automatiquement. À exécuter périodiquement (cron quotidien en prod) :

```bash
php bin/console gesdinet:jwt:clear     # purge tous les refresh expirés
php bin/console gesdinet:jwt:revoke <token>   # révoque un token précis
```

## L'endpoint `/api/me`

Endpoint custom défini sur l'entité `User` (`src/Entity/User.php`) :

```php
new Get(
    uriTemplate: '/me',
    normalizationContext: ['groups' => ['user:read'], 'item_uri_template' => '/users/{id}'],
    security: "is_granted('ROLE_USER')",
    name: 'me',
    provider: MeProvider::class,
),
```

`MeProvider` (`src/State/MeProvider.php`) récupère l'utilisateur courant via le service `Security` et le renvoie. Si pas d'utilisateur authentifié → `401 UnauthorizedHttpException`.

C'est la manière propre d'exposer « moi » sans dévoiler d'ID dans l'URL et sans dupliquer la logique côté client.

## Rôles

| Rôle | Attribué à | Octroyé par |
|------|-----------|-------------|
| `ROLE_USER` | tout utilisateur authentifié | implicite (cf. `User::getRoles()`) |
| `ROLE_ADMIN` | utilisateurs admin | `User::setRoles(['ROLE_ADMIN'])` |

`User::getRoles()` ajoute automatiquement `ROLE_USER` à chaque utilisateur. La hiérarchie n'est pas configurée explicitement, donc `ROLE_ADMIN` **n'implique pas** `ROLE_USER` au niveau du `RoleHierarchy` — c'est `getRoles()` qui garantit la présence de `ROLE_USER` pour tous.

La matrice des permissions par opération est documentée dans [`resources.md`](resources.md).

## Création d'un utilisateur

Côté CLI (recommandé, hors fixtures) :

```bash
php bin/console app:create-user alice              # admin
php bin/console app:create-user alice --no-admin   # utilisateur simple
```

Le mot de passe est demandé en interactif (saisie masquée). Hashage automatique via `UserPasswordHasherInterface` (algorithme `auto` cf. `security.yaml`).

Côté API : `POST /api/users` est ouvert uniquement à `ROLE_ADMIN`. **Attention** : le champ `password` n'est pas dans le groupe `user:write` → impossible de créer un utilisateur avec mot de passe par l'API tel quel. Utilise la commande CLI ou ajoute le mapping `password` au denormalization quand ce sera nécessaire.

## Points d'attention sécurité

- **Toujours en HTTPS en production**. Le JWT transite dans l'en-tête `Authorization` ; en clair sur le réseau il est rejouable.
- **Pas de stockage du JWT dans un cookie sans flags** : côté front, `localStorage` reste l'option la plus simple, à condition d'avoir un CSP strict (XSS = vol de token).
- **Rotation de la clé privée** : prévoir la procédure (invalide tous les tokens en cours — c'est par construction).
