# ADR 025: Notifications MVP Scope, Channel, and Event Set

## Status

Accepted (2026-07-30). All Phase 7 product decisions affecting
Notifications (Decisions 1, 6, 7, 8, 9, 10, and 11 of the Phase 7
decision set) are frozen and recorded below. Accepted together with
ADR-024 (Ratings) after joint final architectural review.

## Context

`docs/product/mvp-scope.md` does not mention Notifications explicitly,
leaving open whether it belongs in the MVP at all. The Phase 7
architecture review (this conversation, §4) identified this as the
second open product decision, after Ratings' architecture review
findings but before any Ratings-specific decision was frozen.

Notifications is architecturally different from every module closed so
far (Auctions, Bids, Payments, Transfers, Disputes): Phases 3–6 all
deliberately deferred HTTP, frontend, and real Laravel event-listener
wiring to a later delivery-layer phase, closing at the domain/backend
boundary. A notification's entire purpose is to actually deliver
something to a real recipient — there is no meaningful "domain/backend
only" version of Notifications. Phase 7 is therefore the first phase
where a real, working delivery channel is part of the closure bar
itself, not a deferred concern.

## Decision

### 1. Notifications is in the MVP, deliberately narrowed

Notifications is part of the MVP, but Phase 7 intentionally scopes it
to:

- One delivery channel: **email only**.
- A small, explicitly approved set of domain events (see Decision 6).
- **Transactional notifications only** — no marketing or engagement
  content.
- **Locale-aware templates**, consistent with this project's standing
  localization rules (English/Spanish, English fallback, no hardcoded
  user-facing strings).
- **Event-driven delivery** through the existing after-commit
  domain-event architecture already established in Auctions, Bids,
  Payments, Transfers, and Disputes — no new dispatch mechanism.

Explicitly out of scope for Phase 7: push notifications, SMS, marketing
campaigns, preference centers, scheduled/bulk notifications, digests,
and multi-channel routing.

Unlike Phases 3–6, **successful completion of Phase 7 requires at least
one real delivery channel to be operational, tested, and integrated** —
the "domain/backend only" closure bar used by every prior phase does not
apply here.

### 6. The approved MVP notification event set

Exactly eight (event, recipient) pairs are in scope for Phase 7, each
already published as a domain event by an existing module — no new
domain event is introduced solely to support notifications:

| Event | Recipient(s) |
|---|---|
| `AuctionWon` | Winner (buyer) |
| `PaymentAuthorizationFailed` | Buyer |
| `TransferIssued` | Buyer and Seller |
| `TransferConfirmed` | Buyer and Seller |
| `TransferExpired` | Buyer and Seller |
| `TransferCancelled` | Buyer and Seller |
| `DisputeOpened` | Seller |
| `DisputeResolved` | Buyer and Seller |

**Selection criteria** — an event qualifies only if at least one of the
following holds:

- the event is financially significant, or
- the user must take action, or
- the user should receive a definitive transactional outcome.

Explicitly excluded from this set, and requiring a new, separately
approved decision before ever being added:

- Bid / outbid notifications (engagement-adjacent, not transactional).
- Marketing or engagement notifications of any kind.
- Rating submission notifications.
- Rating reveal notifications (ADR-024 §5's double-blind reveal has no
  Phase 7 notification of its own — a party learns of a reveal by
  reading, not by being told; see ADR-024 §5's own note that proactive
  reveal notification is deferred to a later phase, should one require
  a scheduler for it).
- Reminder, digest, or any scheduled notification.

`TransferConfirmed` is included deliberately, despite both parties being
physically present and interacting at the moment of confirmation itself:
it represents the successful completion of the marketplace transaction
and its associated payment capture (ADR-019), and both parties should
receive a durable, transactional confirmation record independent of
whatever happened during the in-person handoff — the notification is
evidence of the platform's own recorded outcome, not a substitute for
information already available in person.

### 7. Notification delivery is idempotent via a logical-identity delivery ledger; no separate audit record

Because Notifications introduces this codebase's first real
`Event::listen()` wiring (§Consequences), its listener is queued, and a
queued job can retry after a transient failure even after it already
delivered successfully — the same duplicate-delivery risk this project
has already solved twice: Stripe webhook processing
(`ProcessedWebhookEvent`, Phase 4 Sprint 5) and dispute-refund idempotency
([[feedback_financial_safety_rigor]], Phase 6 Sprint 5, ADR-022).

Delivery must be idempotent. A delivery ledger — one row per logical
notification — is checked before send and written after, preventing a
duplicate email regardless of how many times the underlying event is
redelivered or the queued job is retried.

The ledger's key is a **stable logical identity**: the originating
domain event's identifier, together with the recipient and the
notification type/template — not a transport-specific identifier (e.g.,
not a mail-provider message id, which only exists *after* a send
attempt and therefore cannot itself prevent a duplicate attempt). This
mirrors `ProcessedWebhookEvent`'s own choice to key on a stable
upstream identifier rather than anything generated by the side effect
itself.

