# ADR 018: Transfer Window, Expiry, Re-Authorization, and Scheduler Policy

## Status

Accepted — 2026-10-23.

## Context

Nothing in this codebase currently models a transfer deadline —
`Auction` has no `transferWindow` field (confirmed absent in
`packages/Auctions/src/Auction.php`), and the Phase 5 architecture review
flagged this as a genuine gap. ADR-004 requires the combined auction
duration plus transfer window to fit inside the card-authorization
validity window (~5–7 days) and requires "a defined re-authorization flow
... rather than allowing a silent capture failure." Unlike everything
built in Phases 3–4, this is genuinely time-triggered: there is no
bid-placement-style traffic to piggyback a lazy check on once an auction
is `Won` and a `Transfer` is `Issued` — this is very likely where this
codebase needs its first real scheduled background job.

Two related, previously-unresolved product questions also block a clean
design here: what happens when a *buyer* never shows up (distinct from a
*seller* who abandons the queue, per `claude-mvp-analysis.md` §2 items
7–8), and how long the transfer window itself should be.

## Alternatives considered

Every prior phase in this codebase avoided a scheduler by piggybacking
evaluation on traffic that was already guaranteed to keep happening
(ADR-011's proximity check and ADR-013's closing evaluation both ride on
bid-placement calls). Before introducing a genuinely new kind of
infrastructure, the same three alternatives that worked before — plus
the hybrid of them — need to be weighed against what's actually
different about this problem.

**What's different this time:** the event this ADR needs to detect is
*silence* — nobody confirms, nobody re-visits the transfer at all. Every
prior lazy-evaluation success story depended on a *guaranteed* future
command (another bid) that would eventually re-touch the entity even in
the failure case being guarded against. A `Transfer` that nobody ever
confirms has no such guaranteed future command by definition — the
failure mode *is* the absence of any command.

### Option A — Lazy evaluation only (the established pattern)

Evaluate expiry only when some other command touches the `Transfer` —
e.g., a confirmation attempt, or a status-check read from a future
frontend.

- **Pro**: zero new infrastructure; consistent with every prior phase's
  discipline.
- **Con (fatal for this problem)**: if neither party ever confirms *and*
  nobody ever opens the app to check status, nothing ever evaluates
  expiry. Unlike bid placement (which happens precisely because the
  auction is still contested), "checking on a transfer" is not
  intrinsic to any process guaranteed to keep running — a buyer who
  ghosts has no reason to open the app again. Lazy evaluation can only
  detect what it's told to look for, and here there may be nothing left
  telling it to look.
