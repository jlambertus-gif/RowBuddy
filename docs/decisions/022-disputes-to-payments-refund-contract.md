# ADR 022: Disputes-to-Payments Refund Contract and PaymentIntent Lifecycle Extension

## Status

Accepted — 2026-07-29. Records Decisions 5–6 of the Phase 6 product
decision set.

## Context

The Phase 6 architecture review (§6/§7) identified that resolving a
`Dispute` in the buyer's favor — fully or partially — requires a Payments
capability that does not exist today: `PaymentIntent` currently has no
transition reachable from `Captured` (`capture()`/`failCapture()`/
`cancelAuthorization()` are all guarded to `Authorized` only, per
ADR-019 §3). A refund of an already-`Captured` charge is structurally
new, the same kind of gap ADR-019 itself filled when Transfers first
needed capture.

`claude-mvp-analysis.md` §7.5 names three resolution outcomes
(`resolved_buyer`, `resolved_seller`, `resolved_split`); the architecture
review (§6) observed that Stripe's `Refund` API supports a partial
`amount` natively, making "split" a variant of the same underlying
operation as a full refund, not a separate mechanism.

## Decision

### 1. `PaymentIntent` gains one new transition: `refund(Money $amount, ...)`, guarded to `Captured`

Mirroring `capture()`/`cancelAuthorization()`'s exact shape (guard the
current status, apply the transition, raise a domain event), `refund()`
is reachable only from `Captured`. Attempting it against any other status
is an illegal-transition error, the same discipline every other
`PaymentIntent` transition already uses — not a silent no-op, so a caller
cannot accidentally refund a `PaymentIntent` that was never captured or
was already refunded.

### 2. Domain invariants enforced on every refund, full or partial

- `amount > 0` — a zero or negative refund is not a valid operation.
- `amount <= capturedAmount` — a refund can never exceed what was
  actually captured. This is the one invariant that matters most and is
  enforced unconditionally, regardless of caller.
- `amount.currency` must equal the captured `PaymentIntent`'s currency —
  mirroring the existing currency-match guard `PaymentIntent::authorize()`
  already enforces against the transaction value limit.
- Refunded value can never exceed the captured total across the
  `PaymentIntent`'s lifetime — for MVP, this reduces to "at most one
  `refund()` call ever succeeds" (see §3), so this invariant is satisfied
  by construction rather than needing a running-total check, but it is
  named explicitly here so a future multi-refund extension (if ever
  needed) is built against a stated invariant, not silently overlooked.

### 3. "Split" is a `Dispute`-resolution-outcome concept, not a distinct `PaymentIntent` lifecycle state

**Architectural clarification, explicitly settled by this decision:** a
partial refund is simply a `refund()` call with an amount smaller than
the full captured total — there is no separate `PartiallyRefunded`
`PaymentIntent` status distinct from `Refunded`. `PaymentIntent` gains
exactly **one** new status (`Refunded`), regardless of whether the
refunded amount equals the full captured amount or less. Whether a given
resolution was a "full refund to buyer" or a "split" is a fact recorded
by `Dispute`'s own resolution outcome/reason, never by `PaymentIntent`
itself — `PaymentIntent` only ever knows "how much was refunded," not
"why," the same separation `PaymentIntent::cancelAuthorization()`
already draws between its own state and the free-text `reason` it
carries.

This deliberately avoids fragmenting `PaymentIntent`'s lifecycle further
than strictly necessary — extending it with `Refunded`+`PartiallyRefunded`
as two parallel terminal states would duplicate every guard, transition,
and test this ADR's single `Refunded` state already covers, for a
distinction that only ever matters one layer up, in `Dispute`.

### 4. No predefined percentages or ratios

The refund amount is an arbitrary admin-specified `Money` value, bounded
only by the invariants in §2 — never restricted to a fixed set of
fractions (e.g. 50/50). The judgment of *how much* to refund in a given
case stays with the human reviewing the dispute (consistent with the
expected direction of Decision 7, manual-only administration), not
encoded as a menu of allowed ratios the domain model would otherwise need
to enumerate and validate against.

