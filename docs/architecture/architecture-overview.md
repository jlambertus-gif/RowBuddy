# Architecture Overview

## Implementation status

- **Identity**: implemented (Phase 0) — Fortify-backed authentication.
- **Queues**: implemented (Phase 1, `packages/Queues`) — submission,
  jurisdiction/restricted-category gating, moderation, PostGIS geospatial
  discovery, Inertia/React frontend. See
  `docs/releases/phase-1-completion-report.md`.
- **QueuePresence**: implemented (Phase 2, `packages/QueuePresence`) — GPS
  presence capture, evidence-photo capture with private storage and
  signed URLs, the v1 confidence-scoring engine (ADR-008), Inertia/React
  frontend. See `docs/releases/phase-2-completion-report.md`.
- **Audit**: partially implemented (Phase 2, Phase 8) — a minimal,
  platform-wide append-only sink driven by the shared `AuditableAction`
  interface (Phase 2), now presented through Administration's own
  `AuditEventDisplayRegistry` (Phase 8, 36 explicit per-event-type
  allowlists, fail-closed for any unregistered type) — presentation only,
  the sink itself is unchanged. Case management, partitioning/archival,
  and any Fraud-Risk-adjacent tooling beyond display remain unbuilt.
- **Auctions**: implemented, domain/backend scope only (Phase 3,
  `packages/Auctions`) — the `Auction` aggregate's Open/Closing/Won/
  Expired/Cancelled state machine, an explicit required `closesAt`, and
  the immutable accepted-bid invariant (Sprint 1, extended Sprint 6); the
  `AuctionRepository` persistence layer, including `findByIdForUpdate()`'s
  row-lock support for Bids (Sprint 2, extended Sprint 5); the ADR-009
  `SellerPresenceVerification` read contract and its `apps/web` adapter,
  plus the ADR-010 Evidence Verified gate in `AuctionService::open()`
  (Sprint 3); the ADR-011 `LiveProximityChecker` implementing the two-tier
  proximity policy (Sprint 4), actively invoked by Bids' bid placement
  (Sprint 5); the ADR-013 `AuctionClosingEvaluator`/`SoftCloseExtender`/
  `WinningBidLookup` implementing closing, winner selection, expiry, and
  anti-sniping under the same lock (Sprint 6). The full backend/domain
  exit criteria from `docs/roadmap.md` are met and formally accepted,
  tagged `v0.4.0-auctions`. No HTTP, no frontend, no Reverb — deliberately
  deferred to a later delivery-layer phase, not built yet. See
  `docs/releases/phase-3-completion-report.md`.
- **Bids**: implemented, domain/backend scope only (Phase 3,
  `packages/Bids`) — an immutable, append-only `Bid` aggregate;
  `BidService::place()` orchestrating concurrency-safe placement inside
  one transaction (auction-row lock → `LiveProximityChecker` → closing
  evaluation → highest-bid lookup → validation → insertion → soft-close
  effects, ADR-012/ADR-013); `BidPlacementOutcome` keeping expected
  rejections from rolling back a legitimate proximity or closing
  transition; after-commit event publication. No dependency on Auctions'
  internals — only through `AuctionGateway`, implemented in `apps/web`.
  No HTTP, no frontend — deferred alongside Auctions', see
  `docs/releases/phase-3-completion-report.md`.
- **Payments**: implemented, domain/backend scope only (Phase 4,
  `packages/Payments`) — the `PaymentIntent` aggregate modeling
  authorization only (`Authorized`/`Failed`; no `Captured`/`Held`/
  `ReleasedToSeller`/`RefundedToBuyer` exist in code, per ADR-015);
  `SellerPayoutAccount` and a `ConnectAccountGateway` Stripe adapter for
  Connect Express onboarding, with eligibility always read live from
  Stripe, never cached (Sprint 3); `AuctionWinAuthorizationService`, the
  real `AuctionWon` consumer, authorizing the buyer's total (bid + fee)
  via the separate-charges-and-transfers model with no dependency on
  seller Connect status, assuming an already-obtained Stripe PaymentMethod
  id (ADR-016, Sprint 4); `WebhookSignatureVerifier` and
  `StripeWebhookProcessor` — real local HMAC-SHA256 verification and a
  `ProcessedWebhookEvent` idempotency ledger keyed by Stripe event id
  (Sprint 5); `PayoutPreparationService`, combining authorization,
  account-linkage, and live eligibility into a read-only readiness
  snapshot plus an expected-settlement estimate — no payout ever executed
  (Sprint 6). Payments tracks its own lifecycle keyed by `auctionId`/
  `winningBidId` (ADR-014) — `Auction` gained no new states. Capture,
  transfer confirmation, and payout execution were deferred to Phase 5
  (Transfers) at this phase's own close; Phase 5 has since extended
  `PaymentIntent`'s lifecycle to add capture/cancellation (see below).
  One real HTTP endpoint exists (`POST /webhooks/stripe`, Sprint 5) —
  otherwise no HTTP, no frontend. See
  `docs/releases/phase-4-completion-report.md`.
