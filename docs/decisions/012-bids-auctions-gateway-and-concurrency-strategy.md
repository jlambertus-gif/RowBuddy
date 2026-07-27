# ADR 012: Bids–Auctions Gateway Contract and Concurrency Strategy

## Status

Accepted — 2026-09-22.

## Context

Sprint 5 introduces `packages/Bids` (`docs/product/claude-mvp-analysis.md`
§6–7: "deliberately split from Auctions for performance isolation... this
is the highest-write-contention path in the system"). Placing a bid must:

- read and validate against the auction's current state and current
  highest bid without a lost-update race under concurrent bidders,
- never expose a `BidPlaced` event to listeners before the underlying
  write has actually committed,
- run the already-built (Sprint 4) `LiveProximityChecker` as part of the
  same operation, per ADR-011 §5's standing contract that its first real
  caller would be a command that acts on an existing active auction,
- do all of this without Bids depending on Auctions' Eloquent models,
  repositories, services, or enums directly.

This ADR resolves the concurrency mechanism, the transaction/connection
boundary, the event-publication timing, the monetary validation rules, and
the concurrency test strategy — before any Sprint 5 code is written, the
same discipline ADR-008 through ADR-011 already established.

## Decision

### 1. Transaction and connection boundary

`TransactionManager`, `AuctionGateway`, `EloquentAuctionRepository`, and
`EloquentBidRepository` all participate in **one transaction on one
connection** — Laravel's single configured default database connection.
No module opens a second connection or a nested/savepoint transaction.
Concretely:

```
Bids\Contracts\TransactionManager::run(callable $work): mixed
```

implemented via `DB::transaction($work)`. Because every Eloquent model
involved (`AuctionModel`, `BidModel`) uses the same default connection,
every query issued anywhere inside `$work` — by `AuctionGateway`, by
`BidRepository`, by `LiveProximityChecker` internally — is automatically
part of that one transaction. No connection needs to be threaded through
explicitly; this falls out of using Laravel's ordinary default-connection
behavior consistently.

**The auction row remains locked for the full duration of `$work`**:

```
BEGIN
  SELECT * FROM auctions WHERE id = ? FOR UPDATE      -- AuctionGateway::lockAndCheckForBidding()
  [LiveProximityChecker runs against the locked Auction, may UPDATE it]
  re-derive isOpenForBidding from the (possibly just-updated) status
  SELECT MAX(amount) FROM bids WHERE auction_id = ?    -- BidRepository::highestAmountFor()
  [validate: currency match, amount > current floor, seller != bidder]
  INSERT INTO bids (...) only if every check above passed
COMMIT                                                  -- always, whether the bid
                                                         -- was accepted or expectedly
                                                         -- rejected — see §1a
```

**Why `READ COMMITTED` (Postgres's default) is sufficient**: no other
transaction can even *attempt* to insert a competing bid for this auction
without first acquiring the same `FOR UPDATE` lock on the same `auctions`
row — and every bid-placement attempt is required to go through
`AuctionGateway::lockAndCheckForBidding()` first, by construction (there
is no other write path into `bids`). A concurrent attempt is therefore
blocked at the very first step, before it can read anything, not merely
at the final insert. The `highestAmountFor()` read a few statements later
cannot become stale mid-transaction, because nothing else could have
written to `bids` for this auction since the lock was acquired. No
`SERIALIZABLE` isolation or explicit `SELECT ... FOR UPDATE` on the `bids`
rows themselves is needed — the `auctions` row is the sole serialization
anchor.

### 1a. Expected business rejection vs. unexpected transaction failure

**A validation failure is not a transaction failure.** `AuctionNotOpenForBidding`,
`CurrencyMismatch`, `BidTooLow`, and `SellerCannotBidOnOwnAuction` are
*expected, anticipated outcomes* of evaluating a bid — the same category
as a form validation error, not a database error. Throwing any of them
from inside `$work` would make `DB::transaction()` roll back everything,
including a `LiveProximityChecker` transition that had nothing to do with
the bid being rejected — making live-proximity enforcement's durability
depend on whether an unrelated bid happens to be valid. That is exactly
the outcome this revision rules out.

**The transactional closure therefore never throws for an expected
rejection.** It always returns a `Bids\ValueObjects\BidPlacementOutcome`:

```
BidPlacementOutcome {
    bid: ?Bid,                              // non-null only when accepted
    rejectionReason: ?BidRejectionReason,   // non-null only when rejected
    events: list<DomainEvent>,              // always populated with whatever
                                             // legitimately happened, regardless
                                             // of acceptance/rejection
}

enum BidRejectionReason {
    AuctionNotFound
    AuctionNotOpenForBidding
    CurrencyMismatch
    BidTooLow
    SellerCannotBidOnOwnAuction
}
```

Revised sequence inside `$work`:

```
auction = AuctionRepository::findByIdForUpdate(auctionId)
if auction is null:
    return Outcome(bid: null, rejection: AuctionNotFound, events: [])

auction = LiveProximityChecker::check(auction)   -- persisted now, regardless of
                                                  -- what happens below
proximityEvents = collected from the checker's local publisher
snapshot = derive AuctionSnapshot from the (possibly just-updated) auction

if !snapshot.isOpenForBidding:
    return Outcome(bid: null, rejection: AuctionNotOpenForBidding, events: proximityEvents)
if amount.currency != snapshot.startingPrice.currency:
    return Outcome(bid: null, rejection: CurrencyMismatch, events: proximityEvents)
if bidderId == snapshot.sellerId:
    return Outcome(bid: null, rejection: SellerCannotBidOnOwnAuction, events: proximityEvents)

currentHighest = BidRepository::highestAmountFor(auctionId) ?? snapshot.startingPrice
if !amount.isGreaterThan(currentHighest):
    return Outcome(bid: null, rejection: BidTooLow, events: proximityEvents)

bid = Bid::place(...)
BidRepository::record(bid)
return Outcome(bid: bid, rejection: null, events: [...proximityEvents, ...bid.releaseEvents()])
```

**`TransactionManager::run()` commits unconditionally whenever `$work`
returns an `Outcome` normally** — whether that outcome represents
acceptance or an expected rejection. This guarantees:

- if proximity cancellation occurs, no bid is inserted (the
  `!snapshot.isOpenForBidding` branch returns before reaching the insert);
- the cancellation remains committed regardless (nothing in the branches
  above it throws);
- an at-risk (non-cancelling) transition may remain committed even when
  the bid is rejected for an unrelated reason — e.g. the checker flags
  the auction at risk, it stays `Open`, and the bid is then rejected for
  `BidTooLow`: the at-risk flag was already saved before that rejection
  branch was even evaluated, and nothing in this design unwinds it.

**Only a genuinely unexpected failure — a lost DB connection, a
constraint violation none of the checks above anticipated, any other
uncaught `Throwable`** — propagates out of `$work` uninterpreted. Laravel's
`DB::transaction()` catches it, rolls back everything (correctly: an
infrastructure failure means we cannot trust *any* of what happened in
this attempt, including the proximity check), and rethrows. This is the
one case where the original all-or-nothing behavior still applies, and it
is the right behavior for it.

### 2. Event publication: collect during the transaction, publish only after commit — no outbox

**No event is published while the transaction is open.** Two distinct
sources of events exist in this flow, both deferred the same way:

- `Bid::place()`'s own `BidPlaced` event (only present when the outcome's
  `bid` is non-null).
- Any event `LiveProximityChecker` raises while `AuctionGateway` runs it
  against the locked auction (`AuctionProximityAtRisk`,
  `AuctionProximityRestored`, or `AuctionCancelled`) — present in
  `BidPlacementOutcome::events` regardless of whether the bid itself was
  accepted or rejected, per §1a.

`LiveProximityChecker` (Sprint 4) publishes through whatever
`Auctions\Contracts\DomainEventPublisher` it is constructed with — this
was a deliberate seam left open by dependency injection, not something
this ADR needs to modify. `AuctionGateway`'s real implementation
constructs its own **local** `LiveProximityChecker` for the duration of
the call, injecting a small collecting publisher
(`App\Infrastructure\CollectingDomainEventPublisher implements
Auctions\Contracts\DomainEventPublisher`, living in `apps/web` alongside
the gateway) that appends events to an array instead of dispatching them.
`lockAndCheckForBidding()` itself returns a small gateway-level result —
`Bids\ValueObjects\AuctionLockResult { snapshot: ?AuctionSnapshot,
proximityEvents: list<DomainEvent> }` — distinct from, and one layer
below, `BidService`'s own `BidPlacementOutcome` (§1a). `BidService` is
the one that merges `AuctionLockResult::proximityEvents` into the
`BidPlacementOutcome::events` it ultimately returns, alongside `BidPlaced`
if a bid was also accepted.

`BidService::place()` never calls `DomainEventPublisher::publish()` from
*inside* the closure it hands to `TransactionManager::run()`. The closure
returns a `BidPlacementOutcome` (§1a) unconditionally; publication and
the accept/reject decision both happen **only after `run()` returns**:

```
$outcome = $this->transactions->run(function () {
    ... §1a's full sequence, returning a BidPlacementOutcome ...
});

foreach ($outcome->events as $event) {
    $this->events->publish($event);   // real publisher, now safely after commit
}

if ($outcome->bid !== null) {
    return $outcome->bid;
}

throw $this->exceptionFor($outcome->rejectionReason);   // thrown AFTER commit —
                                                          // the commit already
                                                          // happened; this only
                                                          // signals the caller
```

Proximity events are published *before* `BidPlaced` in that loop —
preserving the causal order in which they actually occurred relative to
the bid decision. **Committed state and published events always match**:
if `AuctionCancelled` is in `$outcome->events`, the cancellation was
already committed by the time it's published, whether or not a bid
follows it. If `TransactionManager::run()` itself throws — the
*unexpected*-failure path from §1a, not an expected rejection — nothing
is ever published, because no `$outcome` was ever produced: the whole
attempt, proximity check included, rolled back and nothing legitimate was
lost, only failed outright.

This satisfies after-commit publication without a transactional outbox:
no events table, no separate dispatcher poll loop, no at-least-once
redelivery concern. An outbox would only earn its complexity if events
needed to survive an *application* crash between commit and publish —
not a requirement here, and not introduced.

**Verifying "no pre-commit event escapes" is a control-flow property, not
a database-visibility one** — `RecordAuditEvent` (the only listener today)
is synchronous, so there is no queued/async consumer that could observe a
prematurely published event before rollback. The real Postgres test in
§4 proves the *locking* is correct; it does not exercise `BidService` or
any publisher at all. A separate, fast application-layer unit test (fakes
only, `packages/Bids/tests/Application/BidServiceTest.php`) proves the
*ordering* directly: a fake `TransactionManager` records a timestamp/step
marker when its `run()` callback starts and finishes, and the fake
`DomainEventPublisher` records when each `publish()` call happens; the
test asserts every recorded `publish()` occurred strictly after `run()`'s
callback returned. This is the appropriate tool for this property —
proving it against real Postgres would require an async consumer that
does not exist in this codebase yet.

### 3. Monetary rules

- **Currency must match.** `amount->currency` must equal
  `snapshot->startingPrice->currency`. Checked explicitly, first, before
  any arithmetic — a mismatch throws `Bids\Exceptions\CurrencyMismatch`
  (a named domain exception), not `Money`'s own generic
  `ValidationException` (which `assertSameCurrency` would throw if we let
  arithmetic discover the mismatch instead of checking it ourselves).
- **The first bid must be strictly greater than `startingPrice`.** Not
  `>=`.
- **Every subsequent bid must be strictly greater than the current
  highest recorded bid.** Not `>=`, and not compared against
  `startingPrice` once at least one bid exists.
- **No minimum increment is introduced in Sprint 5.** A bid exceeding the
  floor by the smallest representable unit (one minor unit) is valid.
  This mirrors the "don't tune speculatively" posture already applied to
  ADR-008/010/011's thresholds — add an increment rule later only if a
  concrete product reason emerges.
- **`Money` gains one new method**: `isGreaterThan(Money $other): bool`,
  in `packages/shared-kernel`. `Money` already centralizes currency-safety
  for arithmetic (`assertSameCurrency`, reused by `add`/`subtract`); a
  comparison method belongs there for the same reason, rather than Bids
  performing an unsafe raw `minorUnits` comparison of its own. This is
  the only shared-kernel change in this ADR — small, mechanical, and
  available to every module, not Bids-specific logic leaking upward.

### 4. Concurrency test: proving the race is actually prevented, not just that locks block

A `SELECT ... FOR UPDATE` that merely blocks a second connection proves
locking exists; it does not prove the bid-placement *logic* is race-free.
The test must show that a bidder who would have validated successfully
against a **stale** highest-bid reading is instead correctly rejected
once forced to read the **current** one.

**Mechanism**: two independent, real `PDO` connections to the same
Postgres database (env-configured, same skip-if-unreachable pattern as
`packages/Queues`' PostGIS integration tests) — not two Eloquent/Capsule
managers, and not `pcntl_fork` or subprocesses. Postgres treats two `PDO`
handles as two independent sessions regardless of the driving PHP
process's single-threadedness, so this is sufficient to exercise real
lock contention deterministically. The test creates its own minimal
`auctions`/`bids`-shaped tables via raw SQL — it never imports Auctions'
or Bids' Eloquent models, keeping it a pure persistence-layer proof.

**Sequence**:

1. Seed one auction row, `starting_price = 1000`, no bids.
2. Connection A: `BEGIN`; `SELECT ... FOR UPDATE` (acquires the lock; does
   not commit yet).
3. Connection B: `SET LOCAL lock_timeout = '200ms'`; `BEGIN`; attempt the
   same `SELECT ... FOR UPDATE`. Assert this fails with a Postgres
   lock-timeout error (`55P03`) — proving contention is real. **This is
   the bounded-timeout mechanism**: B can never hang indefinitely waiting
   on A: it fails fast within 200ms and the test asserts on that failure
   rather than blocking. This is the "equivalent mechanism" to a test
   timeout, applied at the SQL level rather than the PHPUnit level. B's
   failed transaction is rolled back immediately (`ROLLBACK`) so its
   session holds nothing going into step 4.
4. Still on connection A, **the lock held throughout**: read the highest
   bid (`SELECT MAX(amount)` — none yet, so the floor is 1000), validate
   a bid of 1100 against it, `INSERT INTO bids`, only then `COMMIT`
   (releasing the lock). The lock is provably still held across all three
   of these statements because step 3 already proved B cannot acquire it
   until A finishes — there is no point between `BEGIN` and `COMMIT`
   where a competitor could interleave.
5. Connection B: retry `SELECT ... FOR UPDATE` (now succeeds immediately,
   since A committed — proving B observes A's *committed* bid, not a
   stale pre-commit state). Read the highest bid — assert it is now
   **1100**, not the 1000 that was current when B's first attempt was
   blocked in step 3. Attempt a bid of **1050** — a value that *would
   have passed* validation against the stale 1000 floor B could have read
   had it not been blocked. Assert it is rejected (the test's own
   validation check, mirroring `BidTooLow`'s condition, since this test
   exercises raw SQL, not `BidService`). `COMMIT` (nothing was inserted,
   so this is a formality) or `ROLLBACK` — either is safe since no write
   occurred.

Step 5 is the actual proof the requirement asks for: it demonstrates that
the connection forced to wait never gets a chance to act on stale data —
it either fails outright (step 3) or, once unblocked, is guaranteed to see
the post-commit state (step 5), never something in between. No wall-clock
race, no flakiness from thread-scheduling — the interleaving is fully
controlled by the test itself. Whether the outcome would be
`BidPlacementOutcome`'s `BidTooLow` rejection in the real `BidService` is
covered separately by the fast fake-based unit tests in
`BidServiceTest.php` — this test's job is exclusively to prove the SQL
locking primitive that those unit tests assume works.

**Deterministic cleanup**: both connections' transactions are guaranteed
closed before the test ends — a `finally` block (or Pest's `afterEach`)
issues `ROLLBACK` on both connections unconditionally (a `ROLLBACK` with
no open transaction is a harmless no-op in Postgres, so this is safe to
call even along the success path where both sides already committed or
rolled back explicitly), then drops the test's own throwaway tables. Both
`PDO` connections are explicitly closed (`= null`) at the end of
`afterEach` so no session outlives the test. Combined with step 3's
`lock_timeout`, no part of this test can hang indefinitely, and no table
or connection leaks into the next test.

### 5. Gateway responsibility boundary

`AuctionGateway`'s real implementation (`apps/web`, alongside
`EloquentQueueGeofenceLookup` and `QueuePresenceSellerVerification`) is
the **only** place allowed to reference `RowBuddy\Auctions\*` — its
`Auction` type, `AuctionRepository`, `LiveProximityChecker`, and
`AuctionStatus` enum. `packages/Bids` itself never imports any of them:
`AuctionGateway`'s interface and its `AuctionSnapshot`/`AuctionLockResult`
result types (§2) are defined entirely in Bids' own vocabulary, using only
shared-kernel types (`Money`, `DomainEvent`) where a cross-cutting type is
unavoidable — the same shape ADR-009 already established for
`SellerPresenceVerification`. `Auctions` remains fully unaware that
`Bids`, or this gateway, exist; the dependency runs one way only
(Bids → port → adapter → Auctions), exactly as ADR-009 §1 and ADR-011 §3
already established for the QueuePresence direction.

## Consequences

- `packages/shared-kernel` gains `Money::isGreaterThan()` — a small,
  general-purpose addition, not Bids-specific business logic.
- `packages/Auctions` gains one additive method,
  `AuctionRepository::findByIdForUpdate()` — same category as Sprint 3's
  QueuePresence additions: a read/lock capability, no new business rule.
- `packages/Bids` depends only on `shared-kernel` and its own ports — it
  has zero package-level dependency on `Auctions` or `QueuePresence`,
  matching the modular-monolith boundary from ADR-001.
- `apps/web` gains `AuctionGateway`'s real implementation and
  `CollectingDomainEventPublisher`, both thin composition-root glue, no
  business logic, no HTTP.
- `packages/Bids` gains `ValueObjects\BidPlacementOutcome` and
  `ValueObjects\BidRejectionReason` — an expected-rejection-as-data
  pattern, not exceptions, for exactly the four anticipated rejection
  cases (§1a). `BidService::place()` is the only place that translates a
  `BidRejectionReason` into the named exception (`AuctionNotOpenForBidding`,
  `CurrencyMismatch`, `BidTooLow`, `SellerCannotBidOnOwnAuction`,
  or shared-kernel's `NotFoundException` for `AuctionNotFound`) the rest
  of the codebase already expects to catch — callers outside `BidService`
  never see `BidPlacementOutcome` itself.
- A `LiveProximityChecker` transition (at-risk, restored, or cancelled) is
  now committed and published **independently of whether the bid attempt
  that triggered the check succeeds** — reversing this ADR's original
  position, per your explicit requirement. Only a genuinely unexpected
  infrastructure failure rolls back a proximity transition together with
  the bid attempt that surfaced it.
- No outbox table, no scheduler, no queued listeners were introduced to
  achieve after-commit publication — collecting events into
  `BidPlacementOutcome` and publishing them after `TransactionManager::run()`
  returns is sufficient for this codebase's current needs.
- Proving "no pre-commit event escapes" and proving "the lock prevents a
  stale-data race" are two different properties verified by two different
  tests — a fast fake-based unit test for the former, the real-Postgres
  test for the latter (§2, §4). Neither test alone would cover both.
