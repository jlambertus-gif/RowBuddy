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

Status: **done**. Tagged `v0.4.0-auctions`. Domain/backend scope formally
accepted 2026-10-01. Authorized 2026-09-02 following the Phase 2 closure
and a dedicated architecture review (ADR-009, ADR-010).

**Closure scope note**: this phase's exit criteria (below) were defined
and met at the domain/backend level. Unlike Phases 1 and 2, HTTP
controllers, the frontend, Reverb, and a manual browser acceptance test
were explicitly deferred to a later delivery-layer phase by deliberate
decision, not oversight — the Phase 3 objective was the Auctions/Bids
*domain*, not its delivery layer. See
`docs/releases/phase-3-completion-report.md` for the full rationale and
final report.

Auction state machine (§7.1 of the MVP analysis), Reverb-backed real-time
bidding, concurrency-safe bid placement, anti-sniping soft-close.

Before implementation began, two architectural decisions were resolved:

- **ADR-009 — Auctions–Presence Verification Contract**: a synchronous
  read port (`SellerPresenceVerification`), owned by Auctions and
  implemented by an `apps/web` adapter, lets Auctions check a seller's
  presence/confidence state without depending on QueuePresence internals.
  One `PresenceSession` may back at most one auction — documented
  explicitly as an MVP restriction, not a permanent domain invariant,
  pending a future `Position` concept.
- **ADR-010 — Minimum Confidence Tier and Live-Presence Policy**: Evidence
  Verified is the minimum tier required to create/publish an auction
  (Location Verified is reachable via GPS alone per ADR-008, so it cannot
  satisfy "GPS alone is not sufficient verification"). Live proximity
  while an auction is active is a separate, continuous, Auctions-owned
  check. The staleness threshold, the consequence of losing proximity,
  and the consequence of the seller ending their `PresenceSession` while
  an auction is active are explicitly deferred open questions, due before
  Sprint 4.
- **ADR-011 — Live-Proximity Enforcement Policy**: resolved ADR-010's
  three deferred questions — a 15-minute staleness threshold, a two-tier
  consequence (flag "at risk" then a 10-minute grace period before
  auto-cancelling; immediate cancellation when the backing
  `PresenceSession` is no longer active, which is also how "seller ends
  their session mid-auction" resolves, via the already-exposed
  `sessionActive` field — no new QueuePresence code). Enforcement runs
  lazily, invoked only by commands that act on an existing active
  auction, never by a plain read; deliberately no scheduler or background
  infrastructure this phase.
- **ADR-012 — Bids–Auctions Gateway Contract and Concurrency Strategy**:
  a synchronous `AuctionGateway` port (owned by Bids, mirroring ADR-009's
  shape) locks the auction row as the sole serialization anchor for bid
  placement — one transaction, one connection, spanning the lock,
  `LiveProximityChecker`, the highest-bid lookup, validation, and
  insertion. Expected business rejections (auction not open, wrong
  currency, seller bidding on their own auction, bid too low) are
  returned as data (`BidPlacementOutcome`), never thrown inside the
  transaction, so a legitimate proximity transition stays committed
  regardless of whether the triggering bid is accepted. Events are
  collected and published only after commit; no outbox introduced.
- **ADR-013 — Auction Closing, Winner Selection, and Anti-Sniping Policy**:
  `closesAt` is an explicit, required value the aggregate validates
  against `openedAt` but never derives itself — sourced via a swappable
  `AuctionDurationPolicy` (30-minute provisional MVP default). Closing is
  evaluated lazily under the same auction-row lock bid placement already
  established, via `AuctionClosingEvaluator` and a new Auctions-owned
  `WinningBidLookup` port (deterministic ordering: amount DESC,
  placed_at ASC, id ASC). Anti-sniping (2-minute window, 2-minute
  extension, calculated from the current `closesAt`, never from the bid's
  own timestamp) is applied by `SoftCloseExtender`, reachable only after
  a bid has already been accepted and recorded — never from a rejected
  attempt. No cap on total extensions; no scheduler.

Sprint progress in `packages/Auctions` and `packages/Bids`:

- **Sprint 1** (done): `Auction` aggregate scaffold — Open/Closing/Won/
  Expired state machine, immutable accepted-winning-bid invariant,
  domain events (`AuctionOpened`, `AuctionClosingStarted`, `AuctionWon`,
  `AuctionExpired`). No persistence, no HTTP, no QueuePresence coupling.
  11 tests, PHPStan level 8 and Pint clean.
- **Sprint 2** (done): persistence layer — `AuctionRepository` port,
  `EloquentAuctionRepository` adapter, `auctions` migration with a full
  (not partial) unique constraint on `presence_session_id` enforcing
  ADR-009 §4's MVP restriction, translated to a domain-level
  `PresenceSessionAlreadyConsumed` exception. 18 tests, PHPStan and Pint
  clean.
