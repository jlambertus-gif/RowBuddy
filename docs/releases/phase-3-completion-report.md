# Phase 3 (Auctions & Bids) — Completion Report

Tag: `v0.4.0-auctions`
Status: **Complete and formally accepted**, 2026-10-01 — domain/backend
scope only. See §9 for the explicit closure-scope decision.

## 1. Executive summary

Phase 3 delivers the Auctions and Bids bounded contexts end-to-end at the
domain/backend level: a verified seller's queue presence gates auction
creation; an active auction enforces live physical proximity; bids are
placed under real concurrency control with an immutable accepted-bid
invariant; and an auction closes lazily into a selected winner or expiry,
with anti-sniping soft-close extension — all proven, not merely asserted,
under real-Postgres row-lock concurrency tests exercising two independent
database connections. This satisfies the Phase 3 exit criteria defined in
`docs/roadmap.md`.

The phase was delivered as 6 sprints, each scoped, implemented, tested,
and reviewed independently, with five new ADRs (009–013) resolving every
architectural judgment call — the Auctions–Presence contract, the minimum
verification tier, live-proximity policy, the Bids–Auctions concurrency
contract, and closing/anti-sniping — before the sprint that needed it,
the same discipline Phases 1 and 2 established. 363 automated tests pass
across all six affected packages/apps, with clean PHPStan/Larastan and
Pint throughout.

**This phase closes without HTTP, frontend, Reverb, or a manual browser
acceptance test** — a deliberate scope decision, not an oversight or a
lowered bar applied without review. See §9 for the full rationale.

## 2. Sprint-by-sprint deliverables

