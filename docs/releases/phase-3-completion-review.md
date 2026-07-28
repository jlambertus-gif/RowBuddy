# Phase 3 (Auctions & Bids) — Completion Review

Status: **backend/domain exit criteria met; not yet formally closed.**
This is a review for approval, not a closure declaration — no tag has
been created, nothing has been pushed, and this document explicitly
recommends against doing so yet. See §9.

## 1. Executive summary

Phase 3 delivers the full backend/domain lifecycle of an auction — from
creation gated on verified physical presence (ADR-009/ADR-010), through
concurrency-safe bid placement (ADR-012) with live-proximity enforcement
(ADR-011) and anti-sniping (ADR-013), to lazy closing and winner
selection under the same database lock. The roadmap's Phase 3 exit
criterion — "an auction can run end-to-end (open → closing → winning bid
selected) under simulated concurrent bidding with correct, tested
row-locking behavior" — is met, proven by real-Postgres concurrency
tests, not merely unit tests with fakes.

This was delivered as 6 sprints, each scoped, implemented, tested, and
reviewed independently, with 5 new ADRs (009–013) resolving every
judgment call — deadline sourcing, minimum verification tier,
live-proximity policy, the Bids/Auctions concurrency contract, and
closing/anti-sniping — before the sprint that needed it, the same
discipline Phases 1 and 2 established.

**What is deliberately not here, and why this is a review rather than a
closure**: unlike Phases 1 and 2, which both ended with an HTTP/frontend
sprint and a full manual browser acceptance test before formal
acceptance, Phase 3 has no HTTP endpoints, no frontend, no Reverb, and
has never been exercised outside of automated tests. Every sprint's
scope explicitly excluded these, sprint by sprint, by design — but that
means the bar Phases 1 and 2 were held to before tagging has not yet been
reached here. §9 lays out what closing that gap would require.

## 2. Sprint-by-sprint deliverables