- **Sprint 3** (done): implemented ADR-009 and ADR-010 — the
  `SellerPresenceVerification` port and `PresenceVerificationSnapshot`
  DTO (both Auctions-owned), the `apps/web` bridging adapter
  (`QueuePresenceSellerVerification`), and the Evidence Verified gate in
  `AuctionService::open()`, fail-closed throughout. Required two minimal,
  purely additive read methods on QueuePresence
  (`findLatestBySellerAndQueue`, `latestWithinGeofencePingAt`) to expose
  already-persisted signals cross-module — no QueuePresence business
  rule, persistence semantics, or public API changed incompatibly; all
  prior Phase 2 tests pass unmodified. No live-proximity enforcement, no
  HTTP, no bidding — deferred to Sprint 4. 24 Auctions tests, 81
  QueuePresence tests (was 73), 72 apps/web tests (was 66); PHPStan and
  Pint clean throughout.
- **Sprint 4** (done): implemented ADR-011 — a new `Cancelled` status,
  `proximityAtRiskSince` tracking, and `LiveProximityChecker` applying
  the two-tier policy. Invoked only by commands that act on an existing
  active auction, never by a read; no scheduler introduced. Built
  complete and fully tested with no caller yet — Sprint 5's bid-placement
  command is expected to be the first one. 45 Auctions tests (was 24);
  apps/web unchanged at 72; PHPStan and Pint clean throughout.
- **Sprint 5** (done): implemented ADR-012 — a new `packages/Bids`
  bounded context: an immutable, append-only `Bid` aggregate, a
  record-only `BidRepository`, and `BidService::place()` orchestrating
  placement inside one transaction (auction-row lock →
  `LiveProximityChecker` → highest-bid lookup → validation → insertion),
  with `BidPlacementOutcome` keeping expected rejections from rolling
  back a legitimate proximity transition. `AuctionGateway`'s real
  implementation in `apps/web` is `LiveProximityChecker`'s first real
  caller, per ADR-011 §5. A real-Postgres test (two independent PDO
  connections, bounded `lock_timeout`) proves the row lock prevents a
  bidder from validating against a stale highest-bid reading. No HTTP,
  no anti-sniping, no closing/winner-selection trigger yet. 19 Bids
  tests, 47 Auctions tests (was 45), 31 shared-kernel tests (was 29), 77
  apps/web tests (was 72); PHPStan and Pint clean throughout.
- **Sprint 6** (done): implemented ADR-013 — `closesAt` persisted on
  `Auction`, `AuctionDurationPolicy`/`AntiSnipingPolicy`,
  `AuctionClosingEvaluator` (closing → winner selection or expiry, both
  transitions persisted together), `SoftCloseExtender`, and the
  Auctions-owned `WinningBidLookup` bridging to Bids' deterministically
  ordered highest-bid query. `AuctionGateway` gained
  `applyAcceptedBidEffects()`; `AuctionLockResult::proximityEvents`
  renamed to `events` since it now carries closing/extension events too.
  A real-Postgres test proves a concurrent bidder observes the committed,
  extended deadline only after the lock is released. Still no HTTP, no
  frontend, no Reverb. 66 Auctions tests (was 47), 24 Bids tests (was
  19), 80 apps/web tests (was 77); PHPStan and Pint clean throughout.

Exit criteria: an auction can run end-to-end (open → closing → winning bid
selected) under simulated concurrent bidding with correct, tested
row-locking behavior — **met**, at the backend/domain level, and formally
accepted as this phase's closure bar. No payments yet. HTTP, frontend,
Reverb, and a manual browser acceptance test are explicitly out of scope
for this phase's closure and deferred to a later delivery-layer phase.
See `docs/releases/phase-3-completion-report.md` for the full report and
`docs/releases/phase-3-completion-review.md` for the pre-closure review
that recommended it.

## Phase 4 — Payments

Status: **done**. Tagged `v0.5.0-payments`. Domain/backend scope formally
accepted 2026-10-22.

**Closure scope note**: launch-market, Stripe account type, fee
percentage, KYC tier, currency, and transaction-limit product decisions
were resolved first (United States, USD-only, Stripe Connect Express,
10% buyer-side fee, Stripe's own onboarding gate as the sole KYC
requirement, $500 transaction cap). Two architectural boundaries were
then fixed before any code: ADR-014 (Payments tracks its own lifecycle
keyed by `auctionId`/`winningBidId`; `AuctionStatus` is never extended)
and ADR-015 (Phase 4 stops at authorization, webhook handling, and payout
preparation — capture and payout execution are Phase 5/Transfers'
responsibility, with no placeholder trigger introduced to simulate it).
ADR-016 further scoped Sprint 4: authorization assumes a buyer Stripe
PaymentMethod id was already obtained by a separate, not-yet-built
capability, and uses the separate-charges-and-transfers model so
authorization never depends on seller Connect onboarding status. See
`docs/releases/phase-4-completion-report.md` for the full report.