### 5. The domain never distinguishes bid amount from platform fee — no fee-specific behavior anywhere

**Architectural clarification, explicitly settled by this decision:** the
refund contract from §1–§4 is the complete contract. `capturedAmount`
(bid plus fee, per ADR-006/ADR-019) is the **only** financial ceiling the
domain enforces:

```
0 < refundAmount <= capturedAmount
```

The domain has no concept of a fee-specific refund. Concretely, none of
the following exist anywhere in `packages/Payments` or `packages/Disputes`:

- No `refundFee()` or `refundBidOnly()` methods.
- No `feeRefundable` flag, property, or policy.
- No breakdown of `capturedAmount` into its bid/fee components anywhere
  in the refund path — `PaymentIntent::refund()` accepts one `Money`
  amount and validates it against one ceiling.

Whether an admin's chosen `refundAmount` happens to include part or all
of the platform fee is an **operational/policy decision made by the
human choosing the amount**, entirely outside the domain model — the
same way §4 already keeps "how much to refund" a human judgment call
rather than a system-enforced ratio. Introducing fee-specific domain
behavior here would silently contradict §4's own principle by carving
out exactly one privileged, system-enforced distinction (fee vs. bid)
within an amount that is otherwise deliberately unstructured.

## Consequences

- `packages/Payments` gains one new `PaymentIntentStatus` case
  (`Refunded`) and one new transition method on `PaymentIntent`
  (`refund(Money $amount, string $reason, ClockInterface $clock)`),
  guarded to `Captured` exactly like `capture()`/`cancelAuthorization()`
  are guarded to `Authorized`.
- A new domain event (e.g. `PaymentRefunded`), implementing
  `AuditableAction` like every other financial event in this codebase,
  carrying the refunded `Money` amount and the `Dispute`'s reason —
  captured by the existing generic audit sink with zero new wiring.
- A new Disputes-owned port (mirroring `PaymentCaptureGateway`'s "consumer
  owns the port" shape exactly) exposing the refund operation to
  `packages/Disputes`, implemented by a new `apps/web` adapter bridging
  to a new Payments-side `DisputeRefundService` (mirroring
  `PaymentCaptureService`'s shape: lock the `PaymentIntent` row, guard
  status, call Stripe, apply the transition, save, publish events only
  after commit).
- No `PartiallyRefunded` status, and no other new `PaymentIntent` status
  beyond `Refunded`, is introduced by this decision.
- `capturedAmount` is never decomposed into bid/fee components anywhere
  in the refund path — no `refundFee()`/`refundBidOnly()` method,
  `feeRefundable` flag, or equivalent exists in `packages/Payments` or
  `packages/Disputes`. Whether a chosen amount implicitly includes the
  fee is an operational/policy matter for whoever operates the admin
  resolution tool, not a domain concept.
- This decision does not address transfer-reversal or clawback-after-
  payout scenarios (Phase 6 architecture review §7/§8) — `refund()` only
  ever acts on a `Captured` charge still on the platform's own Stripe
  balance, consistent with the standing constraint that no payout-
  execution mechanism exists yet.

## References

- ADR-004 (escrow authorize-then-capture — the underlying Stripe charge
  this refund operation acts against)
- ADR-006 (the buyer-side fee model — confirmed by Decision 6 to have no
  domain-level distinction from the bid amount anywhere in the refund
  path)
- ADR-016 (the separate-charges-and-transfers model — why a refund here
  never requires a Stripe transfer-reversal; see Phase 6 architecture
  review §7)
- ADR-019 (the capture contract and `PaymentIntent` lifecycle extension
  pattern this ADR mirrors exactly, one phase later)
- ADR-021 (Disputes filing eligibility and deadlines — the `Dispute`
  aggregate whose resolution outcome drives this contract)
- Phase 6 architecture review (this conversation, §6, §7, §13)
