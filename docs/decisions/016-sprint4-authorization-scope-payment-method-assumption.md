# ADR 016: Sprint 4 Authorization Scope — Assumed Payment Method and the Separate Charges/Transfers Model

## Status

Accepted — 2026-10-15.

## Context

Sprint 4 wires the real trigger for authorization: when `AuctionWon` fires,
Payments must call Stripe to authorize the buyer's total (winning bid +
platform fee). Two questions surfaced while designing this sprint that
need resolving before implementation, per the same discipline ADR-009
through ADR-015 already established.

**1. Real Stripe authorization requires a payment method for the buyer** —
a card token / Stripe `PaymentMethod` id, or a saved default on a Stripe
`Customer`. Nowhere in RowBuddy does a buyer currently attach one: there
is no SetupIntent/Stripe Elements flow, no saved-card concept on `User`,
and `packages/Bids` never captures one at bid time. This is a genuine gap
in Phase 4's own path — distinct from ADR-015's Transfers-related
deferral, which concerned a bounded context that does not exist yet.
This gap concerns a product capability that could in principle be built
in Phase 4, but has not been designed or scheduled by any prior ADR.

**2. ADR-006 left the exact Stripe Connect charge type open** between two
allowed models: a destination charge with `application_fee_amount`
(requiring the seller's Connect account id at charge-creation time), or
"an equivalent separate-charges-and-transfers model" (charging the buyer
on the platform's own account first; transferring the seller's net share
separately, later). A seller may not have completed Stripe Connect
onboarding (Sprint 3) by the time their auction is won — nothing in this
codebase requires onboarding before an auction can be created or won.
Requiring the seller's Connect account id at authorization time would
make buyer-side authorization block on a step (seller onboarding) that
has no defined timing relationship to auction creation or bid placement.

## Decision

### 1. Buyer payment-method acquisition is an explicit, deferred, out-of-scope product capability

Sprint 4 assumes a valid Stripe `PaymentMethod` id already exists for the
buyer by the time `AuctionWon` fires, and takes it as an explicit,
externally-supplied input — the same way `Auction::open()` takes an
already-resolved `closesAt` rather than deriving it, and the same way
`SellerOnboardingService` (Sprint 3) has no HTTP caller yet. **This is
not an implicit assumption buried in the code** — it is a named,
documented gap: how and when a buyer attaches a payment method (Stripe
Elements/PaymentElement, a SetupIntent flow, a requirement that a bid
cannot be placed without a card on file, or some other mechanism) is a
separate product capability, not designed or scheduled by this ADR, and
is likely to touch `packages/Bids` when it is eventually addressed.

Concretely: the Payments-owned application service that handles
`AuctionWon`'s facts accepts `stripePaymentMethodId: string` as a plain
parameter. No code in this sprint acquires, stores, or validates that a
payment method actually belongs to the buyer, is chargeable, or was
legitimately collected — that trust boundary does not exist yet and is
explicitly out of scope for Phase 4.

### 2. Separate Charges and Transfers, not a destination charge

Per ADR-006's allowance for "an equivalent separate-charges-and-transfers
model," Sprint 4 authorizes the buyer's total (winning bid + platform
fee) as a plain manual-capture Stripe PaymentIntent on RowBuddy's own
platform Stripe account — **no `transfer_data[destination]`, and no
dependency on `SellerPayoutAccount` or the seller's Connect onboarding
status at authorization time.** The eventual transfer of the seller's net
proceeds to their Connect account is Phase 5+ payout-execution work,
already out of scope for Phase 4 per ADR-015, and is not designed by this
ADR either.

This decouples "can a winning bid's payment be authorized" from "has the
seller finished Stripe Connect onboarding" — realistic, since no rule
anywhere requires onboarding before an auction can be created or won, and
it avoids introducing an unwanted coupling between Sprint 3's and Sprint
4's concerns.

## Consequences

- `packages/Payments` gains a `PaymentAuthorizationGateway` port (a real
  Stripe adapter creating a manual-capture PaymentIntent, using our own
  domain `PaymentIntent`'s id as the Stripe idempotency key) and an
  application service consuming `AuctionWon`'s facts (`auctionId`,
  `winningBidId`, `sellerId`, `buyerId`, `winningAmount`) plus the assumed
  `stripePaymentMethodId`.
- `ConnectAccountGateway`/`SellerPayoutAccount` (Sprint 3) are not read
  anywhere in Sprint 4's authorization path — they remain exactly what
  Sprint 3 built, unused by authorization, reserved for the eventual
  Phase 5+ payout/transfer step.
- No SetupIntent, Stripe Elements/PaymentElement integration, saved-card
  model, or Bids-level "card required to bid" rule is introduced in
  Phase 4. Whoever picks up buyer payment-method acquisition next must
  also decide where `stripePaymentMethodId` is actually supplied from
  before Sprint 4's application service has a real, non-test caller —
  until then, it has no caller beyond its own tests, the same shape as
  every prior Phase 4 sprint's application service.
- This ADR does not amend ADR-006's or ADR-004's binding status — it
  exercises the flexibility ADR-006 already reserved ("or an equivalent
  separate-charges-and-transfers model"), rather than changing either
  decision.

## References

- ADR-004 (escrow authorize-then-capture)
- ADR-006 (fee model — buyer-side percentage; reserves the
  separate-charges-and-transfers alternative exercised here)
- ADR-014 (Payments–Auctions lifecycle independence)
- ADR-015 (Phase 4 scope boundary — capture deferred to Transfers)
