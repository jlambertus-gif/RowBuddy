# ADR 013: Auction Closing, Winner Selection, and Anti-Sniping Policy

## Status

Accepted — 2026-09-30.

## Context

Sprint 6 gives `Auction` a real closing lifecycle: a deadline, lazy
evaluation of that deadline under the existing bid-placement lock
(ADR-012), winner selection against Bids' data, and a soft-close
(anti-sniping) extension. `Auction::open()` has never had a closing
deadline — Sprint 1 didn't need one. This ADR resolves where that
deadline comes from, the exact anti-sniping mechanics, the shape of the
second `AuctionGateway` operation bid acceptance requires, the complete
locked sequence, and the winning-bid read contract — before any Sprint 6
code is written, the same discipline ADR-008 through ADR-012 already
established.

## Decision

### 1. Closing deadline: an explicit aggregate value, sourced by policy, not a hard-coded rule

**`Auction::open()` now requires an explicit `closesAt: DateTimeImmutable`
parameter.** The aggregate validates only the invariant it actually owns:
`closesAt` must be strictly after `openedAt` — enforced in the
constructor/factory itself (throwing a new `InvalidClosingDeadline`
exception), the same way `Money` validates non-negativity regardless of
which caller constructs it. The aggregate has **no knowledge of any
default duration** — it only ever receives and checks the value it's
given.

**`AuctionService::open()`'s public signature is unchanged** — callers
still don't pass a duration or a deadline. Internally, it now depends on:

```php
namespace RowBuddy\Auctions\Contracts;

interface AuctionDurationPolicy
{
    public function durationInSecondsFor(string $queueId): int;
}
```

owned by Auctions, deliberately accepting `$queueId` even though the MVP
implementation ignores it — so a future policy that derives duration from
event/queue metadata can replace the binding without changing this
interface or touching the aggregate at all. `AuctionService::open()` calls
`$this->durationPolicy->durationInSecondsFor($queueId)`, computes
`$closesAt = $clock->now()->modify("+{$seconds} seconds")`, and passes
that into `Auction::open()`.

**MVP default implementation**, framework-agnostic, living in
`packages/Auctions/src/Application/` (not `Infrastructure/` — it touches
no framework or database):

```php
final class FixedAuctionDurationPolicy implements AuctionDurationPolicy
{
    public function __construct(private readonly int $durationInSeconds) {}

    public function durationInSecondsFor(string $queueId): int
    {
        return $this->durationInSeconds;
    }
}
```

Bound in `AuctionsServiceProvider`:

```php
$this->app->bind(AuctionDurationPolicy::class, fn () => new FixedAuctionDurationPolicy(30 * 60));
```

**Provisional MVP default: 30 minutes.** This is explicitly a
placeholder configuration value for review, **not a permanent domain
invariant** — RowBuddy sells time-sensitive queue positions, and even 30
minutes may prove wrong for a given event once real usage exists. It is
isolated in exactly one binding, in one file, precisely so it can be
replaced (by a different default, or a genuinely metadata-driven policy)
without touching `Auction`, `AuctionService`, or any test that doesn't
specifically test duration. `Auction::open()` never knows or derives this
value — it never reads `AuctionDurationPolicy`, never computes a
duration, and never has a default of its own; the aggregate receives and
validates only the already-computed, explicit `closesAt` value handed to
it. Future implementations of `AuctionDurationPolicy` may derive
`closesAt` from event/queue metadata that doesn't exist yet, entirely by
replacing this one binding — the aggregate's contract (an explicit
`closesAt`, validated to be after `openedAt`) never has to change for
that to happen.

**Summary of what's persisted and why:**
- `closesAt` is required and persisted (a new, non-nullable column) —
  every `Auction` has one from the moment it's opened.
- `closesAt` must be after `openedAt` — an aggregate-level invariant,
  checked regardless of caller.
- The 30-minute figure is an MVP configuration value, not a permanent
  domain invariant — it lives in a swappable policy binding, not in
  `AuctionService` or the aggregate.
- Future product rules deriving `closesAt` from event/queue metadata
  require only a new `AuctionDurationPolicy` implementation and a changed
  binding — zero changes to `Auction`'s contract.

### 2. Anti-sniping policy: same treatment — configurable, not hard-coded

Mirroring §1's shape:

```php
namespace RowBuddy\Auctions\Contracts;

interface AntiSnipingPolicy
{
    public function softCloseWindowInSeconds(): int;

    public function extensionInSeconds(): int;
}
```

