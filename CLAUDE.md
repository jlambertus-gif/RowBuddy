# RowBuddy Development Instructions

## Product

RowBuddy is a multilingual marketplace where verified users may auction
their physical position in eligible queues.

The platform does not grant ownership of a queue position and does not
guarantee that a business or organizer will accept a transfer. RowBuddy
positions itself legally and rhetorically as a **paid line-standing /
time-brokering service** — compensation for time and effort already spent
waiting, plus a verified handoff — never as the sale of a position itself.
See `docs/decisions/003-line-standing-service-framing.md`.

The system must block restricted, unsafe, or legally sensitive queues.

## Initial locales

- English: en
- Spanish: es

English is the fallback locale.

Never hardcode user-facing strings.

All interface text, validation messages, notifications, and emails must
use translation keys.

Language, country, currency, and timezone are separate user preferences.

## Technology

- Laravel 12
- PHP 8.4
- PostgreSQL
- Redis
- Laravel Reverb
- Laravel Horizon
- React
- Inertia
- Tailwind CSS
- i18next
- Stripe Connect
- Pest
- Docker

## Architecture

Use a modular monolith. Modules communicate through domain events and
explicit application-service interfaces — never through direct cross-module
Eloquent model access. Module boundaries must be enforced by an automated
architecture test, not just convention.

Main modules:

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

Do not create microservices unless explicitly requested.

## Core business rules

- A seller must be physically present before publishing a position.
- GPS alone is not sufficient verification.
- A seller cannot auction the same position more than once.
- A seller must remain near the queue while an auction is active.
- All accepted bids are immutable.
- Prices and fees must be calculated by the server.
- Payment must remain protected until transfer confirmation.
- A transferred position cannot be transferred again in the MVP.
- Restricted queue categories must be blocked.
- Every financial and verification action must create an audit record.
- The platform must never store card details.
- User-uploaded evidence must be private by default.

## Approved architectural decisions

The following are resolved and binding for implementation (see the
corresponding ADR for full rationale and consequences):

- **Legal framing**: paid line-standing service, not position sale
  (`003-line-standing-service-framing.md`).
- **Escrow**: authorize at bid-win, capture at transfer confirmation — no
  intermediate platform-held balance. Auction/transfer windows are bounded
  by card authorization validity (~5–7 days)
  (`004-escrow-authorize-then-capture.md`).
- **Queue authorship**: hybrid — admin/partner queues publish directly;
  user-submitted queues require admin approval before any auction can be
  created against them (`005-hybrid-queue-authorship.md`).
- **Fee model**: percentage of winning bid, charged to the buyer on top of
  the bid; seller receives the full winning bid minus standard payment
  processing costs (`006-fee-model-buyer-side-percentage.md`).

Open questions that remain unresolved are tracked in
`docs/product/claude-mvp-analysis.md` §10.2.

## Localization rules

- Never hardcode user-visible text.
- Add English and Spanish translations for every new key.
- Tests must confirm translation keys exist in both languages.
- Do not store translated system content in a single text column.
- User-generated content must store its original locale.
- Use locale-aware date, time, and currency formatting.
- Never assume USD based only on the selected language.

## Security rules

- Use authorization policies.
- Validate file type and size.
- Remove metadata from uploaded images where appropriate.
- Protect evidence with signed or authenticated URLs.
- Use database transactions for bids, payments, and transfers.
- Use idempotency for payment webhooks.
- Use row-level locks where race conditions are possible.
- Never expose private evidence publicly.
- Never commit secrets.

## Development workflow

For each task:

1. Read the existing documentation and code.
2. Explain ambiguities and risks.
3. Propose a small implementation plan.
4. Modify only files required for the task.
5. Add automated tests.
6. Run the complete relevant test suite.
7. Report changed files, test results, and pending risks.

## Repository status