- **Con**: even where it does fire (someone happens to check), lazy
  evaluation only ever reacts *after* the fact — it cannot proactively
  attempt re-authorization *before* Stripe's own authorization expires,
  which ADR-004 already requires ("a defined re-authorization flow ...
  rather than allowing a silent capture failure"). Re-authorization only
  makes sense as a proactive action taken with margin before a hard
  external deadline; a reactive check that might never run cannot
  satisfy an already-binding requirement to act *before* that deadline.

**Verdict: inadequate as the sole mechanism.** Not because lazy
evaluation is wrong in general — it remains the right default whenever
a guaranteed future command exists — but because this specific failure
mode (total silence) has no such command to hook.

### Option B — Webhook-driven evaluation

React only to Stripe's own webhooks (e.g. `payment_intent.canceled` when
Stripe's authorization naturally expires) rather than tracking RowBuddy's
own transfer-window deadline at all.

- **Pro**: no scheduler, no polling — purely reactive to an external
  system that already tracks its own expiry.
- **Con (fatal)**: Stripe has no concept of RowBuddy's own transfer
  window (the provisional 24-hour MVP default in §1). Stripe's clock is
  the ~5–7 day authorization-validity ceiling — a completely different,
  much longer deadline. A webhook telling us the authorization expired
  arrives *after* it's already too late to re-authorize; by construction,
  webhook-driven-only evaluation can never fire early enough to satisfy
  ADR-004's re-authorization requirement, because Stripe doesn't know
  about — and has no reason to warn us ahead of — a business-level
  deadline it never agreed to.
- **Con**: cannot detect "the RowBuddy transfer window expired" at all,
  only "Stripe's authorization expired" — these are different events,
  and only the latter has a corresponding webhook.

**Verdict: inadequate as the sole mechanism**, for a different reason
than Option A — it isn't a silence problem here, it's that the external
system's clock isn't the clock we need.

### Option C — Scheduled evaluation only

A periodic sweep, independent of any request or webhook, checks every
`Issued` transfer against its own deadlines.

- **Pro**: the only option that can proactively detect silence *and* act
  with margin before Stripe's own expiry, satisfying ADR-004 directly.
- **Con**: genuinely new infrastructure and a new failure mode for this
  codebase (a missed or delayed sweep tick now has real consequences —
  a re-authorization window quietly closing) — the concern the review
  flagged as this phase's top operational risk.
- **Con**: evaluates every `Issued` transfer on a timer even though most
  will actually be resolved by an explicit confirmation long before their
  deadline — pure waste relative to a lazy check that would have caught
  the same resolution "for free" at confirmation time.

### Option D — Hybrid: lazy evaluation wherever a real touch already exists, scheduled sweep as the only mechanism for silence

Evaluate expiry lazily whenever a `Transfer` is genuinely touched by
something else (a confirmation attempt, a status-check read) — cheap,
consistent with this codebase's established default, and it resolves the
common case (someone actually shows up) without waiting for a sweep tick
at all. Reserve the scheduled sweep *only* for what lazy evaluation
structurally cannot cover: detecting that a transfer was never touched
at all, and acting on the authorization-expiry deadline with proactive
margin regardless of whether anyone ever looks.

- **Pro**: combines the cheapness and established-pattern consistency of
  lazy evaluation for the common case with the one capability only a
  scheduler has (proactive action under silence) for the uncommon one —
  the sweep only ever has to do real work for transfers genuinely at
  risk of going unconfirmed, not every transfer in flight.
  Every other check the sweep would perform, a lazy read-time evaluation
  may have already resolved.
- **Con**: two code paths (lazy check, scheduled sweep) both capable of
  transitioning the same `Transfer`, which must be made mutually safe
  under the same row-lock discipline (§3) — more moving parts than either
  pure option alone.

**Decision: Option D.** Options A and B are not merely less elegant here
— they are each structurally incapable of satisfying ADR-004's
already-binding re-authorization requirement, for different reasons
(A: no guaranteed trigger under silence; B: reacts to the wrong clock).
Option C alone works but does more unnecessary polling than needed.
Option D is Option C's necessary core (the sweep is what actually makes
proactive re-authorization and silent-expiry detection possible),
narrowed by also evaluating lazily wherever a real touch already gives
the information for free — reducing how much work the sweep needs to do
without reducing its coverage.

## Decision

### 1. The transfer window lives entirely in `packages/Transfers`, never in `Auction`

`Transfer.expiresAt` is computed by `packages/Transfers` itself from
`issuedAt` (the moment `PaymentAuthorized` was consumed) plus a duration
resolved from a new, swappable policy:

```php
namespace RowBuddy\Transfers\Contracts;

interface TransferWindowPolicy
{
    public function durationInSecondsFor(string $auctionId): int;
}
```

mirroring `AuctionDurationPolicy`'s exact shape (ADR-013 §1) — deliberately
accepting `$auctionId` even though the MVP implementation ignores it, so
a future policy deriving the window from queue/event metadata can replace
the binding without touching the aggregate. `Auction` gains **no**
`transferWindow` field — this preserves the "zero further changes to
Auctions" posture ADR-014 already established, extended to this ADR by
explicit instruction.

**Provisional MVP default: 24 hours.** This is a placeholder configuration
value, not a permanent domain invariant — the same posture ADR-013 took
for the 30-minute auction-duration default. `TransferInitiationService`
must validate that `issuedAt + windowDuration` does not exceed the
underlying `PaymentIntent`'s authorization validity ceiling (ADR-004);
if a future window/duration policy could ever produce a value violating
that ceiling, that validation is this service's job, not `Transfer`'s
own — the aggregate only ever receives an already-resolved,
already-valid `expiresAt`, the same discipline `Auction::open()` uses for
`closesAt`.

### 2. Re-authorization is attempted once, before the window's own expiry, not before Stripe's

Whichever call site of `TransferExpiryEvaluator` (§3) detects that an
`Issued` transfer's underlying authorization is approaching Stripe's own
expiry window attempts re-authorization through Payments (a new
capability ADR-019 must expose) **before** expiring/cancelling the
transfer for running out its own window. If re-authorization fails
(e.g., the card was cancelled, issuer declines), the transfer is treated
exactly like an unresolved default (§4) — cancelled, no capture, no
charge.

### 3. Hybrid evaluation: lazy on every real touch, scheduled sweep for silence (per "Alternatives considered" above)

