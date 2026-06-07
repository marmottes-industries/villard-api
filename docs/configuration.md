# Configuration

Référence des variables d'environnement et fichiers de configuration de l'API.

## Fichiers `.env`

Symfony charge dans cet ordre (priorité décroissante) :

1. `.env.$APP_ENV.local` (non versionné)
2. `.env.local` (non versionné)
3. `.env.$APP_ENV` (versionné)
4. `.env` (versionné, défauts)

Le dépôt contient `.env`, `.env.dev` et `.env.prod`. Les `.local.*` sont à créer pour les secrets et overrides.

## Variables d'environnement

### Application

| Variable | Défaut | Description |
|----------|--------|-------------|
| `APP_ENV` | `dev` | `dev`, `prod`, `test`. |
| `APP_SECRET` | *(vide)* | Secret Symfony. **À définir en prod**. |
| `APP_SHARE_DIR` | `var/share` | Dossier de partage applicatif. |
| `DEFAULT_URI` | `http://localhost` | Base utilisée pour générer des URLs depuis la CLI. |

### Base de données (Doctrine + MariaDB)

| Variable | Exemple | Description |
|----------|---------|-------------|
| `DATABASE_URL` | `mysql://db:db@127.0.0.1:3306/db?serverVersion=10.11.2-MariaDB&charset=utf8mb4` | DSN complet. **Toujours inclure `serverVersion`** pour que Doctrine choisisse la bonne plateforme. |

> La config `doctrine.yaml` contient `identity_generation_preferences` pour PostgreSQL — c'est un résidu du squelette, sans effet sur MariaDB.

En test, le suffixe `_test` est ajouté automatiquement (`dbname_suffix: '_test%env(default::TEST_TOKEN)%'`).

### CORS (`nelmio/cors-bundle`)

| Variable | Exemple dev | Description |
|----------|-------------|-------------|
| `CORS_ALLOW_ORIGIN` | `^https?://(localhost\|127\.0\.0\.1)(:[0-9]+)?$` | Regex (car `origin_regex: true`) appliquée à chaque origine. |

Configuration globale dans `config/packages/nelmio_cors.yaml` :

```yaml
allow_methods: ['GET', 'OPTIONS', 'POST', 'PUT', 'PATCH', 'DELETE']
allow_headers: ['Content-Type', 'Authorization']
expose_headers: ['Link']
max_age: 3600
paths:
    '^/': null
```

En production, restreindre `CORS_ALLOW_ORIGIN` à la regex exacte des domaines des clients officiels (front + futur mobile web).

### JWT (`lexik/jwt-authentication-bundle`)

| Variable | Défaut | Description |
|----------|--------|-------------|
| `JWT_SECRET_KEY` | `%kernel.project_dir%/config/jwt/private.pem` | Chemin clé privée RSA. |
| `JWT_PUBLIC_KEY` | `%kernel.project_dir%/config/jwt/public.pem` | Chemin clé publique RSA. |
| `JWT_PASSPHRASE` | *(défaut dev dans `.env`)* | Passphrase de la clé privée. **À régénérer en prod**. |
| `JWT_TOKEN_TTL` | `3600` | Durée de vie du token en secondes (1 h). |

Génération des clés : voir [`authentication.md`](authentication.md#génération-des-clés).

### MariaDB (dev, via Docker Compose)

`compose.yaml` lit ces variables (avec valeurs par défaut) :

| Variable | Défaut |
|----------|--------|
| `MARIADB_VERSION` | `lts` |
| `MARIADB_DATABASE` | `db` |
| `MARIADB_USER` | `db` |
| `MARIADB_PASSWORD` | `db` |
| `MARIADB_ROOT_PASSWORD` | `root` |

`compose.override.yaml` (dev uniquement) expose `3306:3306` et ajoute un service Mailpit (interface web sur `:8025`).

## Fichiers de configuration clés

| Fichier | Rôle |
|---------|------|
| `config/packages/api_platform.yaml` | API Platform : stateless, formats JSON/JSON-LD, PATCH en `merge-patch+json`, sécurité Swagger via en-tête `Authorization`. |
| `config/packages/doctrine.yaml` | Mapping attribut sur `src/Entity`, suffixe `_test` en environnement de test. |
| `config/packages/lexik_jwt_authentication.yaml` | Lecture des clés, TTL, `user_id_claim: uuid`. |
| `config/packages/nelmio_cors.yaml` | Politique CORS globale. |
| `config/packages/security.yaml` | Firewalls (`login`, `api`), providers Doctrine (username / uuid), `access_control`. |
| `config/routes.yaml` | Route `POST /api/login` + import des routes de contrôleurs (vide pour l'instant). |
| `compose.yaml` / `compose.override.yaml` | MariaDB + Mailpit dev. |
| `.symfony.local.yaml` | Demande à la Symfony CLI de lancer `docker compose up -d` au démarrage du serveur. |

## Notes prod

- Régénérer `JWT_PASSPHRASE` et `APP_SECRET`.
- Restreindre `CORS_ALLOW_ORIGIN`.
- Monter `config/jwt/` depuis un volume sécurisé (clés non incluses dans l'image Docker).
- Désactiver les fixtures (bundle déclaré uniquement en `dev` et `test` dans `config/bundles.php`).
- `cache:clear` puis `cache:warmup` dans le pipeline de build.
