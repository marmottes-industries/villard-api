# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [2.1.0] - 2026-08-12

### Added

- `Room` (`/api/rooms`) — a room within a property (« Chambre 1 », « Cabane à skis »), property-scoped like
  every other business resource. Unlike them, its writes require the property's local **manager**, and its `POST` does
  not tolerate a null `property`: the escape hatch only exists for mobile builds predating multi-property support, and
  keeping it would silently downgrade `MANAGE` to `CONTRIBUTE`.
- `App\Enum\RoomType` — `kitchen`, `bathroom`, `toilet`, `bedroom`, `living_room`, `office`, `laundry`, `hallway`,
  `garage`, `cellar`, `attic`, `outdoor`. Nullable, with no « other » case: an unusual room stays untyped rather than
  carrying a wrong type. No icon is exposed — that is a client decision, and the two clients don't share an icon set.
- `room` on `InventoryItem` and `Work`, both optional. Deleting a room detaches them (`ON DELETE SET NULL`) instead of
  failing.
- `App\Validator\RoomBelongsToProperty` — a room must belong to the same property as the resource referencing it. A user
  who is a member of two properties could otherwise write an inconsistent pair, since both IRIs resolve for them.
- `app:rooms:import-from-categories` — backfills rooms from legacy categories, per property. Idempotent, with
  `--dry-run` and `--property=<slug>`.
- `bin/check-property-scope.sh` covers rooms: manager-only writes, and the three paths of the room/property consistency
  check.

### Changed

- `InventoryItem.category` is now nullable and **deprecated**, superseded by `room`. The real categories in the database
  (« Cuisine », « Chambre », « Salle de bain ») were rooms all along, but `Category` is deliberately shared across
  properties and therefore cannot be split per property. It stays writable until both clients ship, then goes away in
  the next major.
- `Category` keeps its role on `ShoppingItem`, where a shared aisle across properties makes sense.
- `InventoryItem.location` is unchanged. It never was the room: it is the spot _inside_ the room (« placard du
  haut »), and it stays exactly as it is.
- `bin/check-property-scope.sh` reads its base URL from `$API`, so it can run against a plain HTTP server when the local
  TLS certificate isn't installed.

### Migration

`Version20260812091248` creates the `room` table, adds a nullable `room_id` to `inventory_item` and `work`, **backfills
the rooms**, and only then relaxes `inventory_item.category_id` to nullable.

The backfill is done in the migration rather than in the command on purpose: extracting it would leave a production
inventory entirely room-less between `migrate` and the command's first run. One room is created per
`(property, category.name)` pair actually in use — never per `category.id`, since a single shared category must yield a
distinct room in each property. Categories used only for shopping (« Épicerie », « Produits frais ») create no room.

Order matters: relaxing `category_id` before the backfill would leave orphaned items with no way to reclassify them.

`app:rooms:import-from-categories` remains useful afterwards, and should be re-run during the transition window: mobile
builds that predate the feature keep creating items with a category and no room.

`down()` rebuilds `category_id` by joining on the room name, then **deletes** the items it cannot reclassify — an item
filed under « Chambre 2 » has no matching category and the column becomes `NOT NULL` again. That is the rollback's only
assumed data loss.

## [2.0.0] - 2026-08-11

### Added

- Multi-property support. Business resources (`Occupation`, `Work`, `InventoryItem`, `ShoppingItem`, `Note`) now belong
  to a `Property` and are partitioned server-side, by a Doctrine query extension on reads and a voter on writes.
- `Property` (`/api/properties`) and `PropertyMember` (`/api/property_members`) resources, with a local
  `manager` / `occupant` role per property.
- `GET /api/me` now returns the user's properties and local role in each, enough to seed a client property switcher in
  a single call.
- `?property=<IRI>` filter on every business collection.
- `bin/check-property-scope.sh` — HTTP scenarios covering the partitioning, in the absence of a test suite.
- `Property.accentColor` — a per-property accent colour, picked from a closed palette (`App\Enum\AccentColor`:
  `forest`, `lake`, `wood`, `slate`, `plum`, `lichen`). Exposed in `property:summary`, so `GET /api/me` is enough for a
  client to tint its UI on the active property. The read-only `accentHex` field carries the matching hexadecimal, so
  clients don't duplicate the palette.

### Changed

- The legacy property is renamed from « Les Marmottes » to « Les Tennis » (slug `les-tennis`). « Les Marmottes » is the
  name of the service, not of the Villard-de-Lans flat.

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

[unreleased]: https://github.com/marmottes-industries/villard-api/compare/v2.1.0...main
[2.1.0]: https://github.com/marmottes-industries/villard-api/compare/v2.0.0...v2.1.0
[2.0.0]: https://github.com/marmottes-industries/villard-api/compare/v1.3.0...v2.0.0
[1.3.0]: https://github.com/marmottes-industries/villard-api/compare/v1.2.1...v1.3.0
[1.2.1]: https://github.com/marmottes-industries/villard-api/compare/v1.2.0...v1.2.1
[1.2.0]: https://github.com/marmottes-industries/villard-api/compare/1.1.1...v1.2.0
[1.1.1]: https://github.com/marmottes-industries/villard-api/compare/1.1.0...1.1.1
[1.1.0]: https://github.com/marmottes-industries/villard-api/compare/1.0.0...1.1.0
[1.0.0]: https://github.com/marmottes-industries/villard-api/compare/1.0.0...main
