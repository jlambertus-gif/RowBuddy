# ADR 023: Stripe Chargeback Precedence — Explicit Architectural and Legal Deferral

## Status

Accepted — 2026-07-29. Records Decision 8 of the Phase 6 product decision
set.

## Context

`claude-mvp-analysis.md`'s Missing Requirements §2 item 11 names this gap
explicitly and leaves it open: "Chargeback/dispute ownership boundary —
needs a defined precedence between a Stripe-issuer dispute and a RowBuddy
dispute." A buyer can file an issuer-level chargeback with their card
network entirely independently of, and regardless of, whatever RowBuddy's
own in-app `Dispute` says — Stripe would deliver this as a
`charge.dispute.created`/`charge.dispute.closed` webhook. Today,
`StripeWebhookProcessor` (Phase 4 Sprint 5, extended Phase 5 Sprint 6)
already records every verified webhook event unconditionally in the
idempotency ledger (`WebhookEventRepository`), regardless of type, but
reacts to exactly one event type (`payment_intent.canceled`, ADR-019 §7)
— it does not react to, interpret, or link any dispute/chargeback event
to anything.

Resolving real precedence — e.g. "does an open Stripe chargeback freeze
or override an in-app dispute's resolution?" — is a legal and business
question, not merely an architectural one: `jurisdiction-requirements.md`
already lists "dispute-resolution requirements" and "refund obligations"
as items requiring per-jurisdiction legal review before launch, and
`claude-mvp-analysis.md` §3.3 separately flags money-transmission
licensing exposure as a live risk this platform's escrow design (ADR-004)
was specifically shaped to minimize, not eliminate. Inventing a
precedence rule here without legal input would mean guessing at a rule
with real financial and regulatory consequences.

## Decision

**Phase 6 implements no chargeback precedence or reconciliation logic of
any kind.** Concretely, none of the following exist anywhere in this
codebase after Phase 6:

- No automatic linkage between a Stripe chargeback and any RowBuddy
  `Dispute`.
- No automatic freezing of a `Dispute` upon a chargeback event.
- No automatic closure of a `Dispute` upon a chargeback event.
- No automatic refund or release triggered by any `charge.dispute.*`
  event.
- No precedence rule of any kind between an issuer-level dispute and an
  in-app `Dispute`.

**Existing behavior is preserved exactly as-is**: `StripeWebhookProcessor`
continues to record every verified webhook event — `charge.dispute.*`
included — in the idempotency ledger, for audit purposes only, exactly
as it already does for every event type it doesn't otherwise react to.
This requires zero new code; the ledger is already unconditional and
generic.

`packages/Disputes` must not interpret or react to `charge.dispute.*`
events during Phase 6 — no listener, no reconciliation service, no
read-side linkage, nothing analogous to
`StripeCancellationReconciliationService` (ADR-019 §7) for this event
family.

**The presence of a recorded `charge.dispute.*` event must never alter a
`Dispute` aggregate's lifecycle or state transitions during Phase 6.**
`Dispute`'s own state machine (ADR-021) is driven exclusively by the
buyer's filing, the seller's response, deadline eligibility, and an
administrator's explicit resolution (ADR-021 §6) — never by anything
read from the webhook ledger. A `charge.dispute.*` event sitting in
`WebhookEventRepository` is inert data as far as `packages/Disputes` is
concerned: nothing queries it, nothing conditions a guard on it, and
nothing short-circuits `Dispute`'s transitions because of it.

This decision is recorded as an **explicit architectural and legal
deferral**, not silence: the gap identified in `claude-mvp-analysis.md`'s
Missing Requirements is real, remains open, and is deliberately not
closed by guesswork here.

## Consequences

- A Stripe-issuer chargeback and a RowBuddy `Dispute` over the same
  transaction can coexist with no code-level awareness of each other —
  an accepted Phase 6 limitation, not an oversight. An admin reviewing a
  case has no automated signal that a chargeback also exists; at most,
  they could separately consult the webhook ledger, which already
  captures the event unconditionally.
- No new `packages/Disputes` or `packages/Payments` code is written for
  this decision — the only "implementation" is the explicit choice not
  to build reconciliation, recorded here so it reads as deliberate scope
  in any future audit rather than a gap nobody noticed.
- `Dispute`'s state machine (ADR-021) has no dependency, guard, or
  read access of any kind on `WebhookEventRepository` or any
  `charge.dispute.*` event — this must hold even though both concepts
  happen to concern the same underlying transaction, precisely because
  Phase 6 keeps them architecturally unconnected.
- **Any future chargeback precedence, reconciliation workflow, or
  issuer-dispute integration belongs to a future phase, and must follow
  legal and product review first** — per `jurisdiction-requirements.md`'s
  own requirement that dispute-resolution and refund obligations receive
  per-jurisdiction legal sign-off before the relevant capability is
  enabled. This is not assigned to a specific numbered phase in
  `docs/roadmap.md` today; it should be scoped when that legal review
  actually happens, not speculatively placed in Phase 7 or 8 now.
- This decision does not modify `StripeWebhookProcessor`,
  `WebhookEventRepository`, or any other Phase 4/5 code — it is a
  negative decision (a documented choice not to build something),
  distinct in kind from every other ADR in this set.

## References

- `claude-mvp-analysis.md` Missing Requirements §2 item 11 (the
  originally identified, still-open gap this ADR formally defers)
- `docs/legal/jurisdiction-requirements.md` (dispute-resolution and
  refund-obligation legal review requirements this deferral is
  conditioned on)
- ADR-004 (escrow authorize-then-capture — the money-transmission
  licensing exposure this platform's design already minimizes but does
  not eliminate)
- ADR-019 §7 (`StripeCancellationReconciliationService` — the one
  existing, narrow precedent for reacting to a Stripe webhook type,
  explicitly not extended to `charge.dispute.*` by this decision)
- ADR-021 (Disputes filing eligibility, deadlines, and lifecycle — the
  in-app `Dispute` this decision keeps entirely independent of Stripe's
  own chargeback process)
- Phase 6 architecture review (this conversation, §7, §8, §14)
