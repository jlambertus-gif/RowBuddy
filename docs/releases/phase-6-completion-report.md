# Phase 6 (Disputes) — Completion Report

Tag: `v0.7.0-disputes`
Status: **Complete and formally accepted**, 2026-07-30 — domain/backend
scope only, per this phase's established posture (see §8).

## 1. Executive summary

Phase 6 delivers the Disputes bounded context end-to-end at the
domain/backend level: a buyer can file a dispute against a `Confirmed`
transfer within a 7-day window; the seller may respond with
counter-evidence within an (informational, non-gating) 5-day window; an
administrator reviews the aggregated case and resolves it to exactly one
of four outcomes (release to seller, refund to buyer, split,
cancellation); a refund or split outcome triggers a real, idempotent
Stripe refund through a newly-extended Payments contract. This satisfies
the Phase 6 exit criteria implied by `mvp-scope.md`'s "Basic disputes."

The phase was delivered as 6 sprints, all implemented, validated, and
reviewed independently, with one substantial mid-sprint revision cycle
(Sprint 5, financial-safety hardening) resolved and re-validated before
acceptance rather than deferred. All 10 Phase 6 product decisions were
frozen individually, one at a time, before any ADR was drafted — a more
granular process than any prior phase used — resulting in three ADRs
(021–023), all accepted. 570 automated tests pass across all nine
affected packages/apps, with clean PHPStan/Larastan and Pint throughout,
including an end-to-end idempotency test proving the real refund-trigger
chain is safe against duplicate delivery.

**This phase closes without any HTTP surface, any admin UI, any
automated or rules-based resolution, real Stripe chargeback
reconciliation, evidence retention/deletion, or dispute-specific evidence
submission (photo/statement upload)** — all deliberately deferred, each
for a documented reason. See §8/§9.

## 2. Product decisions (frozen individually, before any ADR)

Unlike every prior phase, Phase 6's ten open product decisions were
resolved one at a time in direct conversation, each with trade-offs
presented and a recommendation given, before any ADR drafting began:

1. Buyer-only filing, against a `Confirmed` transfer only.
2. Filing deadline: 7 days from `Transfer.confirmedAt`, via a swappable
   policy.
3. Seller response window: 5 days from `DisputeOpened`, via a swappable
   policy — informational only, never auto-resolving.
4. No reopening or appeal for MVP — `Resolved` is terminal; admin error
   is an out-of-band administrative correction.
5. True partial refunds supported, bounded only by the captured total —
   no predefined ratios.
6. No fee-specific domain behavior — `capturedAmount` is the only
   financial ceiling; the domain never distinguishes bid from fee.
7. Manual-only administration — Disputes collects, aggregates, enforces
   invariants, and executes; it never decides.
8. No chargeback precedence or reconciliation — an explicit, documented
   legal/architectural deferral.
9. No evidence retention or deletion policy — evidence is effectively
   immutable once referenced by a dispute.
10. Fraudulent-evidence findings are an inert, internal observation on
    `Dispute` — no automatic consequence.

## 3. Sprint-by-sprint deliverables