| Sprint | Delivered |
|---|---|
| 1 | `Auction` aggregate scaffold — Open/Closing/Won/Expired state machine, immutable accepted-winning-bid invariant, domain events. No persistence, no HTTP, no QueuePresence coupling. |
| 2 | Persistence layer — `AuctionRepository` port, `EloquentAuctionRepository`, `auctions` migration with a full unique constraint on `presence_session_id` (ADR-009 §4's MVP restriction), `PresenceSessionAlreadyConsumed`. |
| 3 | ADR-009 (`SellerPresenceVerification` port, `apps/web` adapter) and ADR-010 (Evidence Verified gate in `AuctionService::open()`, fail-closed). Two minimal, purely additive read methods added to QueuePresence to support the bridge — no existing QueuePresence behavior, persistence semantics, or public API changed incompatibly. |
| 4 | ADR-011 — `Cancelled` status, `proximityAtRiskSince`, `LiveProximityChecker` (15-min staleness → at-risk → 10-min grace → cancel; immediate cancel on `sessionActive: false`). Built complete with no caller yet, by design — invoked only by commands that act on an existing active auction, never a read. |
| 5 | ADR-012 — new `packages/Bids` bounded context: immutable append-only `Bid`, record-only `BidRepository`, `BidService::place()` orchestrating one transaction spanning the auction-row lock, `LiveProximityChecker` (its first real caller), highest-bid lookup, validation, and insertion. `BidPlacementOutcome` keeps expected rejections from rolling back a legitimate proximity transition. Real-Postgres concurrency test. |
| 6 | ADR-013 — explicit required `closesAt` (validated against `openedAt`, sourced via swappable `AuctionDurationPolicy`), `AuctionClosingEvaluator` (closing → winner selection or expiry under the same lock), `SoftCloseExtender` (anti-sniping, reachable only after an accepted bid), Auctions-owned `WinningBidLookup` with deterministic ordering. Real-Postgres test proving a concurrent bidder observes the committed, extended deadline only after the lock releases. |

## 3. ADRs created

- **ADR-009 — Auctions–Presence Verification Contract**: synchronous `SellerPresenceVerification` port, owned by Auctions; one `PresenceSession` backs at most one auction, explicitly an MVP restriction pending a future `Position` concept.
- **ADR-010 — Minimum Confidence Tier and Live-Presence Policy**: Evidence Verified required (Location Verified is GPS-reachable alone, so cannot satisfy "GPS alone is not sufficient"); live proximity deferred as a separate, continuous concern.
- **ADR-011 — Live-Proximity Enforcement Policy**: 15-min staleness, two-tier consequence (at-risk → 10-min grace → cancel, or immediate cancel on `sessionActive: false`), lazy enforcement, no scheduler.
- **ADR-012 — Bids–Auctions Gateway Contract and Concurrency Strategy**: `AuctionGateway` port owned by Bids; auction-row lock as sole serialization anchor; `BidPlacementOutcome` separating expected rejection from unexpected failure; after-commit event publication; real-Postgres proof of the race being prevented, not just the lock blocking.
- **ADR-013 — Auction Closing, Winner Selection, and Anti-Sniping Policy**: explicit `closesAt` sourced by policy, not hard-coded; `AuctionDurationPolicy` (30-min provisional default) and `AntiSnipingPolicy` (2-min window/extension); `WinningBidLookup` with deterministic tiebreak ordering; the exact locked sequence guaranteeing a late bid is never accepted.

ADRs 001–008 were pre-existing and remain unchanged and binding.

## 4. Architecture changes

- Two new packages: `packages/Auctions` and `packages/Bids`, following the exact package shape established in Phase 1/2.
- Two new cross-module read ports, symmetric in direction: `SellerPresenceVerification` (Auctions → QueuePresence) and `WinningBidLookup` (Auctions → Bids); plus `AuctionGateway` (Bids → Auctions). All follow the same "consumer owns the port, `apps/web` implements the adapter" pattern as `QueueGeofenceLookup` (Phase 2).
- A new concurrency pattern for this codebase: a single database transaction and row lock spanning multiple collaborators (`LiveProximityChecker`, `AuctionClosingEvaluator`, `SoftCloseExtender`, `BidRepository`), with expected business outcomes returned as data (`BidPlacementOutcome`) rather than exceptions, specifically so a legitimate side-effect transition is never rolled back by an unrelated, ordinary rejection.
- After-commit event publication via a collecting `DomainEventPublisher` constructed per-call in the `apps/web` adapter — no outbox table introduced.
- Two minimal, purely additive extensions to the already-closed Phase 2 `packages/QueuePresence` (Sprint 3) — no behavior, persistence semantics, or public API changed incompatibly; all prior Phase 2 tests pass unmodified.

## 5. Database changes

`packages/Auctions/database/migrations/`:
- `create_auctions_table` (Sprint 2)
- `add_proximity_at_risk_since_to_auctions_table` (Sprint 4)
- `add_closes_at_to_auctions_table` (Sprint 6)

`packages/Bids/database/migrations/`:
- `create_bids_table` (Sprint 5) — append-only, no update/delete path anywhere in the codebase for this table.

No changes to any Phase 1/2 package's schema.

## 6. Test and validation results

| Package | Tests | Notes |
|---|---|---|
| `packages/shared-kernel` | 31 (was 29) | `Money::isGreaterThan()` added Sprint 5. |
| `packages/Queues` | 81 | Unaffected by Phase 3, confirmed unchanged at every sprint. |
| `packages/QueuePresence` | 81 (was 73) | 8 new tests for Sprint 3's additive read methods; all prior tests pass unmodified. |
| `packages/Auctions` | 66 | Aggregate, application services, persistence, all six sprints. |
| `packages/Bids` | 24 | Aggregate, `BidService`, persistence, two real-Postgres concurrency tests. |
| `apps/web` | 80 (was 66 at Phase 2 close) | Includes real end-to-end tests driving actual HTTP-based presence capture through to real `AuctionService`/`BidService` calls (not HTTP endpoints of their own). |

**Total: 363 automated tests.** PHPStan/Larastan clean across every package at every sprint. Pint clean on `packages/Auctions` and `packages/Bids`; `apps/web` carries the same 12 pre-existing style issues from Phase 1, confirmed unrelated and unchanged at every sprint.

**No manual browser acceptance test was performed** — there is no browser-facing surface to test. This is the central gap flagged in §9.

## 7. Known limitations (accepted, not oversights)

- **No scheduler exists.** Live-proximity enforcement (ADR-011) and closing evaluation (ADR-013) are both lazy — invoked only when a command (bid placement, currently the only one) touches an existing active auction. **An auction nobody bids on again after its deadline passes will sit `Open` indefinitely**, never transitioning to `Expired`, until something eventually touches it. This is the most consequential limitation to flag for product: without a Sprint 5-or-later HTTP/bidding surface actually being used, or a future scheduler, closed-but-untouched auctions do not self-close.
- **No cap on total soft-close extensions** (ADR-013 §2). A bidder could in principle keep extending an auction indefinitely by bidding within the window repeatedly.
- **No minimum bid increment** (ADR-012 §3). A bid exceeding the floor by one minor currency unit is valid.
- **One `PresenceSession` backs at most one auction** (ADR-009 §4) — an MVP restriction, not a permanent invariant, pending a `Position` concept that does not exist yet. Nothing currently prevents a seller from starting a *new* presence session for the same queue after an auction concludes and auctioning again — this is allowed implicitly, not by any explicit rule.
- **The 30-minute auction duration, the 2-minute soft-close window, the 2-minute extension, the 15-minute staleness threshold, and the 10-minute grace period are all provisional MVP configuration values**, isolated in single swappable bindings, explicitly not validated against real usage or fraud data (none exists yet).

## 8. Deferred product decisions

- What the actual auction duration should be, and whether it should ever be seller-configurable or derived from queue/event metadata (ADR-013 §1) — the 30-minute default is a placeholder, not a product answer.
- Whether/when to introduce a cap on total anti-sniping extensions.
- Whether/when to introduce a minimum bid increment.
- What `Position` actually is, once product is ready to define it, and whether the one-session-per-auction restriction should then change.
- Whether the lazy-only enforcement model remains acceptable once real sellers exist, or whether a scheduler becomes necessary sooner than "once Reverb/worker infrastructure exists for bidding" (ADR-011's original framing).

## 9. Remaining work before Phase 3 can be tagged and closed

Phase 3's roadmap exit criterion is met at the backend/domain level, but tagging and pushing now would set a materially lower bar than Phases 1 and 2 were held to — both of which required a working frontend and a real manual browser walkthrough before acceptance. Recommended before formal closure:

1. **An HTTP layer** for at minimum: creating an auction, placing a bid, and reading an auction's current state/highest bid — thin controllers over the existing `AuctionService`/`BidService`, mirroring Phase 1/2's established controller style.
2. **A minimal frontend** — an auction page and a bid form, following the existing Inertia/React patterns, localized (en/es), matching Phase 1/2's UI quality bar.
3. **A real manual browser acceptance test** of the complete flow: verified seller opens an auction → a second user bids → the deadline passes (or is simulated) → a winner is selected — the same category of test that caught two genuine defects in Phase 2 that no automated test had reached.
4. **An explicit decision on Reverb.** The original Phase 3 scope (`docs/roadmap.md`) names "Reverb-backed real-time bidding" — Sprint 6 did not build this. Product should decide whether real-time updates are required before Phase 3 closes, or whether they are explicitly deferred to a later phase alongside the scheduler question in §7.
5. **A decision on the scheduler question from §7** — at minimum, an explicit acknowledgment (or resolution) of the "auctions can go stale forever if untouched" limitation before this phase is presented as done.

None of this needs to happen in this review — it is scoped here so you can decide whether to authorize a Sprint 7 (HTTP + frontend + acceptance test, mirroring Phase 1/2's closing sprint) before tagging, or to close Phase 3 now with these gaps explicitly documented as known and deferred.

**No Phase 4 work has begun, and none will begin until this review is explicitly approved and a decision on the above is made.**