A single `TransferExpiryEvaluator` (application-layer, mirroring
`AuctionClosingEvaluator`'s shape) encodes the actual check — "is this
transfer past `expiresAt` and not yet `Confirmed`? Is it nearing Stripe's
authorization expiry and not yet `Confirmed`?" — and is invoked from
**two** call sites sharing the same logic:

- **Lazily**, at the start of any command that already touches a
  specific `Transfer` (a confirmation attempt, a future status-check
  read) — exactly the established pattern, catching the common case
  (someone shows up) for free, before that command's own logic proceeds.
- **By a scheduled sweep** — the first Horizon-scheduled job in this
  codebase — iterating every still-`Issued` transfer, existing
  specifically to catch the case lazy evaluation structurally cannot:
  total silence. This is genuinely new infrastructure, not a stopgap;
  Option D above is the reasoned conclusion, not a fallback.

Both call sites lock the `Transfer` row for the duration of evaluation
(`findByIdForUpdate()`, mirroring `Auction`'s and `Bid`'s established
row-lock pattern) so a scheduler tick and a concurrent confirmation
attempt — or two overlapping scheduler ticks — can never both act on the
same transfer. This is the first time this codebase's lock discipline
has to defend a *scheduled* writer against a *request-triggered* one,
rather than two concurrent requests.

On evaluation (from either call site): if nearing Stripe's authorization
expiry and not yet confirmed → attempt re-authorization (§2); if past
`expiresAt` and not yet `Confirmed` → transition to `Expired` and cancel
the underlying authorization via `PaymentCaptureGateway` (ADR-019).

### 4. Buyer no-show and seller default: symmetric, no-fault cancellation (provisional)

**Both scenarios cancel the authorization identically — no capture, no
charge to the buyer, regardless of which party is actually at fault.**
Neither `claude-mvp-analysis.md` nor any other source document resolves
whether a no-show buyer should forfeit anything or a defaulting seller
should face any consequence beyond the transfer simply not completing;
inventing an asymmetric forfeiture/penalty scheme here would be a real
consumer-protection and legal decision this ADR is not positioned to
make. The symmetric, no-fault default is the conservative, defensible
MVP choice: nobody is charged for a transfer that didn't happen. Any
fault-based consequence (e.g., a trust-and-safety flag on a repeatedly
defaulting seller) is explicitly deferred — likely Phase 8
(Administration & Fraud/Risk) territory, not this phase's job to design.

**This point is flagged for explicit product sign-off** — it is a
business/legal judgment presented as a proposed default, not a
resolved architectural fact the way §1–§3 are.

## Consequences

- `packages/Transfers` gains `TransferWindowPolicy`/a fixed default
  implementation, mirroring `AuctionDurationPolicy`/`FixedAuctionDurationPolicy`'s
  exact shape and MVP-configuration posture.
- `Auction` gains no new field, event, or state — the transfer window is
  entirely a `Transfers`-owned computation.
- `packages/Transfers` gains a single `TransferExpiryEvaluator`, called
  from two sites (a lazy read/command-time check, and the scheduled
  sweep) — not two independent implementations of the same logic.
- This is the first Horizon-scheduled job in the codebase, justified
  above against lazy-only and webhook-only alternatives, both of which
  are structurally incapable of satisfying ADR-004's re-authorization
  requirement. Its lock discipline must be proven the same way ADR-012's
  real-Postgres concurrency test proved the bid-placement lock — a
  scheduled-writer-vs-request-writer race test is expected in whichever
  sprint implements this, in addition to a lazy-vs-scheduled race test
  (both call sites can race each other, not just two scheduler ticks).
- `PaymentCaptureGateway` (ADR-019) must expose both a re-authorization
  operation and a cancellation operation, not capture alone.
- The no-fault buyer-no-show/seller-default default is provisional and
  explicitly awaiting your confirmation — if you want an asymmetric or
  fault-based consequence instead, this section needs to change before
  Sprint 6 (the sprint that implements it) begins.

## References

- ADR-004 (escrow authorize-then-capture; the auth-validity ceiling and
  the requirement for a defined re-authorization flow)
- ADR-013 (`AuctionDurationPolicy` precedent this ADR mirrors)
- ADR-014 (no further changes to `Auction`, extended to this ADR)
- ADR-017 (the `Transfer` aggregate whose `expiresAt` this ADR sources)
- ADR-019 (the `PaymentCaptureGateway` operations this ADR's scheduler
  calls)
- `docs/product/claude-mvp-analysis.md` §2 items 7–8, §10.2
- Phase 5 architecture review (this conversation, §11, §17 items 3–4)
