# ADR 007: Geospatial Discovery Engine with PostGIS

## Status

Accepted — 2026-07-24.

## Context

RowBuddy Phase 1 (Catalog) already supports queue submission, legal
gating, moderation, approval, and publication (Sprints 1–5). Sprint 6
adds geospatial discovery for published queues — the last piece of the
Phase 1 exit criteria ("a queue can be created, gated, and discovered by
location"). PostGIS was already anticipated for this from Phase 0
(`docs/architecture/architecture-overview.md`, `docs/roadmap.md`), so
this ADR is about *how*, not *whether*.

The discovery capability must remain decoupled from PostGIS in the
domain/application layers and must preserve the existing package-based
modular architecture under `packages/*` (ADR-001), the same way the
Queues module has kept Eloquent out of its domain since Sprint 2.

## Decision

### 1. Split Sprint 6

- **Sprint 6a** (this ADR): geospatial infrastructure only.
- **Sprint 6b** (later): discovery application service, HTTP endpoint,
  ranking, pagination, and frontend integration.

### 2. Sprint 6a scope

- Enable and verify PostGIS in the Docker/PostgreSQL environment.
- Add geospatial persistence for a queue's coverage area.
- Use SRID 4326, `MULTIPOLYGON` geometry.
- Add a GIST spatial index on that column.
- Add a domain-facing `QueueDiscoveryRepository` port.
- Add a `PostGISQueueDiscoveryRepository` infrastructure adapter.
- Add the minimal value objects needed for the port boundary
  (`CoverageArea`, `Polygon`, `LinearRing`).
- Add real PostgreSQL/PostGIS integration tests.
- No public HTTP endpoint, no Inertia pages, no ranking, no
  Elasticsearch or alternative repositories.

### 3. Architectural constraints (unchanged from prior sprints, restated)

- Domain and application code must not know PostGIS SQL functions —
  no `ST_Contains`, `ST_Intersects`, `ST_DWithin`, geometry types, or
  raw SQL outside `Infrastructure`.
- Eloquent models remain inside `Infrastructure`.
- Repositories must not return Eloquent models.
- Persistence adapts to the domain, never the reverse
  ([[feedback_persistence_adapts_to_domain]] — same rule already
  governing `EloquentQueueRepository` since Sprint 2).
- Maintain `packages/*` boundaries — everything below lives inside
  `packages/Queues`.
- Only `published` queues are discoverable.
- Controllers and HTTP concerns are out of scope for Sprint 6a.
- `CoverageArea` (and the `Polygon`/`LinearRing` types it's built from)
  is a pure domain value object: no PostGIS dependency, no Laravel
  dependency, no WKT/SRID/geometry-type awareness of its own.
  `Infrastructure` alone is responsible for translating it to and from
  `MULTIPOLYGON`/SRID 4326 — the value object never serializes itself
  into any spatial-database format.

### 4. Coverage representation — resolving the open points in this ADR's brief

**The `Queue` aggregate (Sprint 1) is not modified.** A `CoverageArea` is
not added as a field on `Queue`. Coupling the lifecycle aggregate to a
geospatial concept it has no business rules about would couple two
unrelated concerns (status lifecycle vs. discoverability geometry) for
no benefit — nothing in `Queue`'s own invariants (ADR-005's
pending/approved/published/rejected lifecycle) needs to know about
coverage shape. Instead, coverage is a **persistence-and-discovery-side
concern**, associated with a queue by ID, read and written exclusively
through the new `QueueDiscoveryRepository` — never through
`QueueRepository`. This mirrors the existing precedent of
`RestrictedCategoryRepository` / `JurisdictionRuleRepository`
(Sprint 3/3b): narrow, read-mostly repositories scoped to one concern,
not full aggregate CRUD.

**`QueueDiscoveryRepository` port** (domain-facing, in
`packages/Queues/src/Contracts/`) — named for business intent, not SQL
semantics; a method named after `ST_Contains` would leak the
infrastructure's query strategy into the port's vocabulary:

```php
interface QueueDiscoveryRepository
{
    public function defineCoverageArea(string $queueId, CoverageArea $coverageArea): void;

    /** @return list<Queue> */
    public function discoverByLocation(GeoPoint $point): array;
}
```

Both methods are needed for `CoverageArea` and "geospatial persistence"
to be genuinely exercised by production code (not just test fixtures):
`defineCoverageArea` is the write side (still pure persistence, not an
application-service use case — assigning who calls it, and when, is a
Sprint 6b concern), `discoverByLocation` is the read side proven by this
sprint's required tests — *how* it's satisfied (`ST_Contains`,
`ST_Intersects`, or something else entirely) is strictly an
`Infrastructure` implementation detail the port's name must never hint
at. It returns `Queue` aggregates (reusing the existing Sprint 1 type
via the same `fromPersistence` hydration `EloquentQueueRepository`
already uses), not a new discovery-specific DTO — introducing a
parallel read type isn't justified when the existing aggregate already
carries every field the acceptance tests need.

**Value objects** (`packages/Queues/src/ValueObjects/`), pure PHP, zero
PostGIS/WKT knowledge:

- `LinearRing`: a non-empty closed ring of `GeoPoint` (first point equals
  last; minimum 4 points). Validated in the constructor, mirroring
  `Geofence`'s validation style in shared-kernel.
- `Polygon`: one exterior `LinearRing` plus zero or more interior
  `LinearRing`s (holes) — matches the OGC `Polygon` definition exactly,
  not speculative: a polygon *is* an exterior ring plus optional holes.
- `CoverageArea`: one or more `Polygon`s (a `MULTIPOLYGON`).

These stay in `packages/Queues` (not promoted to `shared-kernel`) since
no other module has asked for polygon geometry yet — premature to widen
the shared kernel's surface for a single consumer.

**WKT conversion boundary**: converting a `CoverageArea` to/from
Well-Known Text (the string format PostGIS's `ST_GeomFromText`/
`ST_AsText` use) happens *only* inside
`Infrastructure/PostGIS/PostGISQueueDiscoveryRepository` (and a small
private mapper it owns). The value objects themselves expose only
public readonly data — they are never asked to serialize themselves in
a spatial-database format, keeping the "domain must not know PostGIS"
rule airtight even though WKT is a general OGC text format, not a
PostGIS-specific SQL function.

**Geometry vs. geography type**: `geometry(MultiPolygon, 4326)`, not
`geography`. Coverage areas are local/regional-scale polygons where
planar point-in-polygon tests (`ST_Contains`) are standard practice and
GIST-indexable; `geography` matters for accurate long-distance
calculations, which this sprint doesn't need. No third-party Laravel
spatial package is added — the adapter issues `ST_*` calls directly via
Eloquent's `whereRaw`/`DB::statement`, scoped to
`Infrastructure/PostGIS/`.

**Shared Eloquent↔domain mapping**: `EloquentQueueRepository`'s private
`toDomain()` (Sprint 2) is extracted to a shared
`Infrastructure/Eloquent/QueueModelMapper`, reused by
`PostGISQueueDiscoveryRepository` — otherwise the `QueueModel → Queue`
mapping would silently duplicate across two repositories, the same
duplication risk [[feedback_persistence_adapts_to_domain]] already
flagged once this sprint's sibling work (`QueueGateChecker`, Sprint 5)
was extracted to avoid.

### 5. Docker / PostgreSQL environment

`docker-compose.yml`'s `postgres` service currently uses plain
`postgres:16-alpine` (no PostGIS). It changes to `postgis/postgis:16-3.4`
(Debian-based — no official Alpine PostGIS image), same PostgreSQL major
version already in use. The existing `postgres-data` volume is **not**
recreated (it holds real dev data from Sprints 1–5), so PostGIS's
init-script auto-`CREATE EXTENSION` (which only runs against a freshly
initialized data directory) will not fire automatically for this
environment. A migration explicitly runs
`CREATE EXTENSION IF NOT EXISTS postgis;`, so both the existing dev
volume and any future fresh environment (CI, a new contributor) end up
correctly provisioned either way.

### 6. Query behavior required for Sprint 6a (acceptance criteria)

- A published queue whose coverage contains a given point is found.
- A queue whose coverage does not contain the point is not returned.
- A non-published queue (any other status) is never returned, even with
  matching coverage.
- The GIST index exists (verified via `pg_indexes`).
- All of the above validated against real PostgreSQL/PostGIS — not
  SQLite, which has no spatial support at all.

### 7. Testing strategy

Package tests split by what they need:

- **Framework-independent** (`packages/Queues/tests/ValueObjects/...`):
  `LinearRing`/`Polygon`/`CoverageArea` construction and validation. Run
  on host PHP, no database, matching every prior sprint's pure-domain
  test style.
- **Real PostgreSQL/PostGIS integration**
  (`packages/Queues/tests/Infrastructure/PostGIS/...`): connects via
  Eloquent's Capsule manager (same pattern as the existing sqlite-backed
  repository tests) to a real Postgres/PostGIS connection, reading
  connection details from env vars with defaults matching
  `docker-compose`'s `postgres` service (so the same test file runs
  correctly from inside the app container, where `DB_HOST=postgres` is
  already a real container env var, or from the host via the published
  `5432` port). Each test wraps its work in a transaction that is always
  rolled back, so it never leaves data behind in whichever database it
  connects to. If no PostgreSQL/PostGIS connection is reachable (e.g.
  running `vendor/bin/pest` on a machine with no Docker and no local
  Postgres), the test skips itself rather than failing — this keeps
  `packages/Queues`' standalone test run usable without Docker for
  everything except this one PostGIS-specific suite.
