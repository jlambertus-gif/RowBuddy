# ADR 011: Live-Proximity Enforcement Policy

## Status

Accepted — 2026-09-15.

## Context

`CLAUDE.md`'s core business rule "a seller must remain near the queue while
an auction is active" is a *continuous* requirement, distinct from
ADR-010's one-time Evidence Verified gate at creation. ADR-010 explicitly
deferred three questions to be resolved before this sprint's code is
written, rather than invented ad hoc during implementation:

1. What staleness threshold defines "no longer near the queue"?
2. What happens when proximity is lost while an auction is active?
3. What happens when the seller deliberately ends their `PresenceSession`
   (`PresenceSessionService::end()`) while an auction backed by it is
   still active?

This ADR resolves all three, and additionally decides *how* the check
runs operationally: lazily, on existing auction operations, with no new
scheduled/background infrastructure — Phase 3 has no Reverb wiring,
worker processes, or scheduler usage yet, and introducing one solely for
this would be premature background infrastructure ahead of the phases
that actually need it (bidding's real-time updates, later).

## Decision

### 1. Staleness threshold: 15 minutes

A within-geofence GPS ping older than 15 minutes (or none at all) counts
as stale. Chosen the same way ADR-008's and ADR-010's thresholds were:
long enough to tolerate ordinary signal gaps (phone locked, brief app
close), short enough to catch a seller who has actually left. Not derived
from data — there is none yet — and not to be tuned speculatively; revisit
only once real usage or an incident gives a concrete reason to.

### 2. Two-tier consequence of proximity loss

- **Session still active, but stale** (no within-geofence ping in the
  last 15 minutes): the auction is flagged **at risk**. No new bids may
  be accepted while at risk — nothing enforces that yet, since Bids
  doesn't exist, but the flag must exist now so Sprint 5 has something to
  consult. If a fresh within-geofence ping arrives before the grace
  period elapses, the flag clears automatically ("restored"). If it
  remains flagged for a further **10-minute grace period**, the auction
  is automatically **cancelled**.
- **Session no longer active** (`PresenceVerificationSnapshot.sessionActive
  === false`): cancel **immediately**, no grace period. This is also how
  question 3 above is resolved — see §3.

Both thresholds (15 + 10 minutes) are placeholders in the same spirit as
§1, not calibrated against data.

### 3. Ending a PresenceSession during an active auction: resolved via `sessionActive`, not a new QueuePresence guard

**QueuePresence's `PresenceSessionService::end()` is not changed.** The
seller may end their presence session at any time, even while it backs an
active auction. Sprint 3 already exposed `sessionActive` on
`PresenceVerificationSnapshot` (ADR-009 §2); the moment a session ends,
the very next proximity check (§5) reads `sessionActive: false` for that
auction and cancels it immediately under §2's second case — the same path
as any other confirmed, permanent loss.

This was deliberately chosen over the alternative considered at Sprint 4's
planning stage — a new port owned by QueuePresence (mirroring
`QueueGeofenceLookup`'s "consumer owns the port" shape) letting
`PresenceSessionService::end()` ask Auctions whether the session backs an
active auction before allowing the call. That alternative would require
*new business logic* inside QueuePresence's `end()` — a real behavior
change, not the purely additive, read-only extension Sprint 3 kept
QueuePresence to. The `sessionActive`-based resolution requires zero new
QueuePresence code and keeps that property intact for this sprint too.

### 4. New `Auction` state and fields

- `AuctionStatus` gains a `Cancelled` case (deliberately excluded from
  Sprint 1's scope; needed now).
- `Auction` gains `proximityAtRiskSince: ?DateTimeImmutable` — set once,
  the first time a check detects staleness; **not** reset on every
  subsequent stale check, so the 10-minute grace period is measured from
  the first detection, not extended indefinitely by repeated reads.
- New aggregate methods, guarded to `Open`/`Closing` only (mirroring
  Sprint 1's existing guard style): `flagProximityAtRisk`,
  `restoreProximity`, `cancelForProximityLoss` — each raising a new
  domain event (`AuctionProximityAtRisk`, `AuctionProximityRestored`,
  `AuctionCancelled`), all implementing `AuditableAction` (every
  Auction lifecycle event has been audited since Sprint 1; cancellation
  and proximity transitions are no less meaningful than opening or
  winning).

### 5. Enforcement runs lazily, on commands that act on an active auction — never on reads, no scheduler

A new `LiveProximityChecker` application-layer collaborator (analogous to
QueuePresence's `ConfidenceRecomputer`) re-reads the seller's current
`PresenceVerificationSnapshot` for a given `Auction`, applies §1–§3's
policy, performs at most one state transition, persists only if something
changed, and publishes any resulting event. It is a no-op for auctions
already in a terminal state (`Won`/`Expired`/`Cancelled`).

**The checker is invoked only by domain *commands* that naturally act on
an already-existing active auction** — bid placement (Sprint 5),
administrative actions, explicit lifecycle operations, and any future
active-auction command — never by a plain read. Loading an auction to
merely display or inspect it must stay side-effect-free: a read must
never implicitly perform a domain state transition. This rules out the
approach originally sketched during Sprint 4 planning (running the
checker from a `findById()`-style read accessor); that conflated "fetch"
with "act on," which is the wrong place for a mutation to hide.

Sprint 4 itself has no genuine command-type entry point that acts on an
*existing* active auction — `open()` only creates one, and checking a
just-created auction is inherently a no-op (nothing can be stale in the
same instant as creation, and the Evidence Verified gate already just
confirmed the seller is verified). Sprint 4 therefore builds the complete,
fully tested `Auction` additions and `LiveProximityChecker` with no
`AuctionService` wiring at all — its first real caller is Sprint 5's bid-
placement command, which must load the auction and run it through the
checker before evaluating the bid. This ADR establishes that as the
standing contract for every such command, present or future.

**Accepted limitation**: an auction nobody acts on will not be
proximity-checked, and therefore will not auto-cancel, no matter how long
the seller has actually been gone — reads never trigger it, and there is
no scheduler either. This is a deliberate trade against introducing
background infrastructure before any other part of this phase needs one.
Revisit once Reverb/worker infrastructure exists for bidding — at that
point, periodic checking largely falls out of infrastructure already
being built for other reasons, rather than being introduced solely for
this.

## Consequences

- `packages/Auctions` gains `AuctionStatus::Cancelled`, three new
  `Auction` methods and one new field, three new domain events, and a
  `LiveProximityChecker` collaborator — all before Bids exists to
  actually place a bid against an at-risk auction.
- No changes to `packages/QueuePresence` are required by this ADR — it
  stays fully unaware of Auctions, exactly as ADR-009 established.
- Proximity enforcement is only as timely as the next command that acts
  on the auction — not real-time, and never triggered by a mere read —
  until a scheduler is introduced in a later phase. Auctions/product
  should be aware an abandoned auction can sit "at risk" or even past its
  grace period without auto-cancelling if genuinely nothing acts on it.
- Sprint 5 (Bidding) inherits a fully built, fully tested
  `LiveProximityChecker` with no caller yet — its bid-placement command
  is expected to be the first one, per §5's standing contract.
