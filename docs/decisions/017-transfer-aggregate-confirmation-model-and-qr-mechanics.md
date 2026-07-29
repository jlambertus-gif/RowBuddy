# ADR 017: Transfer Aggregate, Two-Sided Confirmation Model, and QR Mechanics

## Status

Accepted — 2026-10-23.

## Context

Phase 5 introduces `packages/Transfers`, the first bounded context whose
entire job is to react to another module's domain event
(`PaymentAuthorized`, per the Phase 5 architecture review §5) and
coordinate a real-world, in-person event (the handoff) between two
parties before triggering money movement. Nothing about the confirmation
model, the aggregate's shape, or the QR mechanism has been decided
anywhere — `docs/product/user-flows.md`'s wording ("Seller: transfer
position using a temporary QR code" / "Buyer: present temporary QR
code") is ambiguous about who generates and who validates the code.
`docs/product/claude-mvp-analysis.md` §3.1 is more specific about the
fraud-mitigation intent: "mandatory transfer confirmation with
independent geolocation from both parties" — implying confirmation is
not a single scan, but two independent acts.

This ADR resolves the aggregate's shape, the confirmation model, and the
QR issuance/validation direction — the geolocation cross-check mechanism
itself is deferred to ADR-020, and the capture trigger this aggregate
eventually fires is deferred to ADR-019, so this ADR can be reviewed on
its own terms.

## Decision

### 1. `packages/Transfers`, a new bounded context

A new package, following the exact shape established by every prior
context (`src/{Application,Contracts,Events,Exceptions,ValueObjects,
Infrastructure}`, its own `DomainEventPublisher` copy, its own
`TransferRepository`). No existing package is modified by this ADR.

### 2. The `Transfer` aggregate

```php
final class Transfer
{
    private function __construct(
        public readonly string $id,
        public readonly string $auctionId,
        public readonly string $winningBidId,
        public readonly string $sellerId,
        public readonly string $buyerId,
        public readonly string $qrTokenHash,
        public readonly DateTimeImmutable $issuedAt,
        public readonly DateTimeImmutable $expiresAt,
        private TransferStatus $status,
        private ?DateTimeImmutable $sellerConfirmedAt = null,
        private ?GeoPoint $sellerConfirmedAt_geo = null,
        private ?DateTimeImmutable $buyerConfirmedAt = null,
        private ?GeoPoint $buyerConfirmedAt_geo = null,
    ) {}
}
```

(Naming/field layout above is illustrative, not final — Sprint 1
finalizes exact property names.) `expiresAt` arrives from an externally
resolved policy value the same way `Auction::open()` receives `closesAt`
(ADR-018 owns that policy). The plaintext QR token is **never stored** —
only its hash, mirroring how passwords are never stored in plaintext
elsewhere in this stack. The plaintext value is returned exactly once, at
issuance, to whichever caller requested it.

### 3. States

```
Issued → Confirmed
Issued → Expired
Issued → Cancelled
```

`Confirmed`, `Expired`, and `Cancelled` are all terminal — mirroring
`Bid`'s "placed once, never transitions again" shape more than
`Auction`'s multi-step machine, since a `Transfer` has exactly one
real decision point (did both sides confirm before the window closed).

### 4. Two-sided confirmation, not a single scan

Per mvp-analysis §3.1's explicit "independent geolocation from both
parties," confirmation requires **both** the seller and the buyer to
independently confirm — neither confirmation alone is sufficient to
reach `Confirmed`:

```php
public function confirmBySeller(DateTimeImmutable $confirmedAt, GeoPoint $geo): void;
public function confirmByBuyer(DateTimeImmutable $confirmedAt, GeoPoint $geo): void;
```

Each method records that party's confirmation (timestamp + the raw
`GeoPoint` they submitted) and raises a one-sided event
(`TransferSellerConfirmed` / `TransferBuyerConfirmed`). Only when the
**second** side's confirmation lands does the aggregate transition to
`Confirmed` and raise `TransferConfirmed` — the actual capture trigger
(ADR-019). Calling either method a second time for the same party, or
after the transfer has already left `Issued`, is an illegal-transition
error, the same guard shape used throughout `Auction`.

