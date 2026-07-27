# Architecture Overview

## Implementation status

- **Identity**: implemented (Phase 0) — Fortify-backed authentication.
- **Queues**: implemented (Phase 1, `packages/Queues`) — submission,
  jurisdiction/restricted-category gating, moderation, PostGIS geospatial
  discovery, Inertia/React frontend. See
  `docs/releases/phase-1-completion-report.md`.
- **QueuePresence**: implemented (Phase 2, `packages/QueuePresence`) — GPS
  presence capture, evidence-photo capture with private storage and
  signed URLs, the v1 confidence-scoring engine (ADR-008), Inertia/React
  frontend. See `docs/releases/phase-2-completion-report.md`.
- **Audit**: partially implemented (Phase 2) — a minimal, platform-wide
  append-only sink driven by the shared `AuditableAction` interface, not
  the full Administration/Fraud-Risk-adjacent Audit context described
  below (case management, partitioning/archival, admin tooling remain
  unbuilt).
- **Auctions**: in progress (Phase 3, `packages/Auctions`) — the `Auction`
  aggregate's Open/Closing/Won/Expired/Cancelled state machine and
  immutable accepted-bid invariant (Sprint 1); the `AuctionRepository`
  persistence layer, including `findByIdForUpdate()`'s row-lock support
  for Bids (Sprint 2, extended Sprint 5); the ADR-009
  `SellerPresenceVerification` read contract and its `apps/web` adapter,
  plus the ADR-010 Evidence Verified gate in `AuctionService::open()`
  (Sprint 3); the ADR-011 `LiveProximityChecker` implementing the
  two-tier proximity policy (Sprint 4), now actively invoked by Bids' bid
  placement (Sprint 5) — its first real caller. No HTTP, no
  closing/winner-selection trigger, no anti-sniping yet.
- **Bids**: in progress (Phase 3, `packages/Bids`, Sprint 5) — an
  immutable, append-only `Bid` aggregate; `BidService::place()`
  orchestrating concurrency-safe placement inside one transaction
  (auction-row lock → `LiveProximityChecker` → highest-bid lookup →
  validation → insertion, ADR-012); `BidPlacementOutcome` keeping expected
  rejections from rolling back a legitimate proximity transition;
  after-commit event publication. No dependency on Auctions' internals —
  only through `AuctionGateway`, implemented in `apps/web`. No HTTP, no
  closing/winner-selection integration yet.
- All other modules below: not started.

## Style

Modular monolith with event-driven communication between modules.

## Main modules

- Identity
- Localization
- Queues
- QueuePresence
- Verification
- Auctions
- Bids
- Payments
- Transfers
- Disputes
- Ratings
- Notifications
- Administration
- Audit
- Fraud & Risk

## Module boundary rules

- No module accesses another module's Eloquent models directly.
- Cross-module reads/writes go through the other module's application-service
  interface, or are reconstructed locally from published domain events.
- All cross-module side effects are expressed as domain events, dispatched
  through Laravel's event system and queued via Horizon where not required
  to be synchronous.
- Module boundaries are enforced by an automated architecture test from
  Phase 0 onward, so the option to extract a module into a separate service
  later (per `decisions/001-modular-monolith.md`) remains real.

## Shared kernel

Value objects shared across modules, kept dependency-free of any single
module: `Money`/`Currency`, `GeoPoint`/`Geofence`, `TranslatableText`,
`Locale`.

## Infrastructure

- Laravel 12
- PHP 8.4
- React + Inertia
- Tailwind CSS
- PostgreSQL (with PostGIS for geospatial queue discovery)
- Redis (separate logical use for cache, session, and queue)
- Laravel Reverb
- Laravel Horizon
- Stripe Connect
- S3-compatible storage
- Docker

## Full analysis

See `docs/product/claude-mvp-analysis.md` for the complete bounded-context
breakdown, database model, state machines, and phase plan.