Phase 0 (Foundations), Phase 1 (Catalog), Phase 2 (Presence & Trust),
Phase 3 (Auctions & Bids), Phase 4 (Payments), Phase 5 (Transfers),
Phase 6 (Disputes), and Phase 7 (Ratings & Notifications) are complete
and formally accepted — tagged `v0.1.0-foundation`, `v0.2.0-catalog`,
`v0.3.0-presence`, `v0.4.0-auctions`, `v0.5.0-payments`,
`v0.6.0-transfers`, `v0.7.0-disputes`, and
`v0.8.0-ratings-notifications`. Phase 3's, Phase 4's, Phase 5's, and
Phase 6's acceptance all cover the domain/backend scope only; Phase 7's
acceptance covers domain/backend scope for Ratings and full end-to-end
scope (a real, operating delivery channel) for Notifications — see
below.

Phase 4 delivered, in `packages/Payments`: Stripe Connect Express seller
onboarding (Sprint 3); buyer payment authorization on auction win via the
separate-charges-and-transfers model, assuming a buyer Stripe
PaymentMethod id already exists (ADR-016, Sprint 4); Stripe webhook
signature verification and an idempotency ledger (Sprint 5); and payout
preparation — readiness validation plus an expected-settlement estimate,
with no payout ever executed (Sprint 6). ADR-014 keeps Payments'
lifecycle entirely independent of `Auction`'s own status (no new
`AuctionStatus` states); ADR-015 stops Phase 4 at authorization/webhooks/
payout-preparation, deferring capture and payout execution to Phase 5
(Transfers) with no placeholder trigger introduced for it; ADR-016 scopes
Sprint 4's assumed payment-method input and charge model. See
`docs/releases/phase-4-completion-report.md` for the full report.

Phase 5 delivered, in `packages/Transfers`: the `Transfer` aggregate
(`Issued → Confirmed/Expired/Cancelled`), two-sided seller/buyer
confirmation gated by a point-in-time geofence check and, for the seller,
QR validation (ADR-017); a hybrid lazy-plus-scheduled expiry evaluator —
this codebase's first Horizon-scheduled job — cancelling an unconfirmed
transfer's authorization with no charge, symmetrically and without
assigning fault (ADR-018); the Transfers-to-Payments capture contract,
extending `PaymentIntent` (Payments) from append-only to mutable with new
`Captured`/`CaptureFailed`/`Cancelled` states, plus a defensive Stripe
webhook reconciliation path (ADR-019); and a first-class, extensible
photo-evidence model attachable regardless of confirmation status, in
anticipation of Phase 6 (ADR-020). A mid-phase correction moved the
Stripe capture trigger off `TransferConfirmationService` into a dedicated
`TransferCaptureTriggerService` after a requested verification found it
was reading `Transfer`'s status directly rather than reacting to the
committed `TransferConfirmed` event — every cross-module reaction in this
phase is now driven by a committed domain event. Seller payout execution,
buyer payment-method acquisition, proactive re-authorization before
Stripe's own expiry, and real Laravel event-listener wiring for any
cross-module reaction are all explicitly deferred. See
`docs/releases/phase-5-completion-report.md` for the full report.

Phase 6 delivered, in `packages/Disputes`: the `Dispute` aggregate
(`Opened → Resolved`, terminal — a deliberate two-state collapse of the
original `opened`/`under_review` product sketch, since no approved
decision gates behavior between them), buyer-only filing against a
`Confirmed`-only transfer within a swappable filing-deadline window, and
manual-only admin resolution to one of four outcomes (release to seller,
refund to buyer, split, cancellation) — Disputes executes an
administrator's explicit choice, it never decides one itself (ADR-021).
`TransferCaseLookup` extends the "consumer owns the port" pattern a
fourth hop into Transfers with zero new Transfers-side API. The
event-driven reactor split (`DisputeResolutionService` mutates/persists/
publishes only; `DisputeRefundTriggerService` is the real `DisputeResolved`
consumer) was applied correctly from the start, unlike Phase 5's own
mid-phase correction. `PaymentIntent` (Payments) gained a further
lifecycle extension: a single `Refunded` status regardless of whether the
amount is full or partial, a persisted and reconstructible
`refundedAmount`, and computed `remainingCapturedAmount()` — backed by
deterministic Stripe idempotency keys and an explicit
validate→call-Stripe→mutate→persist execution order (ADR-022), this
codebase's first explicit answer to "what if the external call succeeds
but the local commit fails." Real Stripe chargeback precedence/
reconciliation, evidence retention/deletion, any automated or
rules-based resolution, and dispute-specific evidence submission are all
explicitly deferred (ADR-023). See
`docs/releases/phase-6-completion-report.md` for the full report.

