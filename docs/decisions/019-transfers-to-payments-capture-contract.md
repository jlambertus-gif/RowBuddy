# ADR 019: Transfers-to-Payments Capture Contract and PaymentIntent Lifecycle Extension

## Status

Accepted — 2026-10-23.

## Context

ADR-015 §7 (Phase 4) explicitly anticipated this moment: "Phase 5 will
define the Transfers-to-Payments contract and will extend
`PaymentIntent`'s lifecycle through a new ADR at that time, if the
contract's shape requires it." It is required. `PaymentIntent` currently
has exactly two states (`Authorized`, `Failed`), no transition past
authorization, and its repository (`PaymentIntentRepository`) is
deliberately append-only — `record()` only, no update path, mirroring
`BidRepository`'s immutable-once-recorded shape (ADR-015 §5/§6). Capture
is a real, in-place state transition on an *existing* record, which this
shape cannot express as built.

This ADR also resolves a scope question the Phase 5 architecture review
raised explicitly: does Phase 5 execute seller payout (the actual Stripe
transfer to the seller's Connect account), or does it stop at capture,
leaving the already-built `PayoutPreparationService` (Phase 4, Sprint 6)
as the final word until a dedicated payout-execution phase exists?

## Decision

### 1. `PaymentIntentRepository` gains an update path

`PaymentIntentRepository` changes from `BidRepository`'s pure
append-only shape to `AuctionRepository`'s mutable-aggregate shape
(`save()` via `updateOrCreate`, ADR-013's persistence pattern) — this is
a real, precedented pattern **switch**, not a new invention:
`PaymentIntent` is no longer immutable-after-creation once real
post-authorization transitions exist. `record()` (insert-only) is
replaced by a `save()` method usable for both the initial insert
(Sprint 4's `authorize()`/`declineAuthorization()`) and every later
transition this ADR adds.

### 2. Three new `PaymentIntentStatus` cases — no more

```php
enum PaymentIntentStatus: string
{
    case Authorized = 'authorized';
    case Failed = 'failed';
    case Captured = 'captured';
    case CaptureFailed = 'capture_failed';
    case Cancelled = 'cancelled';
}
```

- **`Captured`**: the transfer was confirmed and Stripe's capture call
  succeeded.
- **`CaptureFailed`**: confirmation happened, but the real Stripe capture
  call itself failed (e.g., the authorization had already expired
  Stripe-side despite ADR-018's re-authorization attempt) — an expected,
  anticipatable business outcome, not an unexpected infrastructure
  failure, mirroring the `Authorized`/`Failed` split ADR-012 §1a already
  established for the original authorization attempt. Only a genuinely
  unexpected Stripe exception (not a decline/expiry-shaped one) should
  propagate uncaught, exactly as `StripePaymentAuthorizationGateway`
  already does for `CardException` versus everything else.
- **`Cancelled`**: the authorization was voided *without* an attempted
  capture — the transfer expired unconfirmed, or a buyer/seller default
  was recorded (ADR-018 §4), or re-authorization failed before any
  capture attempt. This is explicitly **not** the same event as
  `CaptureFailed` — one is "we tried to take the money and couldn't,"
  the other is "we deliberately released the hold without ever trying."

**`Held`, `ReleasedToSeller`, and `RefundedToBuyer` are explicitly not
added.** Per decision §5, seller payout *execution* stays out of Phase 5
— `PayoutPreparationService`'s readiness snapshot remains the final word
this phase produces, matching the same restraint ADR-015 already applied
to Phase 4.

### 3. Three new transition methods on `PaymentIntent`

```php
public function capture(ClockInterface $clock): void; // Authorized -> Captured, raises PaymentCaptured
public function failCapture(string $reason, ClockInterface $clock): void; // Authorized -> CaptureFailed, raises PaymentCaptureFailed
public function cancelAuthorization(string $reason, ClockInterface $clock): void; // Authorized -> Cancelled, raises AuthorizationCancelled
```

All three are guarded to `Authorized` only (the same `guardStatus`-style
check `Auction`'s transition methods use) — calling any of them against
a `PaymentIntent` that is already `Captured`/`CaptureFailed`/`Cancelled`
is an illegal-transition error, not a silent no-op, so a caller cannot
accidentally re-capture or double-cancel by mistake. (`Transfers`' own
idempotency — never calling capture twice for the same confirmed
transfer — is a separate, additional guard at the `Transfers` layer,
not a substitute for this one.)

### 4. `PaymentCaptureGateway`: Transfers-owned, three operations

Per the Phase 5 architecture review's naming ("the Transfers-to-Payments
contract" implies Transfers is the consumer), this port is owned by
`packages/Transfers`, implemented by a new `apps/web` adapter bridging to
Payments' internals — the same "consumer owns the port" shape as
`AuctionGateway` (Bids owning a port into Auctions):

```php
namespace RowBuddy\Transfers\Contracts;

interface PaymentCaptureGateway
{
    public function capture(string $auctionId): CaptureAttempt;

    public function reauthorize(string $auctionId): ReauthorizationAttempt;

    public function cancelAuthorization(string $auctionId, string $reason): void;
}
```

`CaptureAttempt`/`ReauthorizationAttempt` are Transfers-owned value
objects (`{succeeded: bool, failureReason: ?string}`), mirroring
`AuthorizationAttempt`'s shape from Payments' own Sprint 4 — Transfers
never touches a Stripe SDK type or a Payments-internal type directly.

### 5. Seller payout execution stays out of Phase 5

Executing the actual Stripe transfer to the seller's Connect account
(`Held → ReleasedToSeller`) is explicitly deferred past this phase —
`PayoutPreparationService` already computes readiness and the expected
settlement (Phase 4, Sprint 6); a real payout requires the
still-unresolved protection-period-duration product decision
(`claude-mvp-analysis.md` §10.2 item 4) and is enough additional scope to
warrant its own dedicated phase or sprint, authorized separately, rather
than folded silently into "Transfers." This mirrors ADR-015's own
restraint exactly — draw the boundary before the next undesigned
product decision, not through it.

### 6. The exact locked sequence

Mirroring ADR-012's transactional discipline:

```
BEGIN
  1. Lock the PaymentIntent row (PaymentIntentRepository::findByIdForUpdate,
     a new method mirroring AuctionRepository's).
  2. Guard: status must currently be Authorized — anything else is a
     no-op from Transfers' perspective (idempotency), not an error, once
     the caller has already checked its own Transfer-level idempotency.
  3. Call Stripe (capture / reauthorize's confirm call / cancel).
  4. On success: apply the corresponding PaymentIntent transition,
     save(), release events.
  5. On an expected failure (e.g. capture attempted against an
     already-Stripe-expired authorization): apply failCapture() instead,
     save(), release events.
COMMIT
Publish events after commit, exactly as BidService/AuctionWinAuthorizationService
already do.
```

### 7. Stripe webhook reconciliation: secondary and defensive, not primary

`StripeWebhookProcessor` (Phase 4, Sprint 5) deliberately records every
verified event without reacting to any of them, explicitly deferred
"until a concrete need exists." This phase is that need, but only in a
secondary, defensive role — ADR-018 §2's proactive re-authorization sweep
remains the *primary* mechanism for handling approaching expiry.
`payment_intent.canceled` (confirming Stripe already expired an
authorization our own scheduler failed to catch in time — e.g. after
scheduler downtime) is reconciled by calling the **same**
`PaymentIntent::cancelAuthorization()` transition §3 already defines,
guarded the same way: a `PaymentIntent` already `Cancelled` locally
receiving a redundant webhook is a no-op, not an error, the same
idempotency posture as everything else in this ADR. No new
`PaymentIntent` state or transition is introduced solely for webhook
reconciliation — it reuses exactly what §2–§3 already built.

## Consequences

- `PaymentIntentRepository` gains `findByIdForUpdate()` and a `save()`
  method; `record()` is removed in favor of `save()` handling both insert
  and update, the same consolidation `AuctionRepository` already
  demonstrates.
- `PaymentIntent` gains three new states and three new transition
  methods — a real, non-trivial change to already-shipped, already-tested
  domain code, anticipated by ADR-015 §7 but still requiring full
  regression coverage of every existing `PaymentIntentTest` alongside the
  new transitions.
- `packages/Transfers` gains `PaymentCaptureGateway` and its two attempt
  value objects; `apps/web` gains the real adapter bridging to Payments.
- No `Held`/`ReleasedToSeller`/`RefundedToBuyer` state, and no real
  Stripe transfer-to-seller call, exists anywhere after this phase —
  `PayoutPreparationService` remains the final word on payout until a
  separately authorized payout-execution phase.
- Every new `PaymentIntent` event (`PaymentCaptured`,
  `PaymentCaptureFailed`, `AuthorizationCancelled`) implements
  `AuditableAction`, captured by the existing generic audit sink with
  zero new wiring, exactly as every prior financial event has been.

## References

- ADR-004 (escrow authorize-then-capture)
- ADR-012 (transactional/locking discipline this ADR's sequence mirrors)
- ADR-013 (`AuctionRepository::save()`/`findByIdForUpdate()` precedent)
- ADR-015 (§4–§7: the states/transitions this ADR was always going to add,
  and the restraint principle this ADR extends to payout execution)
- ADR-017 (`TransferConfirmed`, the event that triggers `capture()`)
- ADR-018 (the scheduler that calls `reauthorize()`/`cancelAuthorization()`)
- Phase 5 architecture review (this conversation, §5, §7, §12, §18 risk #1)