Default `FixedAntiSnipingPolicy` (same package location and binding
pattern as `FixedAuctionDurationPolicy`), proposed values: **2-minute
window, 2-minute extension** — placeholders, not final.

Rules, made explicit because they are easy to get subtly wrong:

- **Only an accepted bid can extend the deadline.** This is enforced
  structurally, not by a runtime check: the extension logic is only ever
  reachable from the code path that runs *after* `BidRepository::record()`
  has already succeeded (§4). There is no code path from a rejected or
  invalid bid attempt into the extension logic at all — not "a check that
  could be bypassed," but simply no wiring that could call it.
- **An invalid or rejected bid never extends the deadline** — the
  corollary of the above.
- **Each extension is calculated from the current `closesAt`, not from
  the bid's timestamp**: `newClosesAt = currentClosesAt + extensionInSeconds`.
  Calculating from the bid's arrival time instead would be a real bug —
  if `closesAt` is already, say, 90 seconds out when a bid lands, basing
  the extension on "now" could *shrink* the deadline relative to what it
  already was. Extensions always push the existing deadline forward, never
  reset it.
- **No cap on total extensions exists in Sprint 6.** A determined bidder
  could in principle keep an auction open indefinitely by bidding once
  every extension window. This is an **explicit, accepted MVP
  limitation**, not an oversight — mirroring ADR-012's "no minimum
  increment" posture: don't add a mechanism (a cap, and the product
  decision of what it should be) before a real scenario motivates it.
  Revisit if this is ever observed in practice.

### 3. Gateway responsibility: a second method on the existing `AuctionGateway`, not a new interface

**Chosen: add `applyAcceptedBidEffects()` to the existing `AuctionGateway`
interface**, rather than introducing a second interface to separate
"read/lock" from "mutation" semantics:

```php
interface AuctionGateway
{
    public function lockAndCheckForBidding(string $auctionId): AuctionLockResult;

    public function applyAcceptedBidEffects(string $auctionId, DateTimeImmutable $acceptedAt): AuctionLockResult;
}
```

Rationale for not splitting into two interfaces: `lockAndCheckForBidding()`
*already* mixes reading, locking, and mutation — it runs
`LiveProximityChecker`, which can persist a cancellation as a side effect
of being checked. The "read/lock vs. mutation" boundary the alternative
would try to enforce doesn't actually exist in this gateway's design
today, so splitting would separate two methods that already share
identical plumbing (lock acquisition, a collecting publisher, an
`AuctionLockResult` return shape) without buying a real boundary.

**The coordinator is `BidService` itself** — no new class is introduced
solely to sequence the two gateway calls. `BidService::place()` already
owns the transactional closure; calling `lockAndCheckForBidding()` before
validation and `applyAcceptedBidEffects()` after a successful `record()`
is sequencing it already does, just with one more step.

`AuctionLockResult::proximityEvents` is **renamed to `events`** — it now
carries proximity, closing, winner-selection, and extension events
depending on which gateway call produced it, not only proximity events.
Bids continues to treat every entry opaquely (`DomainEvent`, never a
concrete Auctions class).

### 4. The exact locked sequence

All of the following happens inside **one transaction, one connection**
(ADR-012 §1, unchanged):

```
BEGIN
  1. Lock the auction row (AuctionRepository::findByIdForUpdate).
  2. Run proximity evaluation (LiveProximityChecker) — persists a
     transition if one occurred; no-op if already terminal.
  3. Evaluate whether the auction is already due to close
     (AuctionClosingEvaluator): if status is still Open and
     clock->now() >= closesAt, call startClosing() then, using
     WinningBidLookup, either selectWinningBid() or
     expireWithoutWinningBid() — both transitions applied in memory,
     then persisted together in one write, releasing both resulting
     events at once. No-op if not yet due, or already non-Open.
  [end of lockAndCheckForBidding(); returns AuctionLockResult{snapshot, events}]
  4. BidService derives the accept/reject decision from
     snapshot.isOpenForBidding — false whether the cause was step 2's
     cancellation or step 3's closing, no new snapshot field needed.
  5. If not open: reject (AuctionNotFound / AuctionNotOpenForBidding).
     Nothing further executes — no insert, no extension.
  6. If open: validate currency match, seller-not-bidder, amount versus
     the current highest bid (or startingPrice if none).
  7. If valid: Bid::place(), BidRepository::record().
  8. If a bid was just recorded: call
     applyAcceptedBidEffects(auctionId, bid.placedAt) — extends closesAt
     if acceptedAt falls within [closesAt - softCloseWindow, closesAt),
     per §2's rules; persists and returns any resulting
     AuctionClosingDeadlineExtended event. No-op otherwise.
  9. Collect every event from steps 2, 3, and 8, plus BidPlaced if a bid
     was accepted, into BidPlacementOutcome.events, in that
     chronological order.
COMMIT
Publish BidPlacementOutcome.events, in order (ADR-012 §2, unchanged).
Return the accepted Bid, or throw the exception mapped from the
rejection reason (ADR-012 §1a, unchanged).
```

