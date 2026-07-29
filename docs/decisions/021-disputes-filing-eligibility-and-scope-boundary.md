# ADR 021: Disputes Filing Eligibility, Deadlines, and Scope Boundary Against ADR-018

## Status

Accepted — 2026-07-29. Records Decisions 1, 2, 3, 4, 7, 9, and 10 of the
Phase 6 product decision set (ADR-022 records Decisions 5–6; ADR-023
records Decision 8).

## Context

The Phase 6 architecture review identified a genuine ambiguity: nothing
in `claude-mvp-analysis.md`, `business-rules.md`, or `auction-rules.md`
specifies who may open a dispute or against which `Transfer` state. Left
unresolved, this risks building a second, competing mechanism for
contesting an outcome ADR-018 §3/§4 already resolves automatically and
symmetrically — a `Transfer` that reaches `Expired` or `Cancelled` never
had its `PaymentIntent` move past `Authorized`, so nothing was ever
captured and there is nothing left to dispute financially. A dispute
mechanism that could also target those states would blur a boundary this
codebase has otherwise kept sharp: *no-fault automatic resolution
handles silence; disputes handle disagreement about a completed
transaction.*

## Decision

### 1. Only the buyer may open a dispute

The seller never independently opens a dispute. The seller's only role
in the dispute lifecycle is as **respondent** — submitting counter-
evidence while the dispute is `UnderReview`. This is deliberately
asymmetric, unlike ADR-018 §4's symmetric no-fault cancellation: once a
`Transfer` reaches `Confirmed`, capture has already triggered in the
seller's favor (ADR-019 §6), and no payout-execution mechanism exists yet
(Phase 4/5 completion reports) for a seller to have anything at stake
that a dispute could restore. The seller's realistic complaint —
retaliatory or false claims by a buyer who actually received the position
(the "friendly fraud" risk named in `claude-mvp-analysis.md` §3.1) — is
handled by responding to the buyer's filing, not by an independent
seller-initiated case.

### 2. A dispute may only be opened against a `Transfer` whose status is `Confirmed`

`Issued`, `Expired`, and `Cancelled` remain **exclusively** governed by
ADR-018's automatic no-fault flow and must never enter the dispute
lifecycle. Concretely:

- `Issued`: no capture has occurred (`PaymentIntent` is `Authorized` at
  most) — nothing has been decided yet for a dispute to contest.
- `Expired`/`Cancelled`: `Transfer::expire()`/`cancel()` are only
  reachable from `Issued` (guarded, per `Transfer`'s own state machine);
  a `Transfer` in either state was, by construction, never `Confirmed`,
  so its `PaymentIntent` was cancelled with no capture ever attempted.
  There is no captured money behind either state for a dispute outcome
  (release/refund/split) to act on.
- `Confirmed`: the only state where `PaymentIntent` has actually
  transitioned to `Captured` (ADR-019 §3/§6) — the only state where a
  dispute's resolution outcomes are financially meaningful.

This is a hard eligibility gate, not a default: an attempt to open a
dispute against a `Transfer` in any other status must be rejected before
a `Dispute` is ever created, mirroring the same "a rejected attempt
leaves no trace" discipline `TransferConfirmationService`'s geofence/QR
gates already established.

### 3. A filing deadline exists and is a swappable policy, not an aggregate constant

A dispute must be opened within **7 days (168 hours) of `Transfer.confirmedAt`**
— resolved via a new `DisputeFilingDeadlinePolicy`, mirroring
`TransferWindowPolicy`'s exact shape (a small interface — e.g.
`durationInSecondsFor(string $transferId): int` — with one MVP-fixed
implementation, config-overridable, deliberately accepting a `$transferId`
it currently ignores so a future policy deriving the deadline from
queue/category metadata can replace the binding without touching the
`Dispute` aggregate). `Dispute`'s own filing-eligibility check receives an
already-resolved deadline value the same discipline `Transfer::issue()`
uses for `expiresAt` — the aggregate never computes or knows the policy
itself.