- `apps/web`'s full suite, Larastan, and Pint are re-run after these
  changes (image swap, new migrations, new package code) to confirm
  nothing broke — including a manual check that `/login` and `/register`
  still respond without a 500 after the Postgres image change.

### 8. Delivery

1. This ADR, as its own documentation commit.
2. Sprint 6a implementation, as its own code commit — only once every
   test above passes.
3. Report: changed files, migrations, spatial index verification, test
   results, Docker/PostGIS verification, risks/open questions.
4. Stop for review before Sprint 6b.

## Consequences

- `packages/Queues` gains a `Discovery`-flavored surface
  (`QueueDiscoveryRepository`, `PostGISQueueDiscoveryRepository`,
  `CoverageArea`/`Polygon`/`LinearRing`) alongside the existing
  lifecycle/gating/moderation surface, without touching `Queue` itself.
- The dev Postgres image change is a one-time, deliberate infrastructure
  migration step, not a recurring concern — future environments get
  PostGIS automatically via the new base image.
- Sprint 6b inherits a read/write geospatial port ready to be wired into
  an application service, an HTTP endpoint, and eventually ranking —
  none of which exist yet by design.
- Assigning a coverage area to a real queue (e.g., during submission, or
  via an admin action) has no caller yet after this sprint; that wiring
  is explicitly Sprint 6b's job, not invented speculatively here.
