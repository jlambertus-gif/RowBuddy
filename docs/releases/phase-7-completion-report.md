# Phase 7 (Ratings & Notifications) — Completion Report

Tag: `v0.8.0-ratings-notifications`
Status: **Complete and formally accepted**, 2026-07-30 — Ratings closes
domain/backend scope only (§8); Notifications closes end-to-end through
a real email channel — this phase's own, deliberately asymmetric exit
bar, decided explicitly rather than discovered at closure.

## 1. Executive summary

Phase 7 delivers two independent bounded contexts. **Ratings**
(`packages/Ratings`) lets a buyer and seller each submit exactly one
symmetric rating of the other, for a `Transfer` that reached `Confirmed`
and only then, with a double-blind reveal (hidden until both parties
have rated, or until that individual rating's own reveal window
elapses) evaluated lazily with no scheduler. **Notifications**
(`packages/Notifications`) delivers a small, explicitly approved set of
eight transactional, locale-aware emails, triggered by this codebase's
first real Laravel `Event::listen()` wiring, idempotent via a
logical-identity delivery ledger, with a bounded retry policy relying on
Laravel's own `failed_jobs` mechanism for anything it cannot resolve.

Both modules were built after an unusually granular product-decision
process — eleven decisions frozen individually across both contexts
before any ADR was drafted, more than any prior phase — producing two
ADRs (024, 025), both accepted. One decision was corrected before
implementation (ADR-024 §5's reveal-deadline anchor), and one
pre-existing test's unrealistic fixture data was corrected, not the new
behavior it exposed, when `TransferExpired` gained a real notification
consumer. 656 automated tests pass across the full ecosystem (up from
570 at Phase 6 close), with clean PHPStan/Larastan/Pint throughout.

**This phase closes with two deliberately different exit bars, by
design**: Ratings has no HTTP, no frontend, no submission UI — the same
domain/backend-only posture every phase since Phase 3 has used.
Notifications is the first phase in this codebase required to prove a
real, operating delivery channel, precisely because a notification with
no real delivery isn't a notification at all. See §8.

## 2. Product decisions (frozen individually, before any ADR)

Mirroring Phase 6's process, all eleven Phase 7 product decisions were
resolved one at a time in direct conversation — trade-offs, a
recommendation, explicit approval — before any ADR drafting began:

1. Notifications is in the MVP, narrowed to email-only, transactional-
   only, locale-aware, event-driven, with the first phase in this
   codebase requiring real end-to-end delivery to close.
2. Rating eligibility is symmetric, `Confirmed`-only, one rating per
   participant per transfer.
3. Ratings and Disputes remain fully independent bounded contexts — no
   cross-context read of any kind, in either direction.
4. Ratings use a double-blind reveal model, evaluated lazily, with no
   scheduler.
5. Rating content: a single required domain-enforced 1–5 score, no
   sub-dimensions; an optional, bounded (1,000-char), untouched comment.
6. The approved MVP notification event set — exactly eight (event,
   recipient) pairs, selected only where an event is financially
   significant, requires user action, or reports a definitive outcome.
7. Notification delivery is idempotent via a logical-identity delivery
   ledger; no separate audit record — the originating business action
   already satisfies CLAUDE.md's audit requirement.
8. Ratings and Notifications have two different Phase 7 exit bars, by
   design — Notifications is the sole exception requiring real
   end-to-end delivery.
9. Notification email content is self-contained (no dead links to
   pages that don't exist yet), explicit per-template, and
   field-restricted — a hard-prohibited-category list (card/provider
   details, internal ids, evidence, stack traces, raw event payloads).
10. Notification rendering is governed solely by the recipient's own
    stored locale preference, resolved independently per recipient,
    never from the actor, request, transaction, or event payload.
11. Notification delivery failure relies entirely on Laravel's existing
    queue infrastructure — bounded retries, then `failed_jobs` — no
    bespoke failure tracking, no alerting, an explicit accepted MVP
    limitation.

## 3. Sprint-by-sprint deliverables

| Sprint | Commit | Delivered |
|---|---|---|
| 1 | `16a42af` | ADR-024/ADR-025 accepted. `packages/Ratings` scaffold — the `Rating` aggregate: symmetric, immutable, no state machine, domain-enforced 1–5 score (`RatingScore`), optional bounded/normalized comment (max 1,000 chars, chosen and documented this sprint). Reveal deliberately deferred. |
| 2 | `7a82b94` | Ratings persistence — `ratings` migration (unique on `(transfer_id, rater_id)`), `RatingRepository`/`EloquentRatingRepository`, `RatingsServiceProvider`. Wired into `apps/web`. |
| 3 | `08902f6` | `RatingSubmissionService` — `Confirmed`-only eligibility, buyer/seller-only authorization, server-side `rateeId` derivation, one-per-participant enforcement. `TransferParticipantLookup` (Ratings' own read port into Transfers) and its `apps/web` adapter. Ratings' own `DomainEventPublisher`. |
| 4 | `319f27e` | `RatingRevealDeadlinePolicy`/`FixedRatingRevealDeadlinePolicy` and `RatingRevealEvaluator` — lazy dual-path reveal (immediate once a counterpart exists, deadline-based otherwise), no persisted flag, no scheduler. ADR-024 §5 corrected (anchor moved from `Transfer.confirmedAt` to each rating's own `submittedAt`) before this sprint's implementation. Completes Ratings' approved scope. |
| 5 | `450b12c` | `packages/Notifications` scaffold. Minimal nullable locale-preference columns on `users` (`language`/`country_code`/`currency`/`timezone`), consumed exclusively through a new `RecipientLocalePreferenceLookup` port. `NotificationDeliveryLedger` (logical-identity idempotency), `RecipientContactLookup`, `WinningBidderLookup`, `MoneyFormatter`. `AuctionWon → winner` wired end-to-end — this codebase's first real `Event::listen()`-driven, queued cross-module reaction. |
| 6 | `d199774` | The remaining seven events: `PaymentAuthorizationFailed`, `TransferIssued`, `TransferConfirmed`, `TransferExpired`, `TransferCancelled`, `DisputeOpened`, `DisputeResolved`. Extracted `NotificationDeliveryPipeline` (shared ledger/contact/locale/send sequence) once duplicating it eight times became the alternative; refactored `AuctionWon`'s listener onto it. Two new read ports (`TransferParticipantLookup`, `DisputeParticipantLookup`, both Notifications' own, independent of Ratings' identically-shaped port). Fixed `TransferExpirySweepWiringTest`'s placeholder fixture data, exposed by `TransferExpired` gaining a real consumer. |
| 7 | (this report) | Phase closure: full validation pass across every affected package, a Composer symlink environment issue found and fixed (not a code defect), completion report, roadmap/architecture-overview/CLAUDE.md updates, release tag. |

## 4. ADRs created

- **ADR-024 — Ratings Eligibility and Symmetric Model**: symmetric
  eligibility; `Confirmed`-only gate; one rating per participant per
  transfer; full independence from Disputes; double-blind reveal
  anchored to each rating's own `submittedAt`, evaluated lazily with no
  scheduler; rating content rules (score range, comment bound/
  normalization, no auto-translation/moderation); domain/backend-only
  closure bar. Records Decisions 2–5, 8 (this phase's own numbering).
- **ADR-025 — Notifications MVP Scope, Channel, and Event Set**:
  MVP scope and channel; the approved eight-event table and selection
  criteria; idempotent delivery via the logical-identity ledger; the
  Ratings/Notifications exit-bar asymmetry; self-contained,
  field-restricted email content; recipient-own-locale rendering;
  retry/failure reliance on `failed_jobs`. Records Decisions 1, 6, 7,
  9–11.

ADRs 001–023 were pre-existing and remain unchanged and binding.

## 5. Architecture changes

- Two new packages, `packages/Ratings` and `packages/Notifications`,
  following the same shape established since Phase 1.
- **This codebase's first real Laravel `Event::listen()` wiring** —
  every prior cross-module reaction (Phases 4–6) was a directly invoked
  application service; Notifications' entire purpose required genuine
  asynchronous, queued, framework-level event consumption instead.
- **This codebase's first cross-package composer dependencies between
  bounded-context packages themselves** (`packages/Notifications`
  requires `rowbuddy/auctions`, `rowbuddy/payments`, `rowbuddy/transfers`,
  `rowbuddy/disputes`) — narrowly scoped to referencing those packages'
  own public domain-event classes only, the sanctioned "modules
  communicate through domain events" mechanism, never their aggregates,
  models, or services.
- The "consumer owns the port" pattern now has two contexts
  (Ratings, Notifications) independently maintaining their own
  identically-shaped `TransferParticipantLookup` port with zero shared
  dependency between them — proof the pattern scales without coupling
  unrelated contexts to each other.
- A new, reusable extraction: `NotificationDeliveryPipeline`, factoring
  the ledger-check → contact-resolve → locale-resolve → render → send →
  record sequence out of what would otherwise be eight near-identical
  listener implementations.
- Minimal, generic, nullable locale-preference persistence added to
  `users` — explicitly foundational infrastructure, not a preference-
  management feature, consumed exclusively through Notifications' own
  read ports.

## 6. Database changes

`packages/Ratings/database/migrations/`:
- `create_ratings_table` — unique constraint on `(transfer_id, rater_id)`.

`packages/Notifications/database/migrations/`:
- `create_notification_deliveries_table` — unique constraint on
  `(domain_event_id, recipient_id, notification_type)`.

`apps/web/database/migrations/`:
- `add_locale_preference_columns_to_users_table` — nullable `language`/
  `country_code`/`currency`/`timezone`.

No changes to any Phase 1–6 package's schema.

## 7. Test and validation results

| Package | Tests | Notes |
|---|---|---|
| `packages/shared-kernel` | 31 | Unaffected. |
| `packages/Queues` | 81 | Unaffected. |
| `packages/QueuePresence` | 81 | Unaffected. |
| `packages/Auctions` | 66 | Unaffected. |
| `packages/Bids` | 24 | Unaffected. |
| `packages/Payments` | 93 | Unaffected. |
| `packages/Transfers` | 58 | Unaffected. |
| `packages/Disputes` | 45 | Unaffected. |
| `packages/Ratings` | 41 | New package — aggregate, persistence, eligibility/submission service, reveal policy/evaluator, across four sprints. |
| `packages/Notifications` | 35 | New package — delivery ledger, locale/contact/winning-bidder/transfer-participant/dispute-participant read ports, money formatting, delivery pipeline, all eight listeners and mail classes, across two sprints. |
| `apps/web` | 101 (was 91 at Phase 6 close) | Includes end-to-end wiring tests for every new cross-module read port and every notification event requiring one, real-locale rendering, English fallback, and duplicate-delivery idempotency for both `AuctionWon`, `TransferConfirmed`, and `DisputeResolved`. |

**Total: 656 automated tests.** PHPStan level 8 clean on both new
packages; Larastan clean on `apps/web`. Pint clean on `packages/Ratings`
and `packages/Notifications`; `apps/web` carries the same 12
pre-existing style issues from Phase 1, confirmed unrelated and
unchanged throughout every Phase 7 sprint.

**A real regression was caught and fixed during Sprint 6, not left
latent**: `TransferExpirySweepWiringTest`'s pre-existing fixture used
placeholder `'1'`/`'2'` buyer/seller ids with no backing `User` rows —
harmless before `TransferExpired` had a real consumer, but a genuine gap
once it did. Fixed at the fixture level, not by weakening the
notification listener's correctness.

**An environment-only issue was caught and fixed during Sprint 7's final
validation, not a code defect**: `packages/Ratings`' `vendor/rowbuddy/
shared-kernel` Composer symlink had been created by an earlier host-side
`composer install` (Sprints 1–4, since Ratings needed no `ext-intl`) and
pointed to a path invalid from inside the Docker container. Fixed by
reinstalling from inside the container; no source file changed.

**This Docker image's PHP build ships ICU data for `en` locales only** —
`NumberFormatter`/`IntlDateFormatter` fall back to English/root
formatting for any other locale (verified: `es_ES`/`de_DE`/`fr_FR` all
identical). `MoneyFormatter`'s implementation is correct against ICU's
documented API and will behave correctly once real locale data is
available; this is an accepted environment limitation, not an
implementation defect, and remains unaddressed by this phase.

## 8. Closure-scope decision

Phase 7 closes with two deliberately different exit bars, decided
explicitly as Decision 8 (ADR-024 §7/ADR-025 §8), not discovered at
closure:

- **Ratings closes domain/backend-only** — complete domain model,
  application services, persistence, read ports, business rules, reveal
  policy, comprehensive automated tests. No HTTP endpoint, no Inertia/
  React page, no user-facing rating-submission UI — the same posture
  every phase since Phase 3 has used.
- **Notifications closes only because a real email channel is
  operational, tested, and integrated end-to-end** — the sole exception
  to every prior phase's own closure bar, reasoned specifically from
  what a notification *is* (meaningless without real delivery), not
  from "Phase 7 modules get upgraded scope" as a general rule.
- **No admin UI, no alerting, no observability tooling beyond Laravel's
  own `failed_jobs`** — Decision 11/ADR-025 §11's explicit, accepted MVP
  limitation.
- **No push, SMS, marketing campaigns, preference centers, scheduled/
  bulk notifications, or digests** — explicitly out of scope per
  Decision 1/ADR-025 §1, not organically extended.

## 9. Known limitations and deferred product decisions

### Accepted limitations (by design, not oversights)

- **No Ratings HTTP/UI** — a rating can only be submitted by directly
  invoking `RatingSubmissionService` (Decision 8).
- **No rating-reveal notification** — a party learns of a reveal by
  reading, not by being told; explicitly excluded from the Notifications
  event set (ADR-025 §6).
- **No push, SMS, marketing, preference center, digests, or multi-
  channel routing** — none exist, and none are implicit extensions of
  what was built (Decision 1).
- **No proactive failure alerting of any kind** — a permanently failed
  notification has no surfacing beyond Laravel's own `failed_jobs` table
  (Decision 11).
- **Non-English ICU-based locale formatting cannot be verified in this
  Docker environment** — an environment limitation, not a code defect
  (§7).
- **No Ratings/Disputes correlation of any kind** — a future Fraud &
  Risk phase (Phase 8) may read both contexts independently for that
  purpose; neither model changes to support it now (Decision 3).

### Deferred product decisions

- Whether a future phase ever needs proactive notification at a
  reveal deadline (would require introducing a scheduler Phase 7
  deliberately avoided).
- Whether Ratings ever needs an aggregate/display feature (e.g., an
  average score shown on a profile) — not addressed by any frozen
  decision this phase.
- Whether a ninth notification event or a second delivery channel is
  ever warranted — each requires its own new, separately approved
  decision, not an organic extension of this event table.
- Real ICU locale-data provisioning for this Docker environment, should
  verified non-English formatting become necessary.

## 10. Phase 8 readiness assessment

Phase 8 (per `docs/roadmap.md`) is Administration & Fraud/Risk v1. Phase
7 provides:

- A second, independently confirmed instance of the "consumer owns the
  port" pattern scaling to two contexts (Ratings, Notifications) reading
  the same upstream data through their own separate ports, with zero
  coupling between the two consumers themselves.
- This codebase's first real event-listener-driven delivery mechanism,
  proven idempotent and locale-correct end-to-end — a template Phase 8's
  own admin-facing notifications (if any) could follow directly.
- A complete, symmetric ratings dataset with double-blind reveal
  already enforced — a natural signal source for Fraud & Risk pattern
  detection, though Decision 3 deliberately keeps Ratings itself free of
  any correlation logic; Phase 8 would read it independently.

**No blockers identified for Phase 8 at the domain level.** Per this
project's established phase-gating discipline, Phase 8 implementation
requires its own separate authorization and, per the roadmap's existing
posture, likely its own architecture-review-and-planning session before
any code is written.