Stripe Connect Express seller onboarding, buyer payment authorization on
auction win (ADR-004/ADR-006/ADR-016), webhook signature verification and
an idempotency ledger, and payout preparation (readiness validation plus
an expected-settlement estimate) — implemented across 6 sprints in
`packages/Payments`. Re-authorization before expiry and capture/payout
execution are deferred to Phase 5, for the same reason ADR-015 already
gives for capture: both depend on Transfers' not-yet-designed handoff
timing.

Exit criteria: a winning bid's buyer-side total (bid + platform fee) can
be authorized in Stripe test mode, with idempotent webhook handling
proven under replay, and a seller's payout can be fully *prepared*
(linked Connect account, live eligibility, expected settlement) —
**met**, at the backend/domain level. Capture, transfer confirmation, and
actual payout execution are explicitly **not** part of this phase's exit
criteria — they require Phase 5 (Transfers) to exist first, a narrower
bar than this phase's original roadmap description assumed, the same
kind of scope narrowing Phase 3's closure documented for HTTP/frontend.

## Phase 5 — Transfers

Status: **done**. Tagged `v0.6.0-transfers`. Domain/backend scope formally
accepted 2026-07-29.

**Closure scope note**: four ADRs (017–020) resolved every open
architectural question before Sprint 1 — the `Transfer` aggregate's
two-sided confirmation model and QR issuance/validation direction
(ADR-017); the transfer window, hybrid lazy-plus-scheduled expiry
evaluation, and provisional no-fault cancellation policy (ADR-018,
revised once to add an "Alternatives considered" analysis before a
scheduler was introduced); the Transfers-to-Payments capture contract and
`PaymentIntent` lifecycle extension (ADR-019); and the confirmation-time
geofence cross-check plus a first-class, extensible evidence model
(ADR-020, revised once so evidence is a real aggregate concept from
Sprint 1, not an app-layer-only afterthought). A mid-phase architectural
correction moved the Stripe capture trigger off `TransferConfirmationService`
and into a dedicated `TransferCaptureTriggerService`, after a requested
verification found it was reading `Transfer`'s post-mutation status
directly rather than reacting to the committed `TransferConfirmed` event.
See `docs/releases/phase-5-completion-report.md` for the full report.

QR issuance/validation, handoff confirmation with geolocation cross-check,
delivered across 6 sprints plus that one correction in
`packages/Transfers`:

- `Transfer` aggregate: `Issued → Confirmed/Expired/Cancelled`, all
  terminal; two-sided confirmation (only the second party's confirmation
  reaches `Confirmed`); first-class, repeatable photo evidence
  (`attachEvidence()`), orthogonal to the confirmation state machine and
  present from Sprint 1 in anticipation of Phase 6.
- `TransferRepository` persistence, with a unique constraint on
  `auction_id` (one transfer per auction) and a dedicated append-only
  `transfer_evidence` table.
- `PaymentIntent`'s lifecycle extended (Payments) from append-only to
  mutable, gaining `Captured`/`CaptureFailed`/`Cancelled` and the exact
  locked capture/cancel sequence ADR-019 §6 defines.
- `TransferInitiationService` (the real `PaymentAuthorized` consumer),
  `TransferConfirmationService` (validation/mutation/persistence/
  publication only), `TransferCaptureTriggerService` and
  `TransferCancelTriggerService` (the real `TransferConfirmed`/
  `TransferExpired`/`TransferCancelled` consumers) — every cross-module
  reaction verified to be driven by a committed domain event, never by a
  caller reading an aggregate's own status.
- `TransferExpiryEvaluator`, invoked both lazily (at confirmation time)
  and via this codebase's first Horizon-scheduled job
  (`EvaluateTransferExpiry`) — justified in ADR-018 against three simpler
  alternatives before being introduced. Two real-PostgreSQL concurrency
  tests prove the row lock serializes a scheduled sweep tick against a
  concurrent confirmation attempt, and against another overlapping sweep
  tick.
- `StripeCancellationReconciliationService`: a secondary, defensive
  reconciliation of `payment_intent.canceled` webhooks, reusing the same
  `cancelAuthorization()` transition the scheduler already uses.
- `TransferEvidenceStorage`/`ImageMetadataStripper` (Transfers' own
  copies of QueuePresence's Phase 2 evidence-handling ports) and
  `TransferEvidenceSubmissionService`, the first real caller of
  `attachEvidence()`.
- 505 automated tests total (58 new in `packages/Transfers`, 77 in
  `packages/Payments`, was 55 at Phase 4 close), Larastan and Pint clean.

Exit criteria: a won, payment-authorized auction can have its position
handed off — QR issued, both parties confirm with an independent geofence
check, capture triggers on the second confirmation, and an unconfirmed
transfer expires and cancels the authorization with no charge — **met**,
at the backend/domain level. No payout execution, no buyer payment-method
acquisition, no proactive re-authorization, and no real event-listener
wiring exist yet — deferred for documented reasons, the same kind of
scope narrowing Phases 3 and 4's closures already established. See
`docs/releases/phase-5-completion-report.md` for the full report.

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
