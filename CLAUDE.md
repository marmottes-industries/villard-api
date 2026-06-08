# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project context

"Les Marmottes" — self-hosted family apartment management app (occupancy calendar + inventory tracking). This repo is the **backend API only**; the Vue 3 SPA lives in a separate repo (`appart-front`). The API is designed to be consumed by multiple clients (web + future mobile) with no breaking changes required server-side.

Extensive human documentation already exists in `docs/` (architecture, authentication, resources, configuration, development, getting-started) and `API.md` (reference written for the front-end agent). Read those before duplicating explanations.

## Stack

- **PHP 8.4+ / Symfony 8.1** with API Platform 4.3
- **Doctrine ORM 3** with attribute-based mapping (`src/Entity/`)
- **MariaDB** (dev, via Docker Compose)
- **LexikJWTAuthenticationBundle 3** — JWT signed with RSA keys
- `nelmio/cors-bundle` for cross-origin support
- `doctrine/doctrine-fixtures-bundle` (dev/test)

## Common commands

```bash
# Start everything (Symfony CLI auto-starts Docker via .symfony.local.yaml)
symfony server:start

# Doctrine
php bin/console doctrine:migrations:diff        # generate migration from entity changes
php bin/console doctrine:migrations:migrate     # apply migrations
php bin/console doctrine:fixtures:load          # load dev fixtures

# JWT keypair (one-time, after install; passphrase in .env)
php bin/console lexik:jwt:generate-keypair

# Users
php bin/console app:create-user <username>             # admin by default
php bin/console app:create-user <username> --no-admin

# Symfony tooling
php bin/console debug:router
php bin/console cache:clear
```

No test suite is wired yet (no `phpunit` in composer.json, no `tests/` directory). The `App\Tests\` PSR-4 autoload prefix is declared in `composer.json` but unused.

## Architecture

### Stateless, multi-client by design

`config/packages/api_platform.yaml` sets `defaults.stateless: true` with `Vary: Content-Type, Authorization, Origin` so HTTP caches can differentiate per user and per client. No server sessions — every request carries its JWT.

### Two firewalls (`config/packages/security.yaml`)

- `login` (`^/api/login`) — `json_login` against `app_user_provider` (looks up `User.username`), returns JWT on success.
- `api` (`^/api`) — stateless JWT auth. Critically, it uses **a different provider** (`app_jwt_provider`) that loads users by **UUID**, matching the `user_id_claim: uuid` in `lexik_jwt_authentication.yaml`. This means a username change does NOT invalidate tokens in flight. Don't switch the JWT identifier back to `username` without understanding this.

`/api/login` and `/api/docs` are `PUBLIC_ACCESS`; everything else under `/api` requires `IS_AUTHENTICATED_FULLY`.

### Auto-generated endpoints from `#[ApiResource]`

Every entity in `src/Entity/` carrying `#[ApiResource]` exposes CRUD under `/api/<resource>` automatically. Per-operation security goes through `security:` / `securityPostDenormalize:` attributes (see `Note`, `Occupation`, `User` for examples).

### Serialization groups — partially applied

Only `User` uses serialization groups (`user:read` / `user:write`) — its `password` field is in no group and therefore not exposable. **Other entities (Category, InventoryItem, ShoppingItem, Note, Occupation) currently expose all scalar fields by default.** When adding fields to those entities, assume they will be exposed; when adding sensitive fields, introduce groups first.

### `GET /api/me`

Implemented via a custom API Platform state provider: `src/State/MeProvider.php`. The route is declared as an operation on the `User` resource and resolves to the currently authenticated user. This is the pattern to follow for other "current-user-scoped" endpoints — don't add a controller.

### Source layout

```
src/
├── ApiResource/   # (empty) — for resources decoupled from Doctrine entities, if needed
├── Command/       # console commands (e.g. CreateUserCommand → app:create-user)
├── Controller/    # (empty) — API Platform generates everything; avoid adding controllers
├── DataFixtures/
├── Entity/        # Doctrine entities = API Platform resources
├── Enum/          # e.g. State enum (ok/worn/replace) for InventoryItem
├── Repository/
└── State/         # API Platform providers/processors (e.g. MeProvider)
```

### Content negotiation

- `Accept: application/ld+json` (default) → JSON-LD + Hydra (pagination, `@id`, `@type`, `hydra:member`).
- `Accept: application/json` → plain JSON.
- `PATCH` requires `Content-Type: application/merge-patch+json` (only format declared in `patch_formats`).

## Development notes

- The Symfony CLI auto-starts Docker (`docker compose up -d`) when running `symfony server:start` — configured in `.symfony.local.yaml`.
- API browsable at `https://127.0.0.1:8000/api/docs` (Swagger UI, public in dev).
- Migrations live in `migrations/`, generated from entity attribute changes via `doctrine:migrations:diff`.
- Test DB uses suffix `_test` (configured in `doctrine.yaml` `when@test`); password hashing is weakened in `when@test` (security.yaml).
- CORS origins are driven by `CORS_ALLOW_ORIGIN` (regex) — set per environment in `.env.*.local`.
- Refresh tokens are **not** implemented; clients re-POST `/api/login` when the JWT expires. (Listed as an evolution in `ROADMAP.md` / `docs/architecture.md`.)
