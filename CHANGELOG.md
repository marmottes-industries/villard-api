# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Multi-property support. Business resources (`Occupation`, `Work`, `InventoryItem`, `ShoppingItem`, `Note`) now belong
  to a `Property` and are partitioned server-side, by a Doctrine query extension on reads and a voter on writes.
- `Property` (`/api/properties`) and `PropertyMember` (`/api/property_members`) resources, with a local
  `manager` / `occupant` role per property.
- `GET /api/me` now returns the user's properties and local role in each, enough to seed a client property switcher in
  a single call.
- `?property=<IRI>` filter on every business collection.
- `bin/check-property-scope.sh` — HTTP scenarios covering the partitioning, in the absence of a test suite.

### Changed

- Deleting an `InventoryItem` or a `ShoppingItem` now requires the property's local **manager** instead of a global
  `ROLE_ADMIN`. `ROLE_ADMIN` keeps the capability.
- A property's local manager can now edit the notes, stays and works of other members of that property — previously a
  `ROLE_ADMIN`-only capability.
- `ROLE_ADMIN` traverses every property, both in queries and in the voter. Documented in `docs/authentication.md`.

### Fixed

- `POST /api/notes` and `POST /api/works` returned `403` to every non-`ROLE_ADMIN` user. `securityPostDenormalize` runs
  before the processors, so `object.getAuthor() == user` was always false for clients that omit `author` — which both
  the Vue front and the Expo app do.

### Migration

- `Version20260810155317` adds the `property` / `property_member` tables, backfills every existing row to the historical
  "Les Marmottes" property, and makes every existing user a manager of it. Columns are added nullable, filled, then
  constrained; `down()` is symmetrical.

## [1.3.0] - 2026-06-22

### Added

- Add weather forecast endpoint from open-Meteo data. 16 days forecast. For Villard and Côte 2000.

## [1.2.1] - 2026-06-11

### Fixed

- Add cron job for notification

## [1.2.0] - 2026-06-11

### Added
- Add notification mail and push
- Add github action for deployment

## [1.1.1] - 2026-06-10

### Fixed
- Add rate limiter for login 
- Add business validation constraints
- Disable API docs in production
- Restrict deletion to admin only

## [1.1.0] - 2026-06-09

### Added
- Extend refresh token to 90 days
- add app version controller and endpoint
- introduce work entity and endpoint

## [1.0.0] - 2026-06-08

### Added

- Initial release.

[unreleased]: https://github.com/marmottes-industries/villard-api/compare/v1.3.0...main
[1.3.0]: https://github.com/marmottes-industries/villard-api/compare/v1.2.1...v1.3.0
[1.2.1]: https://github.com/marmottes-industries/villard-api/compare/v1.2.0...v1.2.1
[1.2.0]: https://github.com/marmottes-industries/villard-api/compare/1.1.1...v1.2.0
[1.1.1]: https://github.com/marmottes-industries/villard-api/compare/1.1.0...1.1.1
[1.1.0]: https://github.com/marmottes-industries/villard-api/compare/1.0.0...1.1.0
[1.0.0]: https://github.com/marmottes-industries/villard-api/compare/1.0.0...main