**7 days, not 72 hours, is the MVP default.** This is a provisional
configuration value, not a permanent domain invariant — the same posture
every other MVP threshold in this codebase takes (`TransferWindowPolicy`'s
24-hour default, `AuctionDurationPolicy`'s 30-minute default). Seven days
gives a buyer realistic time to discover and report a genuine problem
with a real-world ticket/position handoff, and is a deliberately safer
default while Phase 6 has no payout-execution mechanism to reason against
(§8 of the architecture review) — a longer filing window costs nothing
operationally yet, since nothing currently pays a seller out regardless
of how quickly a dispute window closes.

**Queue/category-specific filing-deadline policies are explicitly
deferred** — `Queue` carries no attribute today that would drive a
per-category value, and no document establishes a product need for one
yet. `DisputeFilingDeadlinePolicy`'s shape leaves this open for later
without requiring a redesign.

### 4. A seller response deadline exists, is a swappable policy, and never auto-resolves the dispute

The seller has **5 days from `DisputeOpened`** to submit counter-evidence
— resolved via a new `DisputeResponseDeadlinePolicy`, the same swappable
shape as `DisputeFilingDeadlinePolicy` (§3). Five days, shorter than the
7-day filing deadline, since the seller is reacting to an already-filed,
already-evidenced claim rather than discovering a problem from scratch.
**This is a provisional MVP default**, stated explicitly rather than
assumed, the same posture as every other threshold in this ADR and
elsewhere in this codebase.

Critically, **the response deadline does not auto-resolve the dispute,
and seller silence does not imply any outcome** — not refund, not
release, not an admission of fault. Once the deadline passes, the
dispute merely becomes *eligible for admin resolution*; an admin still
chooses the final outcome (release/refund/split/cancel) by weighing
whatever evidence actually exists, which may be buyer-only if the seller
never responded. This deliberately avoids two problems a silence-triggers-
an-outcome design would create: (a) it never has to pick a "correct"
default outcome for non-response — a fairness-sensitive policy call this
decision does not settle; (b) it needs no scheduler. Detecting "the
response window has passed" is not a silent-failure-mode problem the way
ADR-018 §3's transfer-expiry detection was — there is no Stripe-side
deadline racing against it, no financial urgency forcing proactive
detection. The admin review/read model may simply surface
overdue-response cases **lazily**, whenever an admin's own moderation
queue is viewed, the same "the common case gets caught by something that
already happens" reasoning ADR-018 preferred wherever a scheduler wasn't
structurally required.

### 5. `Resolved`/`Closed` is terminal — no reopening, no appeal, for MVP

Once a `Dispute` reaches its resolved/closed terminal state, it is final,
mirroring `Transfer`'s and `PaymentIntent`'s own terminal-state discipline
elsewhere in this codebase. Concretely, none of the following exist in
Phase 6:

- No reopening of a resolved/closed dispute.
- No formal appeal workflow.
- No second review state (e.g. an escalation tier or senior-admin
  override).
- No second filing against the same `Transfer` once its dispute has
  resolved.
- No additional evidence accepted after resolution.

**This is an accepted MVP limitation, not an oversight.** If an
administrator makes an operational mistake in resolving a dispute (e.g.
selects the wrong outcome), the correction happens **outside the domain
model**, as an administrative/support action (a data correction), not as
a modeled business process — the same way this codebase has consistently
deferred fault-based or exception-handling complexity it has no concrete
product basis for yet (mirroring ADR-018 §4's own deferral of an
asymmetric fault-consequence scheme to a future Administration/Fraud &
Risk phase). A formal appeal/reopening workflow — if real dispute volume
ever shows admin error is a frequent enough problem to justify one — is
explicitly deferred to a future Administration/Fraud & Risk phase (Phase
8), not designed here.

### 6. Resolution is manual-only — `Dispute` executes, it never decides

**Architectural clarification, explicitly settled by this decision:**
every dispute resolution requires an explicit administrator action. None
of the following exist anywhere in Phase 6:

- No automatic outcome selection.
- No rules engine.
- No risk scoring.
- No automatic refund approval.
- No automatic seller release.
- No threshold-based automation (dollar amount, dispute count, or
  otherwise).
- No machine-assisted decision making of any kind.

`packages/Disputes` is responsible only for: collecting the case
(filing, per §1–§2), aggregating evidence (read-only, from `Transfer`
and possibly `QueuePresence`, per the architecture review §4/§13),
enforcing lifecycle invariants (eligibility, deadlines, terminality, per
§1–§5), and **executing** whichever outcome an administrator explicitly
chooses — release, refund (via ADR-022's contract), split (via the same
contract, a smaller amount), or cancellation. `Dispute` itself never
computes, scores, or infers an outcome.

This is consistent with, not a change to, everything already frozen: the
response deadline never auto-resolves (§4), evidence never determines an
outcome by itself, and the refund amount is always human-chosen (ADR-022
§4). **Any future automated or rules-based decision-making belongs
exclusively to the future Fraud & Risk bounded context (Phase 8,
`claude-mvp-analysis.md` §4 item 15) and must not be introduced into
Phase 6** — Fraud & Risk judges accounts and patterns over time; Disputes
resolves one case at a time, on a human's explicit say-so.

### 7. No evidence retention/deletion policy is introduced; evidence referenced by a `Dispute` is treated as effectively immutable

Phase 6 introduces **no evidence retention or deletion capability of any
kind**. Concretely:

- No evidence deletion capability, anywhere.
- No retention period defined.
- No automatic evidence expiration.
- No GDPR/CCPA erasure workflow.
- Existing evidence storage (`Transfer`'s `transfer_evidence`,
  QueuePresence's evidence photos) remains exactly as it is today —
  private-by-default, signed-URL-only, purely additive, per ADR-020 and
  Phase 2's established pattern.

**Evidence referenced by a `Dispute` must remain available for the
entire lifetime of that dispute.** Since no deletion mechanism exists
anywhere in this codebase for either `Transfer` or `QueuePresence`
evidence, this is a guarantee Phase 6 can make at zero cost — there is no
new way for evidence to disappear out from under an active case, because
nothing introduces a way for it to disappear at all.

**Architectural clarification, explicitly settled by this decision:**
evidence is treated as **effectively immutable once referenced by a
`Dispute`**. Any future correction to the evidentiary record — a
mistaken submission, new supporting material, a correction to a prior
statement — must be modeled by **appending** new evidence or metadata,
never by modifying or deleting evidence already associated with a
dispute. This mirrors `Transfer::attachEvidence()`'s own append-only
shape (ADR-020 §4) one layer up, at the `Dispute` level.

This remains an explicitly **open, carried-forward item**, not a resolved
one: the broader retention-period/erasure-rights tension
(`claude-mvp-analysis.md` Missing Requirements #13) is unchanged by this
decision. **Any future evidence-deletion capability must first define
its interaction with active disputes, legal retention obligations, audit
requirements, and jurisdiction-specific compliance** before
implementation — this decision only ensures Phase 6 does not make that
future problem harder by introducing a half-built deletion path now.

### 8. A fraudulent-evidence finding is an internal observation on `Dispute`, not an external domain fact

`business-rules.md` rule 17 ("fraudulent evidence may result in
suspension and forfeiture according to platform policy") names a
consequence with no defined mechanism. Phase 6 resolves this narrowly:
**an administrator resolving a dispute may record that submitted
evidence was determined to be fraudulent, and this finding becomes part
of that `Dispute`'s permanent audit record — nothing more.**

**Architectural clarification, explicitly settled by this decision:** a
fraudulent-evidence finding is an **observation recorded on the
`Dispute` aggregate itself, not a domain fact exposed to, or acted on
by, anything outside it.** It is data alongside the resolution reason/
notes an admin already records for ordinary audit purposes (§7's
immutable-evidence discipline applies to it identically) — not a new
domain event, not a flag any other module reads, and not something
`packages/Disputes` publishes for a listener elsewhere to react to. The
finding has:

- **No automatic domain behavior of any kind.**
- No account suspension.
- No forfeiture of funds beyond whatever resolution amount (release/
  refund/split, per ADR-022) was already, separately decided for that
  one case.
- No effect on any *other* dispute, past or future, involving the same
  or any other user.
- No triggered workflow, automated or otherwise.

`Dispute` outcomes remain limited to exactly the four resolution
outcomes already frozen (release/refund/split/cancellation, Decisions
5–6) — a fraudulent-evidence finding does not add a fifth outcome or
modify how the existing four are chosen or executed.

**Any account-level consequence — suspension, fraud scoring, sanctions,
KYC action, or a concrete forfeiture policy — belongs exclusively to the
future Administration/Fraud & Risk bounded context** (`claude-mvp-
analysis.md` §4 items 13/15, Phase 8). If that future context ever wants
to act on a pattern of fraudulent-evidence findings, it would need its
own, separately designed read access to this data — Phase 6 does not
build that access, publish it as an event, or otherwise reach outside
`Dispute` to make the finding available; it stays exactly where §7
already places all of a resolved dispute's evidence and reasoning:
inert, retained, and unread by anything else in this codebase today.

## Consequences

- `Dispute` filing eligibility depends on reading `Transfer`'s current
  status via the read-only cross-context port described in the Phase 6
  architecture review (§4/§13) — `packages/Disputes` gains no dependency
  on `packages/Transfers`' internals.
- No seller-initiated filing path, endpoint, or application-service
  method should exist anywhere in `packages/Disputes`.
- ADR-018 is not reopened or modified by this decision — its no-fault
  mechanism remains the sole resolution path for `Issued`/`Expired`/
  `Cancelled` transfers, unconditionally.
- This scope boundary is binding on every later Phase 6 decision: the
  filing-deadline and response-deadline policies apply only to
  `Confirmed`-transfer disputes, never to the no-fault flow.
- `packages/Disputes` gains `DisputeFilingDeadlinePolicy` and an MVP-fixed
  implementation, bound via `apps/web` config (mirroring
  `TransferWindowPolicy`/`FixedTransferWindowPolicy`'s exact wiring
  pattern) — a 7-day (168-hour) default, environment-overridable.
- A dispute-filing attempt after the deadline has elapsed must be
  rejected the same way an ineligible-status attempt is (§2) — no
  `Dispute` record is created for a late attempt.
- `packages/Disputes` also gains `DisputeResponseDeadlinePolicy` and an
  MVP-fixed implementation (5-day/120-hour default, environment-
  overridable), independent of and shorter than the filing deadline.
- No scheduler is introduced for response-deadline detection — this is a
  deliberate departure from ADR-018 §3's hybrid lazy-plus-scheduled
  evaluation, justified by the absence of any financial deadline or
  silent-failure mode forcing proactive detection.
- No outcome (release/refund/split) may ever be selected automatically on
  the basis of the response deadline alone — every dispute resolution
  requires an explicit admin decision, confirmed unconditionally by §6.
- `packages/Disputes` contains no rules engine, scoring model, or
  threshold-based automation of any kind — its only job is to collect,
  aggregate, enforce invariants, and execute an admin's explicit choice.
  Any future automation is Fraud & Risk's (Phase 8) responsibility
  exclusively, not a capability Phase 6 partially builds toward.
- `Dispute`'s state machine needs no reopening transition, no appeal
  state, and no second-filing guard beyond simply rejecting a new filing
  attempt against a `Transfer` that already has a resolved/closed
  `Dispute` — mirroring `TransferAlreadyIssuedForAuction`'s one-per-
  auction discipline, adapted to one-dispute-per-transfer.
- `Dispute` accepts no new evidence once resolved/closed — evidence
  attachment (whatever shape Decision on evidence ownership ultimately
  takes) must itself be guarded by dispute status, unlike `Transfer`'s
  own `attachEvidence()`, which is deliberately status-independent
  (ADR-020 §4). This is a real, deliberate divergence from that
  precedent, not an oversight: `Transfer` evidence exists so a *future*
  dispute can read it regardless of when it was attached, but a
  `Dispute`'s *own* evidence must close off once the case itself is
  closed, or "no reopening" would be meaningless.
- An appeal/reopening workflow is explicitly out of scope for Phase 6 and
  deferred to a future Administration/Fraud & Risk phase (Phase 8),
  mirroring ADR-018 §4's own precedent for deferring unresolved
  fault/consequence questions.
- No evidence deletion, retention period, expiration, or erasure workflow
  is introduced anywhere in Phase 6 — `packages/Disputes` and
  `packages/Transfers`/`packages/QueuePresence`'s existing evidence
  storage are unchanged. Evidence referenced by a `Dispute` is treated as
  effectively immutable; any future correction is modeled by appending,
  never modifying or deleting. The retention-period/erasure-rights
  question (`claude-mvp-analysis.md` Missing Requirements #13) remains
  explicitly open and unresolved by this ADR.
- A fraudulent-evidence finding (§8) is stored as part of a `Dispute`'s
  own resolution record — no new table, event, or cross-module
  read/write path is introduced for it. `packages/Payments`,
  `packages/Transfers`, `Identity`, and every other module remain
  entirely unaware such a finding was ever recorded.
- Business rule 17's "suspension and forfeiture according to platform
  policy" remains unimplemented and explicitly deferred to
  Administration/Fraud & Risk (Phase 8) — this ADR only ensures the
  underlying observation isn't lost, not that any consequence follows
  from it yet.

## Explicitly Out of Scope

The following capabilities are intentionally excluded from Phase 6 and
require separate architectural review before implementation:

- Chargeback precedence and reconciliation (ADR-023).
- Appeals and dispute reopening (§5).
- Evidence retention schedules and deletion workflows (§7).
- GDPR/CCPA erasure implementation (§7).
- Fraud scoring (§6, §8).
- Account suspension (§8).
- KYC enforcement (§8).
- Account sanctions (§8).
- Automated dispute resolution (§6).
- Cross-dispute fraud correlation (§8).

Each is addressed at its cited section (or, for chargebacks, in
ADR-023) as a deliberate deferral, not an oversight — every one is
either legally sensitive enough to require counsel input first
(chargeback precedence, GDPR/CCPA erasure) or belongs to the future
Administration/Fraud & Risk bounded context, which does not exist until
Phase 8.

## References

- ADR-017 (`Transfer`'s two-sided confirmation model and state machine)
- ADR-018 (the no-fault expiry/cancellation flow this decision draws a
  hard boundary against, and whose scheduler-justification reasoning is
  deliberately not repeated for the response deadline)
- ADR-019 (the capture transition that makes `Confirmed` the only
  financially meaningful dispute-eligible state)
- ADR-013 (`AuctionDurationPolicy`/`FixedAuctionDurationPolicy` and
  ADR-018's `TransferWindowPolicy`/`FixedTransferWindowPolicy` — the
  exact swappable-policy shape `DisputeFilingDeadlinePolicy`/
  `DisputeResponseDeadlinePolicy` mirror)
- `business-rules.md` rule 17 (the fraudulent-evidence consequence named
  with no defined mechanism, narrowly resolved by §8)
- ADR-020 (`Transfer::attachEvidence()`'s append-only shape, mirrored by
  §7/§8's "observe by appending, never modify or delete" discipline)
- Phase 6 architecture review (this conversation, §3, §6, §9, §10, §13)
