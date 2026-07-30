# ADR 024: Ratings Eligibility and Symmetric Model

## Status

Accepted (2026-07-30). All Phase 7 product decisions affecting Ratings
(Decisions 2, 3, 4, 5, 6, and 8 of the Phase 7 decision set) are frozen
and recorded below. Accepted together with ADR-025 (Notifications)
after joint final architectural review. **Amended (2026-07-30, during
Sprint 4 planning):** §5's reveal-deadline anchor corrected from
`Transfer.confirmedAt` to the rating's own `submittedAt`, to match the
design Sprint 3 had already built and to correctly support ratings
submitted long after confirmation — no other decision changed. Decision
6's deferred implementation detail — the exact maximum comment length —
was selected
during Sprint 1 and is recorded there: 1,000 characters.

## Context

Unlike Disputes (ADR-021 §1), which was deliberately buyer-only because
only the buyer retains a financial claim once capture already triggered
in the seller's favor, a rating carries no financial claim at all — it
is a record of how the other party behaved during the handoff, an
experience both parties share equally. Nothing in `claude-mvp-analysis.md`
or `mvp-scope.md` specifies rating eligibility; the architecture review
(Phase 7, §4) identified this as the first open product decision after
confirming Notifications' MVP scope.

A second risk drove this decision: ADR-018 §4 deliberately declined to
attribute fault for a buyer no-show or seller default at the *financial*
layer ("inventing an asymmetric forfeiture/penalty scheme here would be a
real consumer-protection and legal decision this ADR is not positioned to
make"). Allowing ratings against `Issued`/`Expired`/`Cancelled` transfers
would reopen that exact question at the *reputation* layer instead —
attaching a public fault judgment to precisely the cases with the least
reliable evidence of what actually happened, since nobody ever confirmed
anything.

## Decision

### 1. Rating eligibility is symmetric

Both the buyer and the seller on a transfer may each submit one rating of
the other party. Unlike Disputes' deliberate buyer-only asymmetry
(ADR-021 §1), there is no comparable reason to restrict rating rights to
one side — both parties experienced the same handoff and both have equal
standing to comment on it.

### 2. Ratings are permitted only for a `Transfer` in `Confirmed` status

`Issued`, `Expired`, and `Cancelled` transfers are **not** eligible for a
rating, mirroring ADR-021 §2's identical reasoning one context further:
`Confirmed` is the only state where a real, evidenced interaction
occurred. This is a hard eligibility gate, not a default — an attempt to
rate an ineligible transfer must be rejected before any `Rating` record
is created, the same "a rejected attempt leaves no trace" discipline
`TransferConfirmationService`'s and `DisputeFilingService`'s own gates
already established.

**This deliberately avoids introducing fault attribution through the
rating system for transfers that never completed.** A no-show or a
failed handoff is, and remains, ADR-018 §4's exclusively no-fault,
symmetric concern — Ratings does not become a second, informal channel
for assigning blame to a transfer that resolved that way.

### 3. Each participant may rate the other only once per transfer

Mirroring `TransferAlreadyIssuedForAuction`'s/`DisputeAlreadyExistsForTransfer`'s
one-per-transfer discipline, adapted to two independent rating slots
(buyer→seller and seller→buyer) rather than one — a second rating attempt
by the same party for the same transfer is rejected, not silently
overwritten or appended.

### 4. Ratings and Disputes remain fully independent bounded contexts

A `Confirmed` transfer remains rating-eligible regardless of whether it
was, or later becomes, the subject of a `Dispute` — a filed or resolved
dispute never blocks, delays, or otherwise alters rating submission. In
the opposite direction, a `Rating` never exposes the existence or outcome
of any dispute associated with its transfer: no dispute fact of any kind
is read, stored, or displayed by `packages/Ratings`.

`packages/Ratings` therefore introduces no read dependency on
`packages/Disputes` of any kind — its eligibility rule depends solely on
`Transfer`'s status (Decision 2), the same single fact and the same
`TransferCaseLookup`-style read port already required to enforce that
rule, with no additional port, flag, or query added for Disputes.

This was considered and deliberately rejected against two alternatives:
a dispute blocking rating submission outright (no product or ADR basis,
and a chilling effect on legitimate dispute filing — a buyer who used the
dispute process would lose the ability to rate at all), and surfacing the
dispute's existence/outcome alongside a rating (a new, unrequested
reputational data flow that would reopen ADR-018 §4's and Decision 2's
own no-fault-attribution posture through a side door, with real
defamation-adjacent legal exposure).