| Sprint | Commit | Delivered |
|---|---|---|
| 1 | `31dfce1` | `Auction` aggregate scaffold: Open/Closing/Won/Expired state machine, immutable accepted-winning-bid invariant, domain events. No persistence, no HTTP, no QueuePresence coupling. |
| 2 | `faa0b9d` | Persistence layer: `AuctionRepository` port, `EloquentAuctionRepository` adapter, `auctions` migration with a full unique constraint on `presence_session_id` (ADR-009 §4's MVP restriction), translated to `PresenceSessionAlreadyConsumed`. |
| 3 | `0a17351` | ADR-009 (`SellerPresenceVerification` port, `apps/web` adapter) and ADR-010 (Evidence Verified gate in `AuctionService::open()`, fail-closed). Two minimal, purely additive read methods added to QueuePresence — no existing behavior, persistence semantics, or public API changed incompatibly. |
| 4 | `1936935` | ADR-011: a new `Cancelled` status, `proximityAtRiskSince`, and `LiveProximityChecker` (15-minute staleness → at-risk → 10-minute grace → cancel; immediate cancel on `sessionActive: false`). Built complete with no caller yet, by design. |
| 5 | `c64fbae` | ADR-012: new `packages/Bids` bounded context — immutable append-only `Bid`, record-only `BidRepository`, `BidService::place()` orchestrating one transaction spanning the auction-row lock, `LiveProximityChecker`'s first real caller, highest-bid lookup, validation, and insertion. `BidPlacementOutcome` keeps expected rejections from rolling back a legitimate proximity transition. Real-Postgres concurrency test. |
| 6 | `c94fe8a` | ADR-013: explicit required `closesAt` sourced via swappable `AuctionDurationPolicy`, `AuctionClosingEvaluator` (closing → winner selection or expiry under the same lock), `SoftCloseExtender` (anti-sniping, reachable only after an accepted bid), Auctions-owned `WinningBidLookup` with deterministic ordering. Real-Postgres test proving a concurrent bidder observes the committed, extended deadline only after the lock releases. |

## 3. Features delivered

- Presence-gated auction creation: a seller must hold Evidence Verified
  confidence tier to open an auction (ADR-010), and each `PresenceSession`
  backs at most one auction, an explicit MVP restriction (ADR-009 §4).
- Continuous live-proximity enforcement while an auction is open: staleness
  detection, an at-risk flag with a grace period, and automatic
  cancellation — including immediate cancellation if the seller ends their
  presence session mid-auction (ADR-011).
- Concurrency-safe bid placement: one auction-row lock serializes every
  bid attempt; an immutable, append-only `Bid` aggregate; expected
  rejections (auction not open, wrong currency, seller bidding on their
  own auction, bid too low) never roll back a legitimate proximity or
  closing transition that occurred earlier in the same attempt (ADR-012).
- Lazy auction closing: winner selection by deterministic highest-bid
  ordering, or expiry when no bids exist, evaluated under the same lock
  bid placement already established (ADR-013).
- Anti-sniping soft-close: a bid accepted within the closing window
  extends the deadline from its *current* value, reachable only after an
  already-accepted bid, with no path from a rejected attempt (ADR-013).
- Full domain-event coverage for every transition (opened, proximity
  at-risk/cancelled, closing started, won, expired, deadline extended),
  collected and published only after each transaction commits.

## 4. ADRs created

- **ADR-009 — Auctions–Presence Verification Contract**: a synchronous
  `SellerPresenceVerification` port, owned by Auctions, implemented by an
  `apps/web` adapter. One `PresenceSession` backs at most one auction,
  explicitly an MVP restriction pending a future `Position` concept.
- **ADR-010 — Minimum Confidence Tier and Live-Presence Policy**: Evidence
  Verified required to open an auction; live proximity while active is a
  separate, continuous concern, deferred to ADR-011.
- **ADR-011 — Live-Proximity Enforcement Policy**: 15-minute staleness,
  two-tier consequence (at-risk → 10-minute grace → cancel, or immediate
  cancel on `sessionActive: false`), lazy enforcement, no scheduler.
- **ADR-012 — Bids–Auctions Gateway Contract and Concurrency Strategy**:
  `AuctionGateway` port owned by Bids; the auction-row lock as sole
  serialization anchor; `BidPlacementOutcome` separating expected
  rejection from unexpected failure; after-commit event publication;
  real-Postgres proof of the race being prevented.
- **ADR-013 — Auction Closing, Winner Selection, and Anti-Sniping Policy**:
  explicit `closesAt` sourced by policy, not hard-coded;
  `AuctionDurationPolicy` (30-minute provisional default) and
  `AntiSnipingPolicy` (2-minute window/extension); `WinningBidLookup` with
  deterministic tiebreak ordering; the exact locked sequence guaranteeing
  a late bid is never accepted.

ADRs 001–008 were pre-existing and remain unchanged and binding.

## 5. Architecture changes

- Two new packages, `packages/Auctions` and `packages/Bids`, following the
  exact package shape established in Phases 1–2.
- Two new cross-module read ports, symmetric in direction:
  `SellerPresenceVerification` (Auctions → QueuePresence) and
  `WinningBidLookup` (Auctions → Bids); plus `AuctionGateway` (Bids →
  Auctions). All follow the established "consumer owns the port,
  `apps/web` implements the adapter" pattern from `QueueGeofenceLookup`
  (Phase 2).
- A new concurrency pattern for this codebase: a single database
  transaction and row lock spanning multiple collaborators
  (`LiveProximityChecker`, `AuctionClosingEvaluator`, `SoftCloseExtender`,
  `BidRepository`), with expected business outcomes returned as data
  (`BidPlacementOutcome`) rather than exceptions, so a legitimate
  side-effect transition is never rolled back by an unrelated, ordinary
  rejection.
- After-commit event publication via a collecting `DomainEventPublisher`
  constructed per-call in the `apps/web` adapter — no outbox table
  introduced.
- Two minimal, purely additive extensions to the already-closed Phase 2
  `packages/QueuePresence` (Sprint 3) — no existing behavior, persistence
  semantics, or public API changed incompatibly; all prior Phase 2 tests
  pass unmodified.

## 6. Database changes

`packages/Auctions/database/migrations/`:
- `create_auctions_table` (Sprint 2)
- `add_proximity_at_risk_since_to_auctions_table` (Sprint 4)
- `add_closes_at_to_auctions_table` (Sprint 6)

`packages/Bids/database/migrations/`:
- `create_bids_table` (Sprint 5) — append-only, no update/delete path
  anywhere in the codebase for this table.

No changes to any Phase 1/2 package's schema.

## 7. Test and validation results

| Package | Tests | Notes |
|---|---|---|
| `packages/shared-kernel` | 31 (was 29) | `Money::isGreaterThan()` added Sprint 5. |
| `packages/Queues` | 81 | Unaffected by Phase 3, confirmed unchanged at every sprint. |
| `packages/QueuePresence` | 81 (was 73) | 8 new tests for Sprint 3's additive read methods; all prior tests pass unmodified. |
| `packages/Auctions` | 66 | Aggregate, application services, persistence, all six sprints. |
| `packages/Bids` | 24 | Aggregate, `BidService`, persistence, two real-Postgres concurrency tests. |
| `apps/web` | 80 (was 66 at Phase 2 close) | End-to-end tests driving real `AuctionService`/`BidService` calls through the real presence/proximity/closing chain. |

**Total: 363 automated tests.** PHPStan/Larastan clean across every
package at every sprint. Pint clean on `packages/Auctions` and
`packages/Bids`; `apps/web` carries the same 12 pre-existing style issues
from Phase 1, confirmed unrelated and unchanged.

No manual browser acceptance test was performed — there is no
browser-facing surface yet to test (§9).

## 8. Known limitations and lessons learned

### Accepted limitations (by design, not oversights)

- **No scheduler exists.** Live-proximity enforcement (ADR-011) and
  closing evaluation (ADR-013) are both lazy — invoked only when a bid
  placement touches an existing active auction. An auction nobody bids on
  again after its deadline passes will sit `Open` indefinitely until
  something eventually touches it. This is the most consequential
  limitation carried into the delivery-layer phase.
- No cap on total soft-close extensions (ADR-013 §2).
- No minimum bid increment (ADR-012 §3).
- One `PresenceSession` backs at most one auction (ADR-009 §4) — an MVP
  restriction, not a permanent invariant.
- The 30-minute auction duration, 2-minute soft-close window/extension,
  15-minute staleness threshold, and 10-minute grace period are all
  provisional MVP configuration values, isolated in single swappable
  bindings, not validated against real usage or fraud data.

### Lessons learned this phase

- **Pint's `fully_qualified_strict_types` rule can silently inject a
  forbidden cross-package `use` statement** whenever a docblock's
  `{@see}` tag names a class from a package the current one must never
  depend on. This recurred twice (`Auction.php` → QueuePresence in Sprint
  1; `Bid.php` → Auctions in Sprint 5) and is now a recognized,
  recurring hazard — fixed both times by rewriting the docblock in prose
  without `{@see}`, then re-verifying via a full-tree `grep` for the
  forbidden namespace.
- **`TransactionManager::run()` needed a `Closure` parameter type with
  `@template`/`@param Closure(): TReturn` docblocks — not `callable` —**
  for Larastan to resolve `DB::transaction()`'s own generic template
  end-to-end; a manual `@var` hint on the call site alone was insufficient.
- **A timestamp column without explicit sub-second precision truncates to
  whole seconds at the Postgres type level**, independent of the PHP-side
  value's actual precision. `closes_at`/`opened_at` were never given
  `timestamp(6)` (unlike `presence_confidence_scores.computed_at`, which
  needs it to break same-second ties) — test fixtures for those columns
  should use whole-second `DateTimeImmutable` values, matching the
  column's real precision, rather than being given artificial sub-second
  fidelity.
- **Jumping a frozen clock forward to simulate a closing deadline can
  legitimately trigger an unrelated, correct transition** — advancing the
  clock past `closesAt` in a test also staled the seller's last real GPS
  ping relative to the new "now," correctly firing `LiveProximityChecker`
  first. This was a genuine, correct system interaction, not a defect,
  and the affected tests were updated to expect it rather than to avoid
  it.

## 9. Closure-scope decision

Phases 1 and 2 both closed only after a working frontend and a real
manual browser acceptance test — a bar this phase does not meet. Phase 3
closes anyway, by explicit decision, on the following rationale: the
Phase 3 objective was to deliver the Auctions and Bids **domain**, not
its delivery layer. Every item below is a delivery-layer concern that
does not change or invalidate anything already proven at the
domain/backend level, and each remains available to be picked up in a
later, separately-scoped delivery-layer phase:

- HTTP controllers for creating an auction, placing a bid, and reading an
  auction's current state/highest bid.
- A frontend (auction page, bid form).
- Reverb-backed real-time bidding updates — named in this phase's
  original roadmap scope but not built.
- A manual browser acceptance test of the full flow.

This is a documented, deliberate departure from Phases 1 and 2's closure
bar, not a silent lowering of it.

## 10. Deferred product decisions

- The real auction duration, and whether it should ever be
  seller-configurable or derived from queue/event metadata — the
  30-minute default is a placeholder.
- Whether/when to cap total anti-sniping extensions.
- Whether/when to introduce a minimum bid increment.
- What `Position` actually is, once product defines it, and whether the
  one-session-per-auction restriction should change accordingly.
- Whether the lazy-only enforcement model remains acceptable once real
  sellers exist, or whether a scheduler becomes necessary sooner than
  originally anticipated.

## 11. Phase 4 readiness assessment

Phase 3 provides what Phase 4 (Payments) needs to build on:

- A `Auction::selectWinningBid()` transition and `AuctionWon` event
  carrying the accepted bid's amount and bidder — the trigger point for
  authorizing payment (ADR-004: authorize at bid-win, capture at transfer
  confirmation).
- An immutable accepted-bid invariant already enforced at the domain
  level, so Payments can rely on a winning bid never changing once
  selected.
- A third and fourth proven package-per-bounded-context implementation
  (`packages/Auctions`, `packages/Bids`), confirming the pattern continues
  to generalize, including the established concurrency and
  after-commit-event-publication techniques Payments' own transactional
  work (webhook idempotency, capture-on-confirmation) can reuse.
- CI, translation-parity, and module-boundary architecture tests already
  green and enforced — Payments inherits these guardrails immediately.

**No blockers identified for Phase 4 at the domain level.** Note, however,
that Phase 4's real-world usefulness depends on an HTTP/frontend surface
existing for auctions and bids — a delivery-layer phase for Auctions/Bids
should be sequenced before or alongside early Payments work, a scheduling
question left to product/roadmap planning, not resolved by this report.
Per explicit instruction, Phase 4 implementation will not begin until
separately authorized.