- **Transfers**: implemented, domain/backend scope only (Phase 5,
  `packages/Transfers`) — the `Transfer` aggregate modeling the handoff
  from seller to buyer (`Issued → Confirmed/Expired/Cancelled`, all
  terminal; ADR-017), with two-sided confirmation (only the second
  party's confirmation reaches `Confirmed`) and first-class, repeatable
  photo evidence (`attachEvidence()`, orthogonal to the confirmation state
  machine, ADR-020 §4); `TransferInitiationService`, the real
  `PaymentAuthorized` consumer, issuing a buyer-held QR whose plaintext is
  never persisted (ADR-017 §5); `TransferConfirmationService`, limited to
  validation, mutation, persistence, and event publication after a
  mid-phase correction moved its original inline capture trigger into a
  dedicated `TransferCaptureTriggerService` — every cross-module reaction
  in this package (capture, cancellation) is driven by a committed domain
  event, never by reading an aggregate's own status; `TransferExpiryEvaluator`,
  invoked both lazily and via this codebase's first Horizon-scheduled job
  (ADR-018, justified against three simpler alternatives); a defensive
  `StripeCancellationReconciliationService` reconciling Stripe webhooks
  against the same transitions the scheduler uses (ADR-019 §7);
  `TransferEvidenceStorage`/`ImageMetadataStripper`, Transfers' own copies
  of QueuePresence's Phase 2 evidence-handling ports (ADR-020 §4). Also
  extends `PaymentIntent` (Payments) from append-only to mutable, adding
  `Captured`/`CaptureFailed`/`Cancelled` (ADR-019). Seller payout
  execution, buyer payment-method acquisition, proactive
  re-authorization, and real Laravel event-listener wiring are all
  explicitly deferred. No HTTP, no frontend. See
  `docs/releases/phase-5-completion-report.md`.
- **Disputes**: implemented, domain/backend scope only (Phase 6,
  `packages/Disputes`) — the `Dispute` aggregate (`Opened → Resolved`,
  terminal; a deliberate collapse of the original `opened`/
  `under_review` product sketch, since no approved decision gates
  behavior between them), with `attachEvidence()` guarded closed once
  resolved (unlike `Transfer`'s status-independent evidence) and
  `resolve()` enforcing outcome/refund-amount consistency across all
  four resolution outcomes (ADR-021); `TransferCaseLookup`, the
  Disputes-owned read port into Transfers, extending the "consumer owns
  the port" pattern a fourth hop with zero new Transfers-side API
  (ADR-021 §2); `DisputeFilingService` (buyer-only, `Confirmed`-only,
  deadline-gated) and `DisputeResolutionService`/
  `DisputeRefundTriggerService` — the event-driven reactor split applied
  correctly from the start, unlike Phase 5's own mid-phase correction
  (ADR-021 §6). Also extends `PaymentIntent` (Payments) again: a single
  `Refunded` status regardless of amount, a persisted and
  reconstructible `refundedAmount`, and computed
  `remainingCapturedAmount()` (ADR-022) — backed by deterministic Stripe
  idempotency keys and an explicit validate→call-Stripe→mutate→persist
  execution order, this codebase's first explicit answer to "what if the
  external call succeeds but the local commit fails." Chargeback
  precedence/reconciliation, evidence retention/deletion, automated
  resolution, and dispute-specific evidence submission are all
  explicitly deferred (ADR-023). No HTTP, no frontend. See
  `docs/releases/phase-6-completion-report.md`.
- **Ratings**: implemented, domain/backend scope only (Phase 7,
  `packages/Ratings`) — the `Rating` aggregate: symmetric, immutable,
  no state machine, unlike `Dispute`'s `Opened → Resolved` lifecycle,
  since a rating carries no financial claim and needs no resolution
  (ADR-024 §1); domain-enforced 1–5 integer score (`RatingScore`) and an
  optional, bounded (1,000-char), untouched comment normalizing blank
  input to `null` (ADR-024 §6); `RatingSubmissionService`
  (`Confirmed`-only eligibility, buyer/seller-only authorization,
  server-side `rateeId` derivation) via Ratings' own
  `TransferParticipantLookup` read port — independent of Disputes'
  identically-purposed `TransferCaseLookup`, and independent of
  Notifications' own separate copy of a port with the same name
  (ADR-024 §2/§4/§7); `RatingRevealDeadlinePolicy`/
  `RatingRevealEvaluator` — double-blind reveal computed lazily at read
  time (immediate once a counterpart exists, deadline-based otherwise,
  anchored to each rating's own `submittedAt`), no persisted "revealed"
  flag, no scheduler (ADR-024 §5). No HTTP, no frontend. See
  `docs/releases/phase-7-completion-report.md`.
- **Notifications**: implemented, the first phase-7 module requiring
  real end-to-end delivery to close (Phase 7, `packages/Notifications`)
  — exactly eight approved (event, recipient) pairs (`AuctionWon`,
  `PaymentAuthorizationFailed`, `TransferIssued`/`Confirmed`/`Expired`/
  `Cancelled`, `DisputeOpened`/`Resolved`), each wired via this
  codebase's first real Laravel `Event::listen()`-driven, queued
  cross-module reaction (ADR-025 §6/Consequences); `NotificationDeliveryLedger`,
  a logical-identity idempotency mechanism keyed by `(domain_event_id,
  recipient_id, notification_type)` — this codebase's first idempotency
  mechanism built for a notification rather than a payment (ADR-025
  §7); `NotificationDeliveryPipeline`, the shared ledger-check →
  contact-resolve → locale-resolve → render → send → record sequence
  every listener uses; every email template is self-contained and
  field-restricted (no gateway failure detail, no cancellation reason,
  no admin resolution notes — ADR-025 §9), rendered exclusively from the
  recipient's own stored locale preference (minimal, nullable,
  generic `users` columns added for exactly this purpose — ADR-025
  §10); bounded retry relying on Laravel's own `failed_jobs`, no
  bespoke failure tracking or alerting (ADR-025 §11). No HTTP, no
  frontend. See `docs/releases/phase-7-completion-report.md`.
- **Administration**: implemented, with real HTTP/UI throughout (Phase 8,
  `packages/Administration`) — a closed `AdminRole` enum and
  `AdminRoleCapabilityMap` kept separate from role identity, one Gate per
  `AdminCapability` case (Sprint 1); `AccountSuspensionService` enforced
  across independent `AccountStandingLookup` ports owned by Bids, Queues,
  and Ratings (Sprint 2); `RestrictedCategoryActivationService`/
  `JurisdictionRuleActivationService`, narrow Queues-owned write
  capabilities Administration orchestrates but never bypasses, backed by
  an additive `jurisdiction_rules.active` column (Sprint 3);
  `DisputeCaseLookup` (via Disputes' own repository) and
  `DisputeCorrectionService` — read-only case/evidence review plus a
  correction note that never mutates `Dispute.Resolved` (Sprint 4);
  `AuditEventDisplayRegistry`, 36 explicit per-event-type display
  allowlists over the existing audit sink, fail-closed for any
  unregistered type (Sprint 5). Real, capability-gated HTTP endpoints and
  Inertia/React pages exist for every capability that needs one — this
  phase's own exception to the domain/backend-only closure bar, alongside
  Notifications in Phase 7. Automated Fraud & Risk scoring is explicitly
  deferred beyond MVP (ADR-026 §1). See
  `docs/releases/phase-8-completion-report.md`.
- All other modules below: not started.

## Style

Modular monolith with event-driven communication between modules.

## Main modules

- Identity
- Localization
- Queues
- QueuePresence
- Verification
- Auctions
- Bids
- Payments
- Transfers
- Disputes
- Ratings
- Notifications
- Administration
- Audit
- Fraud & Risk

## Module boundary rules

- No module accesses another module's Eloquent models directly.
- Cross-module reads/writes go through the other module's application-service
  interface, or are reconstructed locally from published domain events.
- All cross-module side effects are expressed as domain events, dispatched
  through Laravel's event system and queued via Horizon where not required
  to be synchronous.
- Module boundaries are enforced by an automated architecture test from
  Phase 0 onward, so the option to extract a module into a separate service
  later (per `decisions/001-modular-monolith.md`) remains real.

## Shared kernel

Value objects shared across modules, kept dependency-free of any single
module: `Money`/`Currency`, `GeoPoint`/`Geofence`, `TranslatableText`,
`Locale`.

## Infrastructure

- Laravel 12
- PHP 8.4
- React + Inertia
- Tailwind CSS
- PostgreSQL (with PostGIS for geospatial queue discovery)
- Redis (separate logical use for cache, session, and queue)
- Laravel Reverb
- Laravel Horizon
- Stripe Connect
- S3-compatible storage
- Docker

## Full analysis

See `docs/product/claude-mvp-analysis.md` for the complete bounded-context
breakdown, database model, state machines, and phase plan.
