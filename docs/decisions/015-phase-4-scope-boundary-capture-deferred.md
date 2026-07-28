# ADR 015: Phase 4 Scope Boundary — Authorization, Webhooks, and Payout Preparation Only; Capture Deferred to Transfers

## Status

Accepted — 2026-10-06.

## Context

ADR-004 requires capture to occur "only once the transfer is confirmed."
Transfer confirmation is a Transfers (Phase 5) concept — Phase 5 has not
been designed or built. The Phase 4 architecture review flagged this as
its top sequencing risk (Risk #1): without a real transfer-confirmation
signal, the authorize → capture chain cannot be wired end-to-end inside
Phase 4 alone.

Two resolutions were possible: (a) introduce a placeholder or stub
"transfer confirmed" event/endpoint inside Phase 4 solely to exercise the
capture call, to be replaced by Transfers' real design later; or (b) draw
Phase 4's scope boundary before the capture trigger, so that everything
Phase 4 delivers — authorization, webhook handling, payout preparation,
and the financial domain logic itself — is genuinely complete and
correct on its own terms, with capture's real trigger built as part of
Transfers' own Phase 5 work. José chose (b) explicitly, rejecting (a): a
placeholder built only to make Phase 4 feel end-to-end would misrepresent
what has actually been validated, and would likely need to be discarded
or reconciled against Transfers' real design later — throwaway work
rather than genuine progress.

**This is not the `LiveProximityChecker` precedent, and this ADR does not
rely on it.** An initial draft of this ADR analogized deferring capture's
*caller* to how `LiveProximityChecker` (ADR-011, Phase 3 Sprint 4) was
"built complete and fully tested with no caller yet," with its real
caller arriving in Sprint 5 of the *same* phase. José rejected that
analogy: `LiveProximityChecker` represented a fully defined Phase 3
policy whose caller was already known and simply arrived later in the
same phase's own sprint sequence. Capture is different in kind — it
depends on a bounded context that does not exist yet (Transfers) and on
Phase 5 semantics that are themselves still undefined (what the
transfer-confirmation contract even looks like). Building capture's
*transition*, not just its caller, ahead of that contract risks modeling
the wrong thing. Phase 4 therefore does not implement the
`authorized → captured` transition at all, in any form.

## Decision

1. **Phase 4's implementation scope is**: `PaymentIntent` authorization
   (destination charge, manual capture, `application_fee_amount`
   computed server-side, triggered by `AuctionWon` per ADR-014), Stripe
   webhook receipt/signature verification/idempotent processing for
   authorization-related events, and payout preparation (seller Connect
   onboarding, the payout-eligibility/KYC gate, and the net-payout
   calculation — winning bid minus platform fee minus standard
   processing costs). Everything in this list must be complete,
   correct, and provably idempotent under webhook replay by the time
   Phase 4 closes.

2. **Capture — the `authorized → captured` transition and everything
   downstream of it (`held`, `released_to_seller`,
   `refunded_to_buyer`)** — is explicitly out of scope for Phase 4
   *production wiring*. Triggering it is Transfers' (Phase 5's)
   responsibility, once a real transfer-confirmation domain event exists.

3. **No placeholder, stub, or simulated "transfer confirmed" trigger —
   event, endpoint, admin action, or otherwise — is introduced in Phase 4
   to demonstrate or test the capture path end-to-end.** If a Phase 4
   test needs to exercise the Stripe capture API call directly (e.g. to
   prove the Stripe client wrapper itself works against Stripe test
   mode), that is a narrowly-scoped infrastructure-level test invoking
   the capture call directly — never a fabricated domain event standing
   in for Transfers.

4. **Phase 4 may document `Captured` and later financial states
   (`held`, `released_to_seller`, `refunded_to_buyer`) as part of the
   expected future lifecycle — in ADRs, comments, and diagrams — but must
   not implement their transitions.** No code exists for them yet, in
   any form.

5. **The Phase 4 `PaymentIntent` implementation must contain only the
   states and transitions actually exercised by the approved Phase 4
   scope** (decision 1). It is not a complete rendering of §7.3's full
   lifecycle with some transitions merely uncalled — it is a smaller,
   genuinely complete aggregate for exactly what Phase 4 does:
   authorization and whatever cancellation/failure paths authorization
   itself requires (e.g. an auction that never wins, or an authorization
   Stripe declines).

6. **No `capture()`, `markCaptured()`, `transferConfirmed()`, or
   placeholder/stand-in equivalent method may be added to `PaymentIntent`
   (or anywhere else in `packages/Payments`) during Phase 4.** This
   supersedes the earlier draft's suggestion that such a method could
   exist "fully tested, with no real caller yet" — rejected per the
   Context section above, since capture depends on a bounded context and
   a contract that don't exist yet, unlike `LiveProximityChecker`'s
   already-known, same-phase caller.

7. **Phase 5 will define the Transfers-to-Payments contract** (the real
   transfer-confirmation domain event and whatever port Payments exposes
   to receive it) **and will extend `PaymentIntent`'s lifecycle through a
   new ADR at that time, if the contract's shape requires it.** Phase 4
   does not anticipate or pre-design that contract.

8. **"Payout preparation" means exactly**: Stripe Connect account
   linkage, onboarding/KYC eligibility information, payout-readiness
   validation, and server-side fee and expected-settlement calculations.
   **It does not mean capture, seller transfer, payout release, or
   settlement** — none of those are implemented, simulated, or
   scaffolded in Phase 4.

## Consequences

- The Phase 4 sprint breakdown from the architecture review is revised:
  the sprint previously described as "capture wiring, shaped by the
  Sprint 0 decision" is removed from Phase 4 entirely — not merely
  deferred within it — and becomes part of Transfers' (Phase 5's) own
  sprint plan instead.
- The Phase 4 `PaymentIntent` aggregate is smaller than §7.3's full
  lifecycle sketch — it models authorization and its own
  success/failure/cancellation outcomes only. `captured`, `held`,
  `released_to_seller`, and `refunded_to_buyer` do not exist in code
  anywhere in `packages/Payments` at the end of Phase 4 — not as unused
  methods, not as enum cases with no transition into them, not as
  documented-but-stubbed states. They exist only in prose (this ADR,
  ADR-004, future architecture reviews) until Phase 5 defines the
  contract that justifies adding them for real.
- `docs/roadmap.md`'s Phase 4 exit criterion ("a winning bid can be
  authorized, captured on transfer confirmation, and paid out to a
  verified seller... entirely in Stripe test mode") **overstates what
  Phase 4 alone delivers** and must be revised to describe authorization,
  idempotent webhook handling, and payout preparation only — not
  capture or payout completion. This revision is **not made by this
  ADR**; per explicit instruction it is deferred to a later step, not
  performed now.
- Transfers (Phase 5) inherits an explicit, named responsibility: define
  the real transfer-confirmation domain event and the Transfers-to-
  Payments contract, and extend `PaymentIntent`'s lifecycle through its
  own new ADR if that contract's shape requires it. Phase 4 does not
  pre-design or anticipate this contract in any way.
- No throwaway, simulated, or "fully tested but uncalled" transition code
  is produced in Phase 4 for Transfers to later discard, reconcile, or
  extend around.

## References

- ADR-004 (escrow authorize-then-capture)
- ADR-014 (Payments–Auctions lifecycle independence)
- Phase 4 architecture review (this conversation, §8 Risk #1)
- `docs/roadmap.md` Phase 4 and Phase 5 sections