| Sprint | Commit | Delivered |
|---|---|---|
| 1 | `abaafc3` | `packages/Disputes` scaffold — the `Dispute` aggregate. Two statuses only (`Opened`, `Resolved`) — a deliberate collapse of the `opened`/`under_review` distinction from the original product sketch, since no frozen decision gates behavior between them. `attachEvidence()` (guarded closed once `Resolved`, unlike `Transfer`'s status-independent evidence) and `resolve()` (the sole exit from `Opened`, enforcing outcome/amount consistency). No persistence, no cross-context ports, no Payments contract. |
| 2 | `e396f51` | Persistence layer — `disputes`/`dispute_evidence` migrations (unique constraint on `transfer_id`), `DisputeRepository`, `EloquentDisputeRepository`, `DisputeAlreadyExistsForTransfer`. Wired into `apps/web`. |
| 3 | `238e1c6` | `TransferCaseLookup`, the Disputes-owned read port into Transfers (mirroring `TransferGeofenceLookup`'s shape), plus its `TransferCaseSnapshot`/`TransferEvidenceSummary` DTOs — decoupled copies, no dependency on Transfers' internals. Required no new Transfers-side API. A speculative fake (`FakeTransferCaseLookup`) was added then deliberately removed since nothing consumed it yet. |
| 4 | `33a3d7a` | `DisputeFilingService` — buyer-only, `Confirmed`-only, deadline-gated filing, with a hard rejection (not idempotent replay) on a second filing attempt. `DisputeFilingDeadlinePolicy`/`FixedDisputeFilingDeadlinePolicy`. |
| 5 | `493563b` | `DisputeResolutionService`/`DisputeRefundTriggerService` (the corrected event-driven split, applied from the start this time). Extended `PaymentIntent` (ADR-022): `Refunded` status, `refund()`/`assertRefundable()`, persisted `refundedAmount`/`remainingCapturedAmount()`. **Revised once before acceptance**: added deterministic Stripe idempotency keys, reordered execution to validate→call Stripe→mutate→persist, added end-to-end duplicate-delivery test, and fixed a real migration-ordering bug caught during validation. |
| 6 | `832aa7c` | `DisputeResponseDeadlinePolicy`/`FixedDisputeResponseDeadlinePolicy` — closing a gap found by re-auditing Sprint 4's own approved decision (Decision 3 required this policy in code; only the ADR had captured it). Deliberately left unwired to any consumer. |

## 4. ADRs created

- **ADR-021 — Disputes Filing Eligibility, Deadlines, and Scope
  Boundary**: buyer-only filing against `Confirmed` transfers only,
  drawing a hard boundary against ADR-018's no-fault flow; both deadline
  policies; terminal lifecycle with no reopening; manual-only resolution
  (Disputes executes, never decides); evidence immutability; the
  fraudulent-evidence observation. Records Decisions 1–4, 7, 9, 10.
- **ADR-022 — Disputes-to-Payments Refund Contract**: `PaymentIntent`
  lifecycle extension, one new `Refunded` status regardless of amount,
  "split" as a `Dispute`-resolution concept never a `PaymentIntent`
  state, no fee-specific domain behavior. Records Decisions 5–6.
- **ADR-023 — Stripe Chargeback Precedence Deferral**: an explicit
  architectural and legal deferral, not silence — recorded so the gap
  reads as deliberate scope in any future audit. Records Decision 8.

ADRs 001–020 were pre-existing and remain unchanged and binding.

## 5. Architecture changes

- One new package, `packages/Disputes`, following the exact shape
  established since Phase 1.
- **A financial-safety pattern new to this codebase**: deterministic
  Stripe idempotency keys derived from stable domain data (`auctionId` +
  `disputeId`), and an explicit, documented validate→call-Stripe→
  mutate→persist ordering — the first place in this codebase where "what
  if the external call succeeds but the local commit fails" is addressed
  head-on rather than by convention alone.
- `PaymentIntent` gains a persisted, reconstructible `refundedAmount` and
  a computed `remainingCapturedAmount()` — the first aggregate in this
  codebase to retain a partial-completion amount distinct from its
  terminal status.
- `PaymentCaptureService` gained a third operation (`refund()`) rather
  than a new service class, extending the same "grow the existing
  service" precedent it already set for `capture()`/`cancel()`.
- The event-driven module boundary (verified and corrected once, at
  cost, during Phase 5) was applied correctly from the start in Phase 6:
  `DisputeResolutionService`/`DisputeRefundTriggerService` never needed a
  correction cycle.
- `TransferCaseLookup` extends the "consumer owns the port" pattern a
  fourth hop (Disputes → Transfers), with zero new Transfers-side API
  required.

## 6. Database changes

`packages/Disputes/database/migrations/`:
- `create_disputes_table` — unique constraint on `transfer_id`.
- `create_dispute_evidence_table` — one-to-many, append-only, mirroring
  `transfer_evidence`.

`packages/Payments/database/migrations/`:
- `add_refunded_amount_to_payment_intents_table` — nullable
  `refunded_amount_minor_units`/`refunded_amount_currency`.

No changes to any Phase 1–5 package's schema beyond that one additive
pair of columns.

## 7. Test and validation results

| Package | Tests | Notes |
|---|---|---|
| `packages/shared-kernel` | 31 | Unaffected. |
| `packages/Queues` | 81 | Unaffected. |
| `packages/QueuePresence` | 81 | Unaffected. |
| `packages/Auctions` | 66 | Unaffected. |
| `packages/Bids` | 24 | Unaffected. |
| `packages/Payments` | 93 (was 77 at Phase 5 close) | Gained the refund contract, `Refunded` lifecycle, and idempotency/ordering hardening. |
| `packages/Transfers` | 58 | Unaffected by Phase 6. |
| `packages/Disputes` | 45 | New package — aggregate, persistence, cross-context read port, filing, resolution, refund trigger, both deadline policies, across all six sprints. |
| `apps/web` | 91 (was 87 at Phase 5 close) | Includes the refund-gateway wiring and the end-to-end duplicate-delivery idempotency test. |

**Total: 570 automated tests.** PHPStan/Larastan clean across every
package at every sprint. Pint clean on `packages/Payments` and
`packages/Disputes`; `apps/web` carries the same 12 pre-existing style
issues from Phase 1, confirmed unrelated and unchanged throughout every
Phase 6 sprint.

**A real bug was caught and fixed during Sprint 5's validation, not
after**: a migration filename dated earlier than the table-creating
migration it altered, which would have broken a fresh install. Caught by
running the migration against real Postgres before commit, not
discovered later.

**No real Stripe test-mode integration test exists**, unchanged from
Phases 4–5's own acknowledgment.

## 8. Closure-scope decision

Phase 6 closes without any HTTP surface, admin UI, automated resolution,
chargeback reconciliation, evidence retention, or dispute-specific
evidence submission — narrower than a literal reading of "Basic
disputes" might suggest, decided deliberately at each boundary, not
discovered at closure:

- **Decision 7** (manual-only administration) and **ADR-021 §6** rule out
  any automated or rules-based resolution component — Disputes collects,
  aggregates, enforces invariants, and executes; any future automation
  belongs exclusively to Fraud & Risk (Phase 8).
- **Decision 8/ADR-023** explicitly defers chargeback precedence and
  reconciliation pending legal review — recorded as a deliberate gap, not
  silence.
- **Decision 9/ADR-021 §7** explicitly defers evidence retention/
  deletion — no capability was built, by design, so nothing needs to be
  un-built later.
- **No HTTP, frontend, or Reverb** — the same posture Phases 3–5 already
  established; Phase 6 required no exception (unlike Phase 4's Stripe
  webhook).
- **Dispute-specific evidence submission** (an actual photo/statement
  upload mechanism, as opposed to the aggregate-level `attachEvidence()`
  capability that already exists) was never requested by any of the 10
  frozen decisions and was not built, consistent with avoiding
  speculative scaffolding.

## 9. Known limitations and deferred product decisions

### Accepted limitations (by design, not oversights)

- **No seller-initiated dispute path exists** — only the buyer may file
  (Decision 1).
- **No reopening or appeal workflow** — an admin's resolution is final;
  error correction happens outside the domain model (Decision 4).
- **`DisputeResponseDeadlinePolicy` has no consumer yet** — it computes a
  value nothing currently reads; it exists for a future admin
  read-model.
- **No chargeback/dispute-webhook reconciliation** — a Stripe-issuer
  chargeback and a RowBuddy `Dispute` can coexist with no code-level
  awareness of each other (ADR-023).
- **No evidence retention, deletion, or GDPR/CCPA erasure capability** —
  evidence is retained indefinitely, effectively immutable once
  referenced by a dispute (Decision 9).
- **No account-level consequence of any kind** — no suspension, fraud
  scoring, KYC action, or sanction follows from a fraudulent-evidence
  finding (Decision 10).
- **No dispute-specific evidence storage/submission mechanism** —
  `Dispute::attachEvidence()` exists as a first-class aggregate
  capability (Sprint 1) but nothing calls it; no upload endpoint, no
  `DisputeEvidenceStorage` port.
- **No real Stripe webhook-driven reconciliation for refunds** —
  recovery from an external-success/local-failure gap relies on
  retry-with-idempotency-key, not a defensive webhook listener (mirroring
  `StripeCancellationReconciliationService`'s pattern, but not built this
  phase).
- **No real Stripe test-mode credentials exist in this environment**,
  unchanged from Phases 4–5.

### Deferred product decisions

- Whether the symmetric no-fault posture inherited from ADR-018 should
  ever become fault-based, once real dispute volume exists to judge by.
- The still-open protection-period/payout-timing decision (carried from
  Phase 4/5) — remains irrelevant to Disputes today since no payout
  execution exists, but will matter once it does.
- Real chargeback precedence — requires legal/counsel input, per
  `jurisdiction-requirements.md`.
- Evidence retention periods and erasure-rights compliance, per
  jurisdiction.
- Whether and how a future Administration/Fraud & Risk phase reads
  fraudulent-evidence findings to act on patterns across disputes.

## 10. Phase 7 readiness assessment

Phase 7 (per `docs/roadmap.md`) is Ratings & Notifications. Phase 6
provides:

- A complete, terminal record of how every disputed handoff actually
  resolved (`DisputeResolved`'s payload: outcome, amount, resolver,
  notes, fraud finding) — a natural signal for **Notifications** to react
  to (e.g., notifying both parties of a resolution) once a real
  event-listener wiring exists, and plausibly relevant to **Ratings**
  (e.g., whether a dispute affects a rating prompt or its weighting) — a
  product decision Phase 7 itself will need to make, not one Phase 6
  resolves.
- Every dispute event already implements `AuditableAction`, captured by
  the existing generic audit sink with zero new wiring — Notifications'
  own future listener registration can follow the identical,
  already-proven pattern.
- A fifth confirmed instance of the "consumer owns the port" pattern and
  the corrected event-driven-reactor split, both now proven across two
  consecutive phases without a second correction needed.

**No blockers identified for Phase 7 at the domain level.** Most of
Phase 6's own deferred items (automated resolution, chargeback
reconciliation, evidence retention, account-level consequences) are
Phase 8 (Administration & Fraud/Risk) territory, not Phase 7's — Phase 7
can proceed independently of them. Per explicit instruction, Phase 7
implementation will not begin until a separate architecture review and
planning session is completed.