**Guarantee this sequence provides**: a bid arriving at or after
`closesAt` is never accepted. Step 3 runs unconditionally before step 4's
open-check — by the time validation happens, the auction's *actual*
current status (already transitioned to `Closing`→`Won`/`Expired` if due)
is what gets checked. There is no reachable path where a bid is validated
against an auction that is due to close but hasn't yet been evaluated as
such. The boundary is inclusive: `clock->now() >= closesAt` counts as due
— a bid arriving in the exact same instant as `closesAt` is treated as
late, not as the last valid bid.

### 5. `WinningBidLookup`: full candidate, explicit deterministic ordering

```php
namespace RowBuddy\Auctions\ValueObjects;

final class WinningBidCandidate
{
    public function __construct(
        public readonly string $bidId,
        public readonly string $bidderId,
        public readonly Money $amount,
        public readonly DateTimeImmutable $placedAt,
    ) {}
}

namespace RowBuddy\Auctions\Contracts;

interface WinningBidLookup
{
    public function highestBidFor(string $auctionId): ?WinningBidCandidate;
}
```

Implemented by an `apps/web` adapter bridging to Bids' `BidRepository`,
mirroring `SellerPresenceVerification`'s and `AuctionGateway`'s pattern
exactly — Auctions owns the port and the DTO, in its own vocabulary;
`packages/Auctions` gains no dependency on `packages/Bids`.

**Deterministic ordering, even though a tie is structurally
impossible today** (Bids requires a strictly-greater-than raise, so two
bids can never share the current-highest amount) — the query itself
should not rely on that invariant holding forever, or on undefined row
order if it were ever violated by a future change or manual data
correction. `BidRepository`'s query orders explicitly:
`ORDER BY amount_minor_units DESC, placed_at ASC, id ASC` — highest
amount first; if ever tied, the earlier bid wins (first to bid at that
price, not most recent); `id` as a final absolute tiebreaker.

**Consolidation**: `BidRepository` gains `findHighestBidFor(string $auctionId): ?Bid`
using this explicit ordering. `highestAmountFor()` (Sprint 5, used by
`BidService`'s own validation) becomes a thin wrapper —
`return $this->findHighestBidFor($auctionId)?->amount;` — so the ordering
logic exists in exactly one place, not duplicated across two queries.

## Consequences

- `Auction` gains a required `closesAt` field and a new
  `extendClosingDeadline(DateTimeImmutable, ClockInterface)` method,
  guarded to `Open` only. `startClosing()`, `selectWinningBid()`, and
  `expireWithoutWinningBid()` are unchanged — Sprint 1 already built them
  correctly; this ADR gives them their first real caller.
- `packages/Auctions` gains `AuctionDurationPolicy`/`FixedAuctionDurationPolicy`,
  `AntiSnipingPolicy`/`FixedAntiSnipingPolicy`, `WinningBidLookup`/`WinningBidCandidate`,
  `AuctionClosingEvaluator`, a `SoftCloseExtender` collaborator (applies
  §2's extension rule given an already-accepted bid's timestamp), and a
  new `AuctionClosingDeadlineExtended` event.
- `packages/Bids` gains `BidRepository::findHighestBidFor()`; `AuctionGateway`
  gains `applyAcceptedBidEffects()`; `AuctionLockResult::proximityEvents`
  is renamed to `events`, an update every existing caller/test must follow.
- `apps/web` gains a `WinningBidLookup` adapter bridging to Bids'
  `BidRepository`, alongside the existing `EloquentAuctionGateway`
  extension.
- No scheduler, no HTTP, no frontend — unchanged from the approved
  Sprint 6 scope.
- The 30-minute auction duration and the 2-minute/2-minute anti-sniping
  values are explicitly flagged as provisional MVP configuration, isolated
  in swappable bindings — not decisions this ADR treats as final product
  truth.
- The uncapped-extension limitation is accepted, not hidden — worth
  revisiting only if real usage or an incident gives a concrete reason to,
  the same posture ADR-008/010/011/012 already established for their own
  thresholds.