**The aggregate does not itself enforce the geofence cross-check.**
Whether a submitted `GeoPoint` is actually within the queue's geofence is
decided by the calling application service *before* it calls
`confirmBySeller()`/`confirmByBuyer()` at all (ADR-020) — mirroring
`BidService`'s pattern (validation lives in the service; a rejected
attempt never reaches the aggregate) rather than `PaymentIntent`'s
pattern (the aggregate itself guards a simple value comparison). A
confirmation attempt outside the geofence is rejected by the service and
never becomes a recorded confirmation.

### 5. QR issuance and validation direction

**The system issues the QR to the buyer, at the moment a `Transfer` is
created** (i.e., right after `PaymentAuthorized`) — it is the buyer's
proof of entitlement to claim the position, the same conceptual role as
an event ticket. The buyer presents it (displays the code) at the
handoff; **the seller scans or enters it**, and a successful, matching
validation is a precondition for `confirmBySeller()` being callable at
all (a wrong/expired/already-used code never reaches the aggregate,
mirroring §4's geofence-gate pattern). This resolves `user-flows.md`'s
ambiguous phrasing in the direction the fraud-mitigation intent implies:
the seller is verifying *the buyer*, not the reverse — the buyer has
nothing of the seller's to validate.

Rationale for rejecting the alternative (seller generates the code): the
seller already has an established, verified claim to the position
(`PresenceSession`/confidence tier, Phase 2); the buyer is the unverified
party at the handoff moment, so the code exists to let the seller confirm
*them*.

### 6. One transfer per auction, no re-transfer

A `Transfer` is created at most once per `auctionId` (enforced the same
way `PaymentIntentRepository::findByAuctionId()` guards idempotent
`PaymentIntent` creation) — consistent with CLAUDE.md's "a transferred
position cannot be transferred again in the MVP." No code path in this
package ever creates a second `Transfer` for the same auction.

## Consequences

- `packages/Transfers` is a new package with zero dependencies on
  `packages/Auctions`/`Bids`/`QueuePresence` internals — it reads only
  `PaymentAuthorized`'s facts (`paymentIntentId`, `auctionId`,
  `winningBidId`, `sellerId`, `buyerId`, `amount`), the same
  "consumer owns the port, reacts to the event" shape Payments itself
  used for `AuctionWon`.
- `Auction` gains zero code changes — preserving ADR-014 exactly as this
  review was instructed to.
- The QR's plaintext value must be handed back to whichever caller
  triggers `Transfer` creation and never persisted — the concrete
  transport of that value to the buyer's device is a delivery-layer
  concern (notifications/frontend), explicitly out of scope for this
  domain-only phase, the same posture every prior phase has taken.
- Rejecting a confirmation (wrong QR, outside geofence) never creates a
  partial or failed `Transfer` record of its own — only a successful
  confirmation ever mutates the aggregate, mirroring `Bid`'s
  rejected-attempts-leave-no-trace shape rather than `PaymentIntent`'s
  record-the-failure-too shape. (Unlike a failed payment authorization,
  a mis-scanned QR is not itself a financial event requiring its own
  audit record — though the *attempt* may still be logged at the
  application-service layer if useful for fraud monitoring; that is an
  implementation detail for Sprint 5, not a domain concern.)
- `TransferIssued`, `TransferSellerConfirmed`, `TransferBuyerConfirmed`,
  and `TransferConfirmed` all implement the shared `AuditableAction`
  interface, exactly like every other financial/verification event in
  this codebase — captured by the existing generic audit sink with zero
  new wiring, satisfying CLAUDE.md's "every financial and verification
  action must create an audit record" for the handoff itself.

## References

- `docs/product/user-flows.md` (seller/buyer flow steps)
- `docs/product/claude-mvp-analysis.md` §3.1, §3.2, §4 item 9, §6, §7.4
- ADR-014 (Payments–Auctions lifecycle independence — the precedent this
  ADR extends one hop further)
- ADR-018 (transfer window/expiry — owns `expiresAt`'s source)
- ADR-019 (the capture trigger `TransferConfirmed` fires)
- ADR-020 (geofence cross-check enforcement mechanism)
