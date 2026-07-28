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
- **Auctions**: implemented, domain/backend scope only (Phase 3,
  `packages/Auctions`) — the `Auction` aggregate's Open/Closing/Won/
  Expired/Cancelled state machine, an explicit required `closesAt`, and
  the immutable accepted-bid invariant (Sprint 1, extended Sprint 6); the
  `AuctionRepository` persistence layer, including `findByIdForUpdate()`'s
  row-lock support for Bids (Sprint 2, extended Sprint 5); the ADR-009
  `SellerPresenceVerification` read contract and its `apps/web` adapter,
  plus the ADR-010 Evidence Verified gate in `AuctionService::open()`
  (Sprint 3); the ADR-011 `LiveProximityChecker` implementing the two-tier
  proximity policy (Sprint 4), actively invoked by Bids' bid placement
  (Sprint 5); the ADR-013 `AuctionClosingEvaluator`/`SoftCloseExtender`/
  `WinningBidLookup` implementing closing, winner selection, expiry, and
  anti-sniping under the same lock (Sprint 6). The full backend/domain
  exit criteria from `docs/roadmap.md` are met and formally accepted,
  tagged `v0.4.0-auctions`. No HTTP, no frontend, no Reverb — deliberately
  deferred to a later delivery-layer phase, not built yet. See
  `docs/releases/phase-3-completion-report.md`.
- **Bids**: implemented, domain/backend scope only (Phase 3,
  `packages/Bids`) — an immutable, append-only `Bid` aggregate;
  `BidService::place()` orchestrating concurrency-safe placement inside
  one transaction (auction-row lock → `LiveProximityChecker` → closing
  evaluation → highest-bid lookup → validation → insertion → soft-close
  effects, ADR-012/ADR-013); `BidPlacementOutcome` keeping expected
  rejections from rolling back a legitimate proximity or closing
  transition; after-commit event publication. No dependency on Auctions'
  internals — only through `AuctionGateway`, implemented in `apps/web`.
  No HTTP, no frontend — deferred alongside Auctions', see
  `docs/releases/phase-3-completion-report.md`.
- **Payments**: implemented, domain/backend scope only (Phase 4,
  `packages/Payments`) — the `PaymentIntent` aggregate modeling
  authorization only (`Authorized`/`Failed`; no `Captured`/`Held`/
  `ReleasedToSeller`/`RefundedToBuyer` exist in code, per ADR-015);
  `SellerPayoutAccount` and a `ConnectAccountGateway` Stripe adapter for
  Connect Express onboarding, with eligibility always read live from
  Stripe, never cached (Sprint 3); `AuctionWinAuthorizationService`, the
  real `AuctionWon` consumer, authorizing the buyer's total (bid + fee)
  via the separate-charges-and-transfers model with no dependency on
  seller Connect status, assuming an already-obtained Stripe PaymentMethod
  id (ADR-016, Sprint 4); `WebhookSignatureVerifier` and
  `StripeWebhookProcessor` — real local HMAC-SHA256 verification and a
  `ProcessedWebhookEvent` idempotency ledger keyed by Stripe event id
  (Sprint 5); `PayoutPreparationService`, combining authorization,
  account-linkage, and live eligibility into a read-only readiness
  snapshot plus an expected-settlement estimate — no payout ever executed
  (Sprint 6). Payments tracks its own lifecycle keyed by `auctionId`/
  `winningBidId` (ADR-014) — `Auction` gained no new states. Capture,
  transfer confirmation, payout execution, and re-authorization before
  expiry are deferred to Phase 5 (Transfers), which does not exist yet.
  One real HTTP endpoint exists (`POST /webhooks/stripe`, Sprint 5) —
  otherwise no HTTP, no frontend. See
  `docs/releases/phase-4-completion-report.md`.
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