**No separate audit record is required.** The delivery ledger is an
operational correctness mechanism — it answers "was this already sent"
— not a business audit trail. The platform's audit requirement
(CLAUDE.md: "every financial and verification action must create an
audit record") is already satisfied by the originating business action
itself (the auction win, payment authorization failure, transfer
issuance/confirmation/expiry/cancellation, or dispute filing/resolution)
at the moment it occurred — Notifications reacts to an already-audited
fact, it does not need to audit its own reaction a second time.

### 8. Notifications remains the sole Phase 7 exception requiring end-to-end delivery

Phase 7 was explicitly evaluated on whether Ratings should share
Notifications' real-delivery closure bar (Decision 1), purely because
the two modules ship in the same phase. The answer is no: Ratings closes
domain/backend-complete, exactly like Auctions/Bids/Payments/Transfers/
Disputes before it (ADR-024 §7) — Notifications' exception is not a
general "Phase 7 modules get upgraded scope" rule, it is reasoned
specifically from what a notification *is*. A notification with no real
delivery isn't a notification at all; a rating record is fully
meaningful and testable with no submission UI.

**Phase 7 therefore has two different exit bars, by design**: Ratings
closes domain/backend-complete; Notifications closes only once
end-to-end delivery through a real email channel is operational, tested,
and integrated. This asymmetry is deliberate, not an inconsistency to
reconcile in a later sprint.

### 9. Notification email content is self-contained, explicit per-template, and field-restricted

Because no HTTP/frontend exists yet for Auctions, Payments, Transfers, or
Disputes (ADR-024 §7/§8), a Phase 7 notification email is not a
supplement to an in-app page for these events — it is the only
user-facing artifact that will exist for them. Emails must therefore be
self-contained: **no placeholder or dead links to application pages that
do not yet exist.** Each of the eight templates (ADR-025 §6) must include
enough inline information for the recipient to understand the event,
its financial or transactional effect, and any required next step, with
no link at all where no real page exists to link to.

Template content is **explicitly defined per notification type**, not
assembled by serializing an event's payload — each template names
exactly the fields it renders. Only purpose-required fields may be
included. Allowed examples: a user-facing transaction/transfer
reference, the relevant status or outcome, a locale-formatted monetary
amount, locale-and-timezone-formatted dates/deadlines, and clear
next-step instructions when action is required.

**Explicitly prohibited in any template:** card or payment-method
details; raw Stripe or other provider identifiers; internal database
identifiers not intended for users; evidence files, evidence references,
or private dispute-response content; stack traces, exception messages,
or other internal failure details; and any unrestricted serialization of
a domain event's full payload.

`PaymentAuthorizationFailed` in particular must communicate the
user-facing consequence (the win is at risk; a next step exists) and the
next step itself, without exposing the underlying gateway failure detail
(e.g., the raw Stripe decline reason/code) — the domain fact "the
authorization failed" is user-facing; the gateway's own explanation of
why is not.

When a real frontend eventually exists for these modules, templates may
be revised to add valid deep links — a template content change, not an
architectural one. Phase 7 must not depend on any such link existing.

### 10. Notification rendering is governed solely by the recipient's own stored locale preferences

Every notification is rendered **independently per recipient** — for a
two-recipient event (`TransferIssued`, `TransferConfirmed`,
`TransferExpired`, `TransferCancelled`, `DisputeResolved`), the buyer's
and seller's copies are two separate rendering passes, each using that
recipient's own stored language, country, currency, and timezone
preference, exactly per CLAUDE.md's existing rule that these are
"separate user preferences." Locale-aware formatting applies to every
date, time, and monetary value in the template. If a recipient's
preference is missing, the existing platform fallback rules apply
(English is the fallback locale, per CLAUDE.md).

User-generated content embedded in a notification, if any (none of the
eight approved events currently carry any — rating comments are
explicitly excluded from this event set, ADR-025 §6), would remain in
its original language and would not be translated, per this project's
standing rule that user-generated content stores and displays its
original locale (mirrors ADR-024 §6's identical rule for rating
comments).

**Notifications must never resolve locale from**: the initiating user's
locale (the actor whose action raised the event may not be the
recipient), the current HTTP request locale (most of these listeners
run queued, off-request, with no HTTP request in scope at all), the
auction/transaction's own locale (no single coherent value exists for a
two-party event), or any locale value carried on the event payload
itself. The notification service resolves the recipient's localization
context freshly, immediately before template rendering, from the
recipient's own stored preference — never from any value attached to
the triggering event or its actor.

### 11. Delivery failure relies entirely on Laravel's existing queue infrastructure — no bespoke failure tracking