If a future Fraud & Risk phase (Phase 8) needs to correlate ratings and
disputes for signal purposes, it will do so by reading both bounded
contexts explicitly and independently, for that stated purpose — not by
changing either model or adding a cross-context dependency now.

### 5. Ratings use a double-blind reveal model, evaluated lazily

A submitted rating is persisted immediately, but stays hidden from the
counterparty and from any public display until either:

- both parties on the transfer have submitted a rating, or
- that rating's own reveal deadline has elapsed, governed by a swappable
  `RatingRevealDeadlinePolicy` (mirroring `DisputeFilingDeadlinePolicy`'s/
  `DisputeResponseDeadlinePolicy`'s identical shape) — the MVP default
  implementation uses a fixed duration computed from **the rating's own
  `submittedAt`**, not from any transfer-level timestamp.

This anchor is deliberate, not incidental: unlike Disputes' filing
window, Ratings has no deadline on *submission* itself — a rating may be
submitted whenever the transfer is `Confirmed`, however long after that
status was reached. Anchoring the reveal deadline to `Transfer.confirmedAt`
instead would mean a rating submitted long after confirmation could
already be past its own transfer-level deadline the moment it's
submitted, revealing instantly with no blind window at all — defeating
the purpose for exactly the late-arriving ratings this system must still
support. Anchoring to each rating's own `submittedAt` guarantees every
rater a full blind window from the moment they act, regardless of how
much time passed since confirmation or whether the counterpart ever
rates at all. `Transfer.confirmedAt` is therefore not required by
Ratings and is deliberately absent from `TransferParticipantSnapshot`
(§7/Consequences) — it plays no role in this decision.

This exists to prevent retaliatory rating — a party who could see the
other's rating before submitting their own could shade their own score to
match or punish it, corrupting the rating as a measure of the actual
handoff experience.

**No scheduler is introduced for this.** Unlike `TransferExpiryEvaluator`
(ADR-018), which justified a Horizon-scheduled job because an
un-confirmed transfer's authorization must be actively released even if
nobody ever looks at it again, a hidden rating has no side effect that
must fire in the absence of a read — nothing external depends on the
exact moment of reveal. Reveal eligibility is therefore evaluated the
same way this codebase already evaluates other time-bound state lazily
elsewhere: immediately, in-process, whenever the second rating on a
transfer is submitted (an eager reveal, no deadline check needed); or
lazily, whenever a `Rating` is read, by re-evaluating
`RatingRevealDeadlinePolicy` against the read-time clock. Both paths
converge on the same stored fact — a rating is either revealed or not —
with no background process required to keep it correct.

If a future phase needs proactive notification at the moment a deadline
elapses (as opposed to correctness of what's returned on read), that
phase may introduce a scheduler then; Phase 7 does not.

### 6. Rating content is a single required 1–5 score plus an optional, bounded, untouched comment

A `Rating` carries exactly one required score, a domain-enforced integer
in the closed range `[1, 5]` — enforcement lives on the aggregate itself
(constructor/factory validation), not only in a form request or
presentation-layer rule, so an invalid score cannot reach persistence
through any caller. No sub-dimensional or category-specific scores exist;
this is deliberately the smallest model that serves a handoff-quality
record, consistent with Decision 5's rejection of speculative
multi-dimensional scoring.

A comment is optional free text with a bounded maximum length. Selected
during Sprint 1 implementation: **1,000 characters** (`Rating::MAX_COMMENT_LENGTH`,
measured with `mb_strlen` to count characters rather than bytes) —
long enough for genuine handoff feedback, short enough to keep any
future display or notification render bounded; covered by boundary
tests at exactly 1,000 (accepted) and 1,001 (rejected). A blank or
whitespace-only comment is normalized to `null` at the domain layer, not
merely trimmed for display — there is no distinction in this system
between "no comment" and "an empty one." A non-blank comment is stored
exactly as submitted, with no trimming of surrounding whitespace, per
this project's standing rule that user-generated content is stored
exactly as its author wrote it.

A comment is stored exactly as submitted, in the rater's original
locale/language, per this project's standing localization rule that
user-generated content stores its original locale rather than being
translated at write time. **Ratings performs no auto-translation,
rewriting, moderation, or sentiment inference on comments in Phase 7** —
a comment is opaque, untouched user content; any of those capabilities,
if ever wanted, is a distinct, explicitly-scoped decision for a later
phase, not an implicit consequence of accepting free text now.

### 7. Phase 7 closure for Ratings is domain/backend-only

Ratings follows the exact precedent established by Phases 3–6 (Auctions,
Bids, Payments, Transfers, Disputes): Phase 7 completion for Ratings
requires a complete domain model, application services, persistence,
read ports (including the `TransferCaseLookup`-style eligibility port,
Decision 2/4), business rules, the reveal policy (Decision 5), and
comprehensive automated tests — it does **not** require an HTTP
endpoint, an Inertia/React page, or any user-facing rating-submission UI.
A rating is created only by directly invoking the application service,
the same way every prior phase's own tests exercise their aggregates.

This was considered and deliberately rejected against extending
Notifications' real-delivery requirement (ADR-025 §1) to Ratings as
well, purely because the two modules ship in the same phase. That
exception was reasoned specifically from what a notification *is* — it
isn't a notification at all without real delivery — and no equivalent
argument applies to a rating record's existence, which is fully
meaningful and testable without a submission UI. Extending the exception
here would be scope creep, the same kind Decision 5 already declined for
multi-dimensional scoring.

**Phase 7 therefore has two different exit bars**, by design: Ratings
closes domain/backend-complete, exactly like every module before it;
Notifications closes only once end-to-end delivery through a real email
channel is operational, tested, and integrated (ADR-025 §1/§8). This
asymmetry is deliberate and is not itself a defect to reconcile later.

## Consequences

- `packages/Ratings` gains an eligibility check depending on reading
  `Transfer`'s current status via a read-only cross-context port
  (mirroring Disputes' `TransferCaseLookup`, Phase 7 architecture review
  §9) — `packages/Ratings` gains no dependency on `packages/Transfers`'
  internals, and, per Decision 4, no dependency on `packages/Disputes`'
  internals either.
- No eligibility check, display, or any other behavior in
  `packages/Ratings` depends on whether the transfer was later the
  subject of a `Dispute`, or on that dispute's outcome. This is now
  resolved, not deferred.
- ADR-018 is not reopened or modified by this decision — its symmetric
  no-fault mechanism remains the sole resolution for
  `Issued`/`Expired`/`Cancelled` transfers, at both the financial and
  reputational layer.
- Persistence must enforce "at most one rating per (transferId, raterId)"
  — a uniqueness constraint mirroring every prior phase's identical
  discipline, not merely an application-layer check.
- `Rating` persistence must distinguish "submitted" from "revealed" as a
  computable read-time fact (either the counterpart rating exists, or
  *this* rating's own `submittedAt`-anchored deadline per
  `RatingRevealDeadlinePolicy` has elapsed) rather than a value fixed at
  write time — no background job maintains it.
- `packages/Ratings` gains a `RatingRevealDeadlinePolicy` contract with a
  fixed-duration MVP default, mirroring `DisputeFilingDeadlinePolicy`/
  `DisputeResponseDeadlinePolicy`'s swappable-policy shape exactly. The
  policy computes a duration only — the anchor timestamp is always the
  rating's own `submittedAt`, never a transfer-level timestamp.
- `TransferParticipantSnapshot` (§7) correctly has no `confirmedAt`
  field — Ratings' reveal computation never needs one.
- No Horizon-scheduled job is introduced in Phase 7 for reveal; any
  proactive reveal-deadline notification is explicitly deferred to a
  later phase, should one require it.
- The `Rating` aggregate rejects an out-of-range score at construction
  time (`RatingScore`), with tests proving both boundaries (`0`/`6`
  rejected, `1`/`5` accepted); a test proves whitespace-only comment
  input normalizes to `null`; boundary tests prove 1,000 characters is
  accepted and 1,001 is rejected.
- Comment moderation, auto-translation, and sentiment inference are
  explicitly out of scope for Phase 7 — any future addition is a new
  decision, not a natural extension of accepting free text now.

## References

- ADR-018 (the no-fault expiry/cancellation flow this decision
  deliberately does not extend a fault-attribution channel around, and
  whose scheduled-job justification Decision 5 explicitly distinguishes
  itself from)
- ADR-021 §1/§2 (Disputes' asymmetric filing right and `Confirmed`-only
  eligibility gate — the shape this decision mirrors, adapted to
  Ratings' symmetric case)
- ADR-021 §3/Phase 6 Sprint 6 (`DisputeFilingDeadlinePolicy`/
  `DisputeResponseDeadlinePolicy` — the swappable-policy shape Decision 5's
  `RatingRevealDeadlinePolicy` mirrors)
- ADR-025 §1/§8 (Notifications' real-delivery closure bar — the
  exception Decision 8/§7 above deliberately declines to extend to
  Ratings)
- Phase 7 architecture review (this conversation, §2, §4, §9)
