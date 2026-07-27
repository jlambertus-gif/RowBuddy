# Development Roadmap

Status legend: `not started` / `proposed` / `in progress` / `done`.

## Phase 0 — Foundations

Status: **done**.

- Laravel 12 installed at repository root (PHP 8.4 runtime via Docker).
- Docker Compose: app (PHP-FPM), nginx, postgres, redis, reverb, horizon,
  node (Vite dev).
- PostgreSQL and Redis configured (cache, session, and queue connections
  logically separated).
- React + Inertia + Tailwind CSS wired up.
- Pest, Laravel Pint, PHPStan/Larastan configured.
- GitHub Actions CI: lint, static analysis, tests, frontend build,
  translation-parity check, module-boundary architecture test.
- EditorConfig and fully populated `.env.example`.
- English/Spanish localization scaffolding (backend `lang/`, frontend
  i18next), fallback locale `en`.
- Modular monolith folder skeleton (`app/Modules/*`), no models, no
  business logic, no migrations.

Exit criteria: repository boots locally via Docker, CI pipeline is green on
an empty scaffold, module-boundary and translation-parity tests pass with
zero modules implemented.

## Phase 1 — Catalog

Status: **done**. Tagged `v0.2.0-catalog`. Manual browser acceptance test
passed and formally accepted 2026-07-27.

Queues bounded context, delivered across Sprints 1–7 (Sprint 6 split into
6a/6b) in `packages/Queues`:

- `Queue` aggregate with `pending/approved/published/rejected` status
  lifecycle (ADR-005), immutable transitions, domain events for every
  transition.
- Eloquent persistence adapter behind a `QueueRepository` port —
  domain/application layers have no Eloquent dependency.
- Jurisdiction/restricted-category gating engine: versioned,
  date-effective jurisdiction rules; fail-closed when no rule exists for
  a country.
- Queue submission (user-submitted, gated) and direct publish
  (admin-curated, still gated) application services and HTTP layer.
- Admin moderation queue: list pending, approve, reject (with reason),
  publish.
- Geospatial discovery (ADR-007): PostGIS-backed coverage areas
  (`MULTIPOLYGON`, SRID 4326, GIST index), automatic coverage-area
  assignment on publish, distance-ranked in-memory-paginated search.
- Inertia/React frontend: submission form, discovery search, admin
  moderation UI, dashboard navigation — all localized (en/es).
- 113 automated tests (32 `apps/web`, 81 `packages/Queues`), Larastan and
  Pint clean, module-boundary and translation-parity architecture tests
  passing.

Exit criteria met: a queue can be created (admin-published or
user-submitted), gated by jurisdiction/restricted-category rules, and
discovered by location. No auctions, no money yet.

## Phase 2 — Presence & Trust

Status: **done**. Tagged `v0.3.0-presence`. Manual browser acceptance test
passed and formally accepted 2026-08-31.

QueuePresence bounded context, delivered across Sprints 1–7 in
`packages/QueuePresence`:

- `PresenceSession` aggregate: Active/Ended lifecycle, GPS ping and
  evidence-photo signal recording guarded to the Active state, its own
  elapsed-duration calculation (frozen once ended).
- Eloquent persistence adapters behind domain-facing repository
  ports — domain/application layers have no Eloquent dependency, mirroring
  Queues' pattern.
- `QueueGeofenceLookup`: a port owned by QueuePresence (not Queues),
  bridged by a composition-root adapter in `apps/web` — the one place
  allowed to depend on both packages' internals.
- v1 confidence-scoring engine (ADR-008): a pure, dependency-free
  `ConfidenceScorer` over the four approved signals (GPS within-geofence +
  best accuracy, presence duration, evidence photo), wired to real
  persisted signal history via `ConfidenceRecomputer`.
- Evidence capture: private local-disk storage behind a swappable
  `EvidenceStorage` port, GD-based metadata (EXIF) stripping, signed
  temporary URLs.
- Append-only confidence-score history (never mutated in place) and a
  minimal, platform-wide audit sink (`audit_events`) driven by the shared
  `AuditableAction` interface — audits any module's event generically,
  proven against an existing Queues event with zero Queues-side changes.
- Inertia/React frontend: presence-session UI (start, record GPS, upload
  evidence, end session) with the confidence tier/score shown after every
  action; a "Claim presence" entry point from Discover — all localized
  (en/es).
- 220 automated tests (73 `packages/QueuePresence`, 81 `packages/Queues`,
  66 `apps/web`), Larastan and Pint clean.

Exit criteria met: a seller can register presence at a queue and receive a
computed confidence score/tier, with signals independently recorded and
auditable.

## Phase 3 — Auctions & Bids

Status: not started.

Auction state machine (§7.1 of the MVP analysis), Reverb-backed real-time
bidding, concurrency-safe bid placement, anti-sniping soft-close.

Exit criteria: an auction can run end-to-end (open → closing → winning bid
selected) under simulated concurrent bidding with correct, tested
row-locking behavior. No payments yet.

## Phase 4 — Payments

Status: not started. Blocked until remaining open questions in
`docs/product/claude-mvp-analysis.md` §10.2 (exact fee %, launch market,
KYC tier, protection-period duration) are answered.

Stripe Connect seller onboarding, authorize-now/capture-at-transfer escrow
(ADR-004), platform fee (ADR-006), webhook idempotency ledger,
re-authorization flow for transfer windows nearing expiry.

Exit criteria: a winning bid can be authorized, captured on transfer
confirmation, and paid out to a verified seller, entirely in Stripe test
mode, with idempotent webhook handling proven under replay.

## Phase 5 — Transfers

Status: not started.

QR issuance/validation, handoff confirmation with geolocation cross-check.

## Phase 6 — Disputes & Refunds

Status: not started.

Case management, evidence aggregation (read-only across contexts), refund
orchestration delegated to Payments.

## Phase 7 — Ratings & Notifications

Status: not started.

Cross-channel notification templates (locale-aware), ratings.

## Phase 8 — Administration & Fraud/Risk v1

Status: not started.

Manual KYC/dispute overrides, rules-based risk scoring (Fraud & Risk
context), restricted-category admin UI.

## Phase 9 — Hardening & Launch Readiness

Status: not started.

Load-testing Reverb/bidding under concurrency, security review,
per-jurisdiction legal sign-off for each launch market
(`docs/legal/jurisdiction-requirements.md`), observability, Horizon
capacity tuning.

---

Every phase ships with: automated tests for that phase's domain rules,
translation-key parity checks, and domain-event contract tests — not
retrofitted at the end.