Notification delivery uses a bounded retry policy: an explicit, finite
retry count and backoff strategy configured directly on each
notification job (Laravel's standard `$tries`/backoff mechanism), not an
unbounded or ad hoc retry loop.

After all retries are exhausted, the job fails normally into Laravel's
existing `failed_jobs` mechanism — the same platform-wide facility every
other queued job in this codebase already relies on. **No
notification-specific audit table or failure ledger is created**,
consistent with Decision 7's refusal to build a separate audit record
for Notifications generally. No admin UI, alerting, escalation path, or
secondary delivery channel is part of Phase 7.

This is an explicit, accepted operational limitation of the MVP, not an
oversight: a permanently failed `PaymentAuthorizationFailed`,
`DisputeOpened`, or `DisputeResolved` email currently has no proactive
surfacing to anyone, only a durable, inspectable trace in `failed_jobs`
that requires someone to go looking. Building proactive alerting now
would have no consumer — Administration has not started (Repository
Status) and no observability tooling exists yet for any module. A future
Administration or Operations capability may consume `failed_jobs` (or
other platform observability) to add monitoring and remediation without
requiring any change to the Notifications bounded context itself.

## Consequences

- `packages/Notifications` will need a listener/subscriber per
  domain event in the table above, each translating a committed domain
  event into a locale-aware email — the first real Laravel
  `Event::listen()` wiring in this codebase (every prior cross-module
  reaction has been a directly-invoked application service, never a
  framework-level listener), since genuine asynchronous delivery is the
  point.
- Because `TransferExpired`/`TransferCancelled` are explicitly no-fault
  (ADR-018), the email content for both must remain symmetric and
  blame-free, mirroring the aggregate's own posture — Notifications does
  not get to editorialize an outcome the domain model deliberately
  declined to attribute fault for.
  `DisputeResolved`'s email content must likewise report only the
  administrator's chosen outcome (ADR-021 §6), never a inferred
  judgment of its own.
- Adding a ninth event later (e.g., a future Ratings or Fraud & Risk
  notification) requires a new, separately approved decision — this
  table is not a starting point to be organically extended.
- Locale-aware templates require English and Spanish translations for
  all eight event templates before Phase 7 can close, per CLAUDE.md's
  localization rules ("Add English and Spanish translations for every
  new key," "Tests must confirm translation keys exist in both
  languages").
- `packages/Notifications` persists a delivery ledger keyed by
  `(domainEventId, recipient, notificationType)`; the queued listener
  must check this ledger before sending and record it after — the
  design must include an explicit duplicate-delivery test (two
  deliveries/retries of the same logical event → exactly one email),
  the same discipline `feedback_financial_safety_rigor` established for
  external-call retries generally.
- No `NotificationAuditRecord`-style entity is introduced; any future
  need for delivery observability (bounce tracking, open/click
  analytics) is a distinct, separately-scoped decision, not an implicit
  extension of the delivery ledger.
- Each of the eight templates is backed by an explicit, named view-model
  (per-template fields only, never a raw event payload passed to a
  view) and a test asserting the rendered output contains none of the
  prohibited categories listed in Decision 9 — this is a security
  boundary (CLAUDE.md: "never expose private evidence publicly," "the
  platform must never store card details"), not merely a style
  preference.
- The notification service needs a read port into recipient locale
  preference (language/country/currency/timezone), resolved fresh per
  recipient immediately before rendering — never sourced from the
  triggering event's payload or actor. Each of the two-recipient
  templates must be tested with the buyer and seller holding two
  different stored preferences, proving the two rendered copies differ
  correctly rather than sharing one locale.
- Each of the eight notification jobs sets an explicit, finite
  `$tries`/backoff configuration — no default/unbounded retry behavior
  — and a test proves that once retries are exhausted the job lands in
  `failed_jobs` rather than retrying indefinitely or throwing
  uncaught.
- This ADR records, as an explicit accepted limitation, that Phase 7
  ships with no proactive failure alerting of any kind — the same
  "documented deferral, not silence" discipline ADR-023 established for
  chargeback reconciliation.

## References

- ADR-018 (no-fault expiry/cancellation — the posture
  `TransferExpired`/`TransferCancelled` notification content must not
  contradict)
- ADR-019 (the capture contract `TransferConfirmed`'s notification
  content refers to as the transaction's completion)
- ADR-021 §3/§6 (the dispute response window `DisputeOpened`'s
  notification exists to surface, and the manual-only resolution
  `DisputeResolved`'s notification must report verbatim)
- ADR-024 §5 (Ratings' double-blind reveal — the reason rating-reveal
  notifications are explicitly excluded from this event set)
- ADR-024 §7 (Ratings' domain/backend-only closure bar — the precedent
  Decision 8/§8 above declines to extend Notifications' own real-delivery
  exception to)
- Phase 7 architecture review (this conversation, §4)
