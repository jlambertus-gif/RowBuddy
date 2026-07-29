# Phase 5 (Transfers) — Completion Report

Tag: `v0.6.0-transfers`
Status: **Complete and formally accepted**, 2026-07-29 — domain/backend
scope only, per this phase's established posture (see §8).

## 1. Executive summary

Phase 5 delivers the Transfers bounded context end-to-end at the
domain/backend level: a won auction whose payment was authorized
(Payments' `PaymentAuthorized`) gets a `Transfer` issued with a
buyer-held, hashed-at-rest QR code; both seller and buyer must
independently confirm the handoff, each gated by a point-in-time geofence
cross-check, the seller additionally gated by QR validation; the second
confirmation triggers the real Stripe capture; a transfer nobody
confirms in time expires under a hybrid lazy-evaluation-plus-scheduled-
sweep mechanism (this codebase's first Horizon-scheduled job) and cancels
the authorization with no charge, symmetrically and without assigning
fault; a defensive Stripe webhook reconciliation path backs up the
scheduler; and either party may attach optional photo evidence to a
transfer regardless of its confirmation state, in anticipation of Phase 6
(Disputes). This satisfies the Phase 5 exit criteria defined in
`docs/roadmap.md`.

The phase was delivered as 6 sprints plus one mid-phase architectural
correction, each scoped, implemented, tested, and reviewed independently.
Four ADRs (017–020) resolved every open architectural question before
Sprint 1 began, with two of them (018, 020) revised once each after
explicit rejection and re-approved before implementation started. 505
automated tests pass across all eight affected packages/apps, with clean
PHPStan/Larastan and Pint throughout, including four real-PostgreSQL
concurrency tests (two carried over in spirit from Phase 3's precedent,
two new to Phase 5's scheduler).

**This phase closes without seller payout execution, buyer payment-
method acquisition, real Laravel event-listener wiring, or ADR-018 §2's
proactive re-authorization** — all deliberately deferred, each for a
documented reason, not discovered at closure. See §8 and §9 for the full
rationale.

## 2. Product decisions

Unlike Phase 4, no separate business/product decisions were resolved
ahead of the architectural design step — every open question this phase
faced (QR issuance/validation direction, the confirmation model, the
transfer window's source and duration, the capture contract, the
geofence-enforcement strictness, the evidence policy, the scheduler
justification, the no-fault cancellation policy) was resolved directly
within ADR-017 through ADR-020 during the dedicated pre-Sprint-1 design
phase, described in §4 below.

## 3. Sprint-by-sprint deliverables

| Sprint | Commit | Delivered |
|---|---|---|
| 1 | `4f81b29` | `packages/Transfers` scaffold — the `Transfer` aggregate (`Issued`/`Confirmed`/`Expired`/`Cancelled`), two-sided `confirmBySeller()`/`confirmByBuyer()` (only the second confirmation reaches `Confirmed`), `expire()`/`cancel()`, and — per explicit instruction during ADR-020's review — first-class, repeatable evidence (`attachEvidence()`/`evidenceRecords()`, `TransferEvidenceType`/`TransferEvidenceRecord`) present from Sprint 1 even though unused until Sprint 6. No persistence, no Stripe, no HTTP. |
| 2 | `23b31a8` | Persistence layer — `transfers`/`transfer_evidence` migrations (unique constraint on `auction_id`, ADR-017 §6), `TransferRepository` (`save()`/`recordEvidence()`/`findById()`/`findByAuctionId()`/`findByIdForUpdate()`), its Eloquent adapter, `TransferAlreadyIssuedForAuction`. Found and fixed a SQLite/PostgreSQL cross-driver type difference on `decimal` geolocation columns. |
| 3 | `843b2cc` | ADR-019's `PaymentIntent` lifecycle extension, in `packages/Payments`: `PaymentIntentRepository` switched from append-only `record()` to mutable `save()`/`findByIdForUpdate()`; three new states (`Captured`/`CaptureFailed`/`Cancelled`) and transitions (`capture()`/`failCapture()`/`cancelAuthorization()`); `PaymentCaptureService` implementing ADR-019 §6's locked sequence. Found and fixed a genuine Sprint-4-era (Phase 4) gap: `AuctionWinAuthorizationService` computed a real Stripe PaymentIntent id but never stored it — fixed by adding `stripePaymentIntentId` and threading it through. |
| 4 | `1ea94be` | `TransferInitiationService` (the real `PaymentAuthorized` consumer: issues the `Transfer`, generates the plaintext QR token, stores only its hash) and `TransferConfirmationService` (validates the QR/geofence, mutates, persists, publishes — originally also triggered capture directly). |
| correction | `25b4c8c` | Requested verification surfaced a real boundary violation: `TransferConfirmationService` was triggering Stripe capture by reading `Transfer`'s post-mutation status inline, not by reacting to the committed `TransferConfirmed` event — breaking the event-driven module boundary Phases 3–4 established. Corrected by extracting `TransferCaptureTriggerService`, the real `TransferConfirmed` consumer; `TransferConfirmationService` now does only validation, mutation, persistence, and event publication. |
| 5 | `39b17a1` | ADR-018's hybrid expiry evaluation: `TransferExpiryEvaluator` (one evaluation, two call sites), lazy invocation wired into `TransferConfirmationService`, and `TransferExpirySweepService` driven by this codebase's first Horizon-queued job (`EvaluateTransferExpiry`, registered via `bootstrap/app.php`'s `withSchedule()`). `TransferCancelTriggerService` mirrors the capture trigger's shape for `TransferExpired`/`TransferCancelled`. Two real-PostgreSQL concurrency tests prove the row lock serializes a scheduled sweep tick against a concurrent confirmation attempt, and against another overlapping sweep tick. ADR-018 §2's re-authorization was deliberately not implemented (see §9). |
| 6 | `d4aec6f` | The two remaining accepted-but-unbuilt ADR pieces: ADR-019 §7's `StripeCancellationReconciliationService`, reconciling `payment_intent.canceled` webhooks defensively via the same `cancelAuthorization()` transition Sprint 3 built; and ADR-020 §4's evidence storage — `TransferEvidenceStorage`/`ImageMetadataStripper` (Transfers' own copies of QueuePresence's Phase 2 ports) and `TransferEvidenceSubmissionService`, the first real caller of `attachEvidence()`/`recordEvidence()`. |

## 4. ADRs created

- **ADR-017 — Transfer Aggregate, Two-Sided Confirmation Model, and QR Mechanics**: the `Transfer` aggregate's shape and state machine (`Issued → Confirmed/Expired/Cancelled`, all terminal); confirmation requires both parties independently, mirroring `BidService`'s "rejection leaves no trace" shape rather than `PaymentIntent`'s "record the failure too" shape; the system issues the QR to the buyer at issuance, the seller scans/validates it (the seller verifies the unverified party, not the reverse); one `Transfer` per auction, no re-transfer.
- **ADR-018 — Transfer Window, Expiry, Re-Authorization, and Scheduler Policy**: revised once, adding a full "Alternatives considered" analysis (lazy-only, webhook-only, scheduled-only, hybrid) before re-approval, per explicit instruction to justify a scheduler against every simpler alternative first. Decided Option D (hybrid): a single `TransferExpiryEvaluator` invoked both lazily and via a scheduled sweep — this codebase's first genuinely new background-infrastructure mechanism. §4's buyer-no-show/seller-default symmetric no-fault cancellation was explicitly flagged provisional, pending product sign-off, and was approved as-is at Sprint 5's review without introducing any fault-based logic.
- **ADR-019 — Transfers-to-Payments Capture Contract and PaymentIntent Lifecycle Extension**: `PaymentIntentRepository`'s append-only-to-mutable pattern switch; three new `PaymentIntentStatus` states; the Transfers-owned `PaymentCaptureGateway` port (implemented Sprint 4 with a deliberate, transparently-flagged deviation from this ADR's original sketch — `void` methods, no `reauthorize()` yet, since `PaymentCaptureService` already self-audits its own outcome); the exact locked capture/cancel sequence; §7's webhook-reconciliation scope, built in Sprint 6. Seller payout execution explicitly stays out of Phase 5.
- **ADR-020 — Handoff Geolocation Cross-Check and Evidence Policy**: revised once — an earlier draft modeled evidence as an `apps/web`-only storage concern with no aggregate representation; rejected because Phase 6 (Disputes) needs evidence "aggregated from other contexts (read-only)," which would otherwise force a Phase 6 redesign of `Transfer` itself. Revised to make evidence a first-class, extensible, repeatable concept on the aggregate from Sprint 1, deliberately not gated by confirmation status. The confirmation-time geofence check is point-in-time, not continuous (unlike QueuePresence's proximity monitoring), and no anti-spoofing beyond the geofence check exists — an accepted MVP limitation.

ADRs 001–016 were pre-existing and remain unchanged and binding.

## 5. Architecture changes

- One new package, `packages/Transfers`, following the exact package shape established since Phase 1, with its own `DomainEventPublisher`/`TransactionManager` copies (now four packages each carrying an identical copy, all satisfied by the same `apps/web` `LaravelTransactionManager` class implementing all four interfaces simultaneously).
- **A three-hop "consumer owns the port" extension**: `TransferGeofenceLookup` (Transfers-owned, bridging Auctions+Queues) and `PaymentCaptureGateway` (Transfers-owned, bridging to Payments) — the same discipline `AuctionGateway`/`SellerPresenceVerification` established, now one hop further from the original module.
- **The event-driven module boundary was explicitly verified and, where it had drifted, corrected mid-phase**: every cross-module reaction in Transfers (`TransferInitiationService` reacting to `PaymentAuthorized`, `TransferCaptureTriggerService` reacting to `TransferConfirmed`, `TransferCancelTriggerService` reacting to `TransferExpired`/`TransferCancelled`) is now a dedicated service invoked off a committed domain event's payload, never a caller reading an aggregate's own post-mutation status to decide whether to act. None of these are yet wired to a real Laravel `Event::listen()` — mirroring the exact same posture Phase 4 established for `AuctionWon`, still unwired to this day.
- **This codebase's first genuinely new background-infrastructure mechanism**: a Horizon-queued job (`App\Jobs\EvaluateTransferExpiry`) registered on Laravel 12's scheduler (`bootstrap/app.php`'s `withSchedule()`), justified in ADR-018 against three simpler alternatives before being introduced.
- **`PaymentIntentRepository` pattern switch** (Payments): from `BidRepository`-style append-only `record()` to `AuctionRepository`-style mutable `save()`/`findByIdForUpdate()`, since `PaymentIntent` gained real post-authorization transitions.
- Transfers gained its own copies of QueuePresence's Phase 2 evidence-handling ports (`ImageMetadataStripper`, `TransferEvidenceStorage`) rather than reusing QueuePresence's interfaces directly, per this project's "each consuming module gets its own copy" discipline — reusing the same underlying mechanics (GD re-encoding, private local disk, signed URLs), not the same classes.
- No HTTP surface was added for any Transfers-owned capability (confirmation, evidence submission) — consistent with Phase 3/4's established domain/backend-only posture. `apps/web` gained one small HTTP change: the existing `POST /webhooks/stripe` endpoint now threads through the Stripe object id needed for ADR-019 §7's reconciliation.

## 6. Database changes

`packages/Transfers/database/migrations/`:
- `create_transfers_table` (Sprint 2) — unique constraint on `auction_id` (one transfer per auction, ADR-017 §6); `decimal(10,7)` lat/lng columns for both parties' confirmation geolocation.
- `create_transfer_evidence_table` (Sprint 2) — one-to-many from `transfers`, not a JSON blob column.

`packages/Payments/database/migrations/`:
- `add_stripe_payment_intent_id_to_payment_intents_table` (Sprint 3) — the fix for the Phase-4-era persistence gap described in §3.

No changes to any Phase 1–4 package's schema beyond that one additive column.

## 7. Test and validation results

| Package | Tests | Notes |
|---|---|---|
| `packages/shared-kernel` | 31 | Unaffected by Phase 5. |
| `packages/Queues` | 81 | Unaffected by Phase 5. |
| `packages/QueuePresence` | 81 | Unaffected by Phase 5. |
| `packages/Auctions` | 66 | Unaffected by Phase 5. |
| `packages/Bids` | 24 | Unaffected by Phase 5. |
| `packages/Payments` | 77 (was 55 at Phase 4 close) | Gained the capture/cancel lifecycle (Sprint 3) and webhook reconciliation (Sprint 6). |
| `packages/Transfers` | 58 | New package — aggregate, application services, persistence, evidence storage, and two real-PostgreSQL concurrency tests, across all six sprints. |
| `apps/web` | 87 (was 83 at Phase 4 close) | Includes the scheduler-wiring smoke tests, the evidence-storage wiring test, and the extended Stripe webhook reconciliation test. |

**Total: 505 automated tests.** PHPStan/Larastan clean across every package at every sprint. Pint clean on `packages/Payments` and `packages/Transfers`; `apps/web` carries the same 12 pre-existing style issues from Phase 1, confirmed unrelated and unchanged throughout every Phase 5 sprint.

**Four real-PostgreSQL concurrency tests exist for this phase's row-lock discipline**: two in `packages/Transfers/tests/Integration/ConcurrentTransferExpiryEvaluationTest.php`, proving the scheduled sweep and a concurrent confirmation attempt (and two overlapping sweep ticks) can never both act on the same `Transfer` — the same two-independent-PDO-connection, bounded-`lock_timeout` technique ADR-012's Bids concurrency test established in Phase 3.

**Pint's cross-package-import hazard recurred again this phase** (previously seen in Phases 3 and 4) — every occurrence across all six sprints was same-package (harmless `{@see}` docblock references), confirmed via a full-tree `grep` for `RowBuddy\{Auctions,Bids,Queues,QueuePresence,Payments}` returning zero matches in `packages/Transfers`, and the reverse check in `packages/Payments`.

**No real Stripe test-mode integration test exists**, unchanged from Phase 4's own acknowledgment — this environment still has no real Stripe test API keys configured. The new `StripeCancellationReconciliationService` and `PaymentCaptureService` are tested against fakes; `StripeWebhookSignatureVerifier`'s payload-parsing extension (extracting `objectId`) is fully tested since it remains pure local logic with no network dependency.

## 8. Closure-scope decision

Phase 5 closes without payout execution, buyer payment-method
acquisition, or real Laravel event-listener wiring for any cross-module
reaction — narrower than a literal reading of the roadmap's Phase 5
description might suggest, decided deliberately and documented as each
boundary was reached, not discovered at closure:

- **ADR-019 §5** (Sprint drafting) kept seller payout execution out of
  Phase 5 entirely, extending ADR-015's own restraint from Phase 4:
  `PayoutPreparationService` (Phase 4) remains the final word on payout
  until a separately authorized payout-execution phase.
- **ADR-018 §2** (Sprint 5) was explicitly not implemented: proactive
  re-authorization before Stripe's own expiry depends on a stored
  Stripe-side authorization-expiry timestamp and an undefined margin
  threshold, neither of which exists anywhere in this codebase, and is
  structurally unreachable under the current 24-hour `TransferWindowPolicy`
  default regardless.
- **No real `Event::listen()` wiring exists** for `PaymentAuthorized` →
  `TransferInitiationService`, `TransferConfirmed` →
  `TransferCaptureTriggerService`, or `TransferExpired`/`TransferCancelled`
  → `TransferCancelTriggerService` — mirroring the exact same posture
  Phase 4 established for `AuctionWon` → `AuctionWinAuthorizationService`,
  still unwired to this day. This is delivery-layer work for a later
  phase, consistently deferred across every phase since Phase 3.
- **HTTP, frontend, and Reverb** were deferred by the same posture Phases
  3 and 4 already established — this phase's objective was the Transfers
  *domain*, not its delivery layer. Unlike Phase 4's one HTTP exception
  (a genuinely required inbound webhook), Phase 5 required no new HTTP
  surface at all.

This mirrors Phase 4's own closure, which accepted a domain/backend-only
bar in exchange for not building throwaway or speculative delivery-layer
work ahead of design.

## 9. Known limitations and deferred product decisions

### Accepted limitations (by design, not oversights)

- **No seller payout execution exists anywhere in this codebase.** A
  `Cancelled`/`Captured` `PaymentIntent` and a `Confirmed` `Transfer` are
  both final states from this phase's own code's perspective — nothing
  executes the actual Stripe transfer to the seller's Connect account.
- **ADR-018 §2's proactive re-authorization does not exist.** Structurally
  unreachable under the current 24-hour window default (the window-close
  check always fires first), and would require inventing an unspecified
  margin threshold and stored Stripe-expiry data — documented as a known
  gap, not silently dropped.
- **No real Laravel event-listener wiring exists for any Transfers cross-
  module reaction.** `TransferInitiationService`, `TransferCaptureTriggerService`,
  and `TransferCancelTriggerService` are built complete and fully tested,
  but nothing in `apps/web` actually invokes them from a live event yet.
- **Buyer payment-method acquisition still does not exist** (carried over
  from Phase 4, unchanged) — no SetupIntent, Stripe Elements/PaymentElement
  flow, or saved-card model.
- **No device-integrity/anti-spoofing checks beyond the confirmation-time
  geofence** — mock-location detection, device attestation, and IP-
  consistency checks are explicitly out of scope, the same posture
  QueuePresence's own v1 confidence scoring took.
- **The five-minute scheduler cadence is a provisional operational
  parameter**, not a business rule — easily changed in `bootstrap/app.php`.
- **No real Stripe test-mode credentials exist in this environment**,
  unchanged from Phase 4 (§7).

### Deferred product decisions

- Whether an asymmetric or fault-based consequence should replace ADR-018
  §4's symmetric no-fault cancellation default — flagged provisional in
  the ADR itself, approved as-is for the MVP, revisitable without
  redesigning `Transfer`.
- Where and when a buyer attaches a payment method (carried over from
  Phase 4, still unscheduled).
- Protection-period duration after transfer confirmation before payout
  release, and whether a post-payout dispute can claw back funds
  (`claude-mvp-analysis.md` §10.2 item 4) — still unresolved, still blocks
  a future payout-execution phase's design.
- Per-jurisdiction legal sign-off remains a gate on enabling any market
  beyond the US-only MVP scope.

## 10. Phase 6 readiness assessment

Phase 5 provides what Phase 6 (Disputes) needs to build on:

- Every confirmation's `GeoPoint`s and timestamps, and any attached
  `TransferEvidenceRecord`, are retained on `Transfer` precisely so a
  future dispute can read them — read-only, cross-context, per
  `claude-mvp-analysis.md` §4 item 10's design. `TransferEvidenceType` is
  a deliberately extensible enum (`Photo` only today) for Phase 6 to add
  new cases to (e.g. a dispute-submitted written statement) without
  redesigning the aggregate.
- A `Transfer`'s terminal states (`Confirmed`/`Expired`/`Cancelled`) and
  the `PaymentIntent` states they drive (`Captured`/`CaptureFailed`/
  `Cancelled`) give Phase 6 a complete, queryable picture of how a given
  handoff actually resolved, without needing to reconstruct that history
  from raw infrastructure state.
- A proven event-driven cross-module reaction pattern, now verified
  consistent across four packages (Bids, Payments, Transfers reacting to
  Auctions/Payments/Transfers events respectively) — Phase 6's own
  cross-context reads can follow the same shape with confidence it holds
  up under explicit scrutiny, not just convention.
- A fifth proven package-per-bounded-context implementation
  (`packages/Transfers`), including this codebase's first genuinely new
  background-infrastructure mechanism (the Horizon-scheduled sweep) and
  its first real-PostgreSQL concurrency proof for a scheduled-writer-vs-
  request-writer race.

**No blockers identified for Phase 6 at the domain level.** The
provisional no-fault cancellation policy (ADR-018 §4) and the still-open
protection-period/clawback question are the two product decisions most
likely to shape Phase 6's own design and should be revisited before or
during Phase 6's architecture review. Per explicit instruction, Phase 6
implementation will not begin until separately authorized.