Phase 7 delivered two independent bounded contexts, after eleven product
decisions frozen individually before any ADR was drafted (mirroring
Phase 6's own process). In `packages/Ratings`: the `Rating` aggregate —
symmetric, immutable, no state machine, unlike `Dispute`'s lifecycle,
since a rating carries no financial claim (ADR-024 §1); domain-enforced
1–5 score and an optional, bounded (1,000-char), untouched comment
(ADR-024 §6); `RatingSubmissionService` (`Confirmed`-only eligibility,
buyer/seller-only authorization, server-side `rateeId` derivation) via
Ratings' own `TransferParticipantLookup` read port, independent of
Disputes' `TransferCaseLookup` and of Notifications' own separate copy
of a port with the same name; and `RatingRevealEvaluator` — double-blind
reveal computed lazily at read time, anchored to each rating's own
`submittedAt`, no persisted flag, no scheduler (ADR-024 §5, corrected
before implementation from an initial `Transfer.confirmedAt` anchor). In
`packages/Notifications`: exactly eight approved (event, recipient)
pairs, each wired via this codebase's first real Laravel
`Event::listen()`-driven, queued cross-module reaction (ADR-025 §6);
`NotificationDeliveryLedger`, this codebase's first idempotency
mechanism built for a notification rather than a payment, keyed by
`(domain_event_id, recipient_id, notification_type)` (ADR-025 §7);
`NotificationDeliveryPipeline`, the shared delivery sequence every
listener uses; every email template self-contained and
field-restricted, rendered exclusively from the recipient's own stored
locale preference (ADR-025 §9/§10); bounded retry relying on Laravel's
own `failed_jobs`, no bespoke failure tracking (ADR-025 §11). Ratings
closes domain/backend scope only; Notifications is the first phase in
this codebase required to prove a real, operating delivery channel to
close — two deliberately different exit bars, decided explicitly
(ADR-024 §7/ADR-025 §8). See `docs/releases/phase-7-completion-report.md`
for the full report.

By deliberate decision, HTTP, frontend, Reverb, and a manual browser
acceptance test were deferred to a later delivery-layer phase rather than
required for Phase 3's closure — a departure from Phases 1 and 2's
closure bar — and Phases 4, 5, 6, and Ratings within Phase 7 follow the
same posture, with two exceptions: Phase 4 Sprint 5 introduced a real
HTTP endpoint (`POST /webhooks/stripe`) since receiving Stripe webhooks
genuinely requires one, and Notifications within Phase 7 required a
real, operating email delivery channel end-to-end, since a notification
with no real delivery isn't a notification at all; Phases 5 and 6
required no new HTTP surface at all. See
`docs/releases/phase-3-completion-report.md`,
`docs/releases/phase-4-completion-report.md`,
`docs/releases/phase-5-completion-report.md`,
`docs/releases/phase-6-completion-report.md`, and
`docs/releases/phase-7-completion-report.md` for the full reports and
rationale. Phase 8 (Administration & Fraud/Risk v1) implementation
requires separate authorization before it begins. See
`docs/roadmap.md` for the phase plan and sprint progress, and
`docs/releases/phase-1-completion-report.md` /
`docs/releases/phase-2-completion-report.md` /
`docs/releases/phase-3-completion-report.md` /
`docs/releases/phase-4-completion-report.md` /
`docs/releases/phase-5-completion-report.md` /
`docs/releases/phase-6-completion-report.md` /
`docs/releases/phase-7-completion-report.md` for the completion reports
of the closed phases.
