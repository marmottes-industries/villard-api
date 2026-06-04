# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project context

"Les Marmottes" — self-hosted family apartment management app (occupancy calendar + inventory tracking). This repo is the backend API only; the Vue 3 SPA lives in a separate repo (`appart-front`). The API is designed to be consumed by multiple clients (web + future mobile) with no breaking changes required server-side.

## Stack

- **PHP 8.4+ / Symfony 8.1** with API Platform 4.x
- **Doctrine ORM** with attribute-based mapping (`src/Entity/`)
- **MariaDB** (dev, via Docker) — note: README mentions PostgreSQL but the actual stack uses MariaDB
- JWT authentication (LexikJWT — planned, not yet installed)
- `nelmio/cors-bundle` for cross-origin support

## Common commands

```bash
# Start everything (Symfony CLI manages Docker automatically via .symfony.local.yaml)
symfony server:start

# Or manually start DB then run dev server
docker compose up -d
symfony serve

# Doctrine
php bin/console doctrine:migrations:diff        # generate migration from entity changes
php bin/console doctrine:migrations:migrate     # apply migrations
php bin/console doctrine:fixtures:load          # load dev fixtures (once installed)

# Clear cache
php bin/console cache:clear
```

## Architecture

API Platform is configured stateless (`defaults.stateless: true`) with `vary` cache headers for multi-client support. All entities go in `src/Entity/` and are exposed via `#[ApiResource]` attributes — API Platform auto-generates CRUD endpoints.

Key config files:
- `config/packages/api_platform.yaml` — API Platform settings (stateless, cache headers)
- `config/packages/doctrine.yaml` — ORM mapping (attribute-based, `src/Entity/`)
- `config/packages/security.yaml` — firewall config (JWT auth to be wired here)
- `config/packages/nelmio_cors.yaml` — CORS origins per environment
- `compose.yaml` — MariaDB service for dev

## Development notes

- The Symfony CLI auto-starts Docker (`docker compose up -d`) when running `symfony server:start` — configured in `.symfony.local.yaml`.
- API is browsable at `/api` (Swagger UI) in dev.
- Use **serialization groups** (`normalizationContext` / `denormalizationContext`) on `#[ApiResource]` to control field exposure — API Platform exposes everything by default.
- Migrations go in `migrations/`, generated from entity attribute changes via `doctrine:migrations:diff`.
- Test DB uses a suffix: `_test` (configured in `doctrine.yaml` `when@test`).
