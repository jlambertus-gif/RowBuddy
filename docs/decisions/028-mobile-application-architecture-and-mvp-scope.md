# ADR 028: Mobile Application Architecture and MVP Scope

## Status

**Accepted.** All decisions below, plus Decision 8 (added at acceptance)
and the `apps/api` resolution added to Decision 2 (added after Sprint 0
surfaced the question), are frozen. Sprint 0 (project scaffolding only —
no auth, no screens, no backend consumption) is complete, validated, and
approved; see `docs/mobile/sprint-0-validation-report.md`. Sprint 1
architecture work begins next. No web/backend file has been modified. No
merge into `main` has occurred.

## Context

RowBuddy Web reached RC1 at commit `6b06a62` (tag `v1.0.0-rc1`): all nine
numbered development phases plus Phase 9 (Hardening & Launch Readiness,
ADR-027) are accepted, and the Functional Acceptance stage that followed
closed with zero unresolved defects. Five feature/architecture gaps
found during that stage (`docs/qa/enhancement-backlog.md`, FG-001 through
FG-005) were explicitly deferred, not implemented.

You then authorized the mobile application project and requested a
Mobile Architecture Review before any implementation. That review
(research conducted across six parallel fact-finding passes over ADR-001
through ADR-027, the complete HTTP/routes surface, the auth stack,
Payments/Transfers, Reverb/broadcasting, Notifications/Ratings/Disputes/
Administration, and localization) found that the backend is more
mobile-ready than expected — several domain-critical endpoints (auction
detail, bid placement, buyer-payment-method setup, transfer detail/QR/
confirmation) are already plain JSON, not Inertia-coupled — but that
**zero token-based authentication surface exists anywhere**, and that
Ratings, Disputes-filing, and any form of push notification have **zero
HTTP or delivery surface at all**, despite complete, tested domain
services already existing for the first two.

This ADR records the decisions you froze in response to that review, and
the concrete architectural consequences each one carries. It does not
reopen the review's own findings — see
`docs/mobile/architecture-review.md` for the full research and the
reasoning behind each recommendation this ADR now ratifies or, in one
case (push notifications), overrides.

## Decision

### 1. Mobile technology stack

React Native, **Expo managed workflow**, **TypeScript**, **Expo Router**
for navigation, **TanStack Query** for server state, and **EAS Build/
Submit** for CI/CD and store distribution. The mobile API client uses
**hand-written TypeScript types** mirroring the backend's actual DTO
shapes — no OpenAPI spec exists on the backend to generate from, and
none is introduced by this decision.

This was chosen over Flutter after an explicit comparison (review §3):
both are technically sound for this app's actual demands (forms, lists, a
live countdown, one websocket feed, camera/GPS capture — nothing that
makes Flutter's rendering advantage decisive); React Native wins on team
and stack continuity with the existing React/Inertia web app and its
TypeScript-adjacent domain conventions, not because Flutter is deficient.

The managed workflow (not bare) is chosen because every native capability
this MVP needs — Stripe, Reverb/Pusher-protocol connectivity, camera,
foreground/background location, secure storage, push — already has
first-party or mature Expo tooling; no capability identified in the
review requires ejecting to bare React Native.

### 2. Mobile authentication: Laravel Sanctum, personal access tokens

**Laravel Sanctum**, using its **personal-access-token** guard — not its
SPA cookie-session mode, which assumes a first-party JS app sharing a
domain with the API and is the wrong model for a native client.

- Fortify's existing web session flows are **untouched**. A new
  `routes/api.php` (does not exist today) exposes mobile-specific
  endpoints that call the **same underlying Fortify Action classes**
  already in `app/Actions/Fortify/` (`CreateNewUser`,
  `ResetUserPassword`, `UpdateUserPassword`,
  `UpdateUserProfileInformation`) — only the HTTP response contract
  differs (JSON + token, never a redirect or session cookie).
- The entire mobile-facing surface is versioned from day one:
  **`/api/v1/...`**. A native client cannot be force-refreshed the way a
  web page can; retrofitting a version segment after release would be
  materially more disruptive than reserving it now.
- Tokens are created per device (`$user->createToken($deviceName)`),
  individually revocable, and scoped to a single default ability for MVP
  — no fine-grained Sanctum ability scopes are introduced until a
  concrete need for them exists.
- `POST /api/v1/auth/logout` revokes only the calling token
  (`$request->user()->currentAccessToken()->delete()`) — never any other
  token belonging to the same user.
- Password-reset and email-verification links continue to open the
  existing web route (they are clicked from an email client, which may
  not have the app installed) with a "return to the app" deep-link
  interstitial — see Decision 7 for the deep-link mechanism itself.
- `verified` middleware requires no change: it inspects
  `hasVerifiedEmail()` on whatever guard resolved the authenticated user,
  and works identically once that guard is Sanctum's token guard instead
  of the session guard.
- CSRF is not applicable to any Sanctum-token-guarded request. Mobile
  never sends or needs a CSRF token — only an `Authorization: Bearer
  <token>` header.

**This new `/api/v1` surface lives inside the existing `apps/web` Laravel
application — not inside `apps/api`.** Sprint 0's scaffolding surfaced
that `apps/api` has sat reserved since Phase 0 (2026-07-21) with a README
explicitly anticipating this exact situation: *"reserved for a future
headless, versioned API — the kind of surface a native mobile app... would
need... [stays] empty until a second real API consumer exists."* Mobile is
that second consumer, and the question of which reserved slot it lands in
was raised, not assumed. It is resolved here, explicitly, in favor of
`apps/web`:

- There is one domain model, one application layer, and one deployment
  artifact. Mobile is a second *client* of the existing backend, not a
  second backend.
- A separate `apps/api` Laravel installation would duplicate
  authentication, infrastructure, deployment, monitoring, Horizon, the
  Stripe integration, Notifications, Audit, and every package's own
  application-service wiring — real, ongoing operational cost with no
  corresponding benefit at this project's current scale.
- `routes/api.php` inside `apps/web`, gated by `auth:sanctum` and
  versioned under `/api/v1`, achieves everything Decision 2/3 need — a
  real separation between the Inertia surface and the JSON surface — at
  the routing layer alone, with zero duplication of anything underneath
  it.

`apps/api` **remains reserved, unchanged, and empty.** This is not a
reversal of that directory's own stated purpose — it is a decision that
today's "second real API consumer" does not yet justify the operational
cost of a second Laravel installation. If RowBuddy ever becomes a true
headless platform serving multiple independent products, `apps/api` (or
an equivalent) can be revisited then, as a separate decision, with that
justification in hand.

### 3. Net-new backend API surface — zero new domain logic

The following endpoints are net-new, and every one of them is a thin
controller over an **already-existing** domain service. None introduces
new business logic, and none changes any accepted bounded-context
invariant from ADR-001 through ADR-027:

- **Auth** (Decision 2): register, login, logout, forgot-password,
  reset-password, resend-verification, `GET /api/v1/me`.
- **Queue submission, JSON variant** — wraps the existing
  `QueueSubmissionService`; a native client needs a parseable JSON
  response, not the Inertia-form redirect pattern the existing
  `POST /queues` route uses.
- **Ratings** — `POST /api/v1/transfers/{id}/ratings` and
  `GET /api/v1/transfers/{id}/ratings`, wrapping
  `RatingSubmissionService`/`RatingRevealEvaluator` exactly as they exist
  today (ADR-024). This is the first HTTP surface either service has
  ever had, on any client.
- **Disputes filing** — `POST /api/v1/transfers/{id}/disputes` (buyer
  only) and `GET /api/v1/disputes/{id}` (status view for the filing
  buyer), wrapping the existing `DisputeFilingService` (ADR-021).
  Dispute *resolution* remains exclusively the existing admin-only web
  surface — unaffected, untouched.
- **Profile/locale-preference write** — extends the existing profile-
  update path to also write `users.language`, `country_code`,
  `currency`, and `timezone`. These four columns already exist
  (migrated for ADR-025 §9/§10's recipient-locale-preference read path)
  and are read today by `EloquentRecipientLocalePreferenceLookup` — but
  nothing in the codebase has ever written them. This endpoint is their
  first writer (see Decision 4).
- **Account-standing self-visibility** — a small, new read endpoint
  exposing the account owner's own suspended/active status. The
  underlying `AccountStandingLookup` port (ADR-026) already exists for
  enforcement; nothing today lets the account owner see their own
  standing.
- **Device push-token registration** — required only because of
  Decision 6; see that decision for its full design.

One item flagged by the review as unconfirmed, not assumed either way:
whether a single-queue **detail** JSON endpoint (distinct from
`GET /queues/discover`'s list) already exists. This must be verified
directly during Sprint 2 scoping, before assuming either that it exists
or that it must be built.

### 4. Mobile locale switching, independent of web's deferred FG-001

Mobile implements real, user-facing locale switching: device-locale
detection on first launch (`expo-localization`) plus an explicit,
persistent in-app override, using i18next/`react-i18next` — the same
library family as the web app, for tooling continuity, though not literal
code sharing (the en/es JSON namespace *content* is portable; the build
tooling is not).

Mobile becomes the **first-ever writer** of `users.language`/
`country_code`/`currency`/`timezone` (Decision 3). This is explicitly
**independent of** `docs/qa/enhancement-backlog.md`'s FG-001 (web locale
switching), which remains deferred exactly as recorded there. Mobile
solving this for itself does not retrofit, imply, or schedule a
locale-switcher for the web app — the two are separate decisions, and
this ADR does not reopen FG-001.

A secondary, previously undocumented consequence follows directly:
because `EloquentRecipientLocalePreferenceLookup` already reads
`users.language` to select a transactional email's rendering locale
(ADR-025 §10), any user who sets a locale preference through the mobile
app will, from that point on, also receive **email** notifications in
that locale — a real behavior change to an existing, accepted system,
caused entirely by populating a column that has been read-but-never-
written since Phase 7. This is a desired, not incidental, consequence:
it is the correct behavior for a preference column that already exists
for exactly this purpose, and requires no change to
`packages/Notifications` itself.

### 5. No composed transaction-status backend endpoint

Auction → Payment → Transfer status composition happens **client-side**,
in the mobile app, by calling the existing separate endpoints for each
(`GET /auctions/{id}`, and the existing Payments/Transfers read
surfaces). **No new composed backend read-model or aggregate endpoint is
introduced** for this MVP.

This directly preserves ADR-014's own decision that Payments' lifecycle
stays independent of `Auction`'s status, with any "combined status" view
treated as a read-side concern deliberately left unbuilt. This decision
does not reopen that deferral; it explicitly declines to build the
aggregate now, for mobile or otherwise. A future decision could revisit
this if real UX friction demonstrates it's needed — this ADR does not
foreclose that, but does not schedule it either.

### 6. Push notifications are included in MVP, as a second channel on the existing Notifications machinery

This reverses the Architecture Review's own recommendation (which
proposed deferring push) — you explicitly included it. The design below
extends `packages/Notifications` rather than introducing a parallel
system, because ADR-025's existing machinery — a closed `NotificationType`
enum, per-type templates, per-type recipient-locale resolution, and a
per-type content-safety allowlist — is exactly the machinery a second
delivery channel needs, and duplicating it for push would violate this
project's own non-duplication discipline.

- **Provider**: Expo's push notification service, which brokers both
  APNs and FCM — consistent with Decision 1's managed-workflow choice.
- **Device-token registration**: a new `device_tokens` table
  (`user_id`, `platform`, `expo_push_token`, `last_seen_at`), owned by
  **`packages/Notifications`**, not Identity/User — it is the sole
  consumer of this data, matching the "consumer owns the port" pattern
  this project has already used consistently (Ratings and Notifications
  each independently read Transfers today through their own ports, with
  zero coupling to each other). `POST /api/v1/devices` registers or
  refreshes a token; a token is removed on logout and is never pushed to
  once its owning session's token has been revoked.
- **Channel dimension on the existing idempotency ledger**:
  `NotificationDeliveryLedger`'s key
  (`domain_event_id, recipient_id, notification_type`) gains a fourth
  dimension, `channel` (`email` | `push`), so email and push delivery for
  the same event are tracked independently rather than one channel's
  delivery incorrectly suppressing the other's. This is an **additive**
  migration to a Phase 7-accepted schema, not a breaking change to it.
- **Event set**: push is extended to the **same eight (event, recipient)
  pairs** ADR-025 §6 already approved for email — no new event is
  invented, and no existing pair is dropped. This starting mapping may be
  narrowed before Sprint 6 begins if you decide some events don't warrant
  an interruption-level channel; it is not treated as permanently closed
  by this decision, only as the correct default to start from.
- **Content safety**: every push payload must satisfy the exact content
  restrictions ADR-025 §9 already imposes on email — no raw provider
  identifiers, no internal IDs, no un-allowlisted participant identity,
  no content that would leak more than the equivalent email already
  allows.
- **Locale**: push content renders from the recipient's own stored
  `users.language`, identically to email (ADR-025 §10) — the first real
  consumer of that column besides email, and, per Decision 4, populated
  for the first time by the very same mobile app consuming it.
- **Permission model**: no token is registered until the OS grants
  notification permission; the app requests this contextually, not at
  first launch (review §8).

### 7. MVP security posture

- **Biometric authentication remains out of MVP.** If ever added, it may
  only gate *unlocking the on-device stored token* — never a substitute
  for, or an additional server-side factor in, real authentication.
- **QR-screen screenshot protection is adopted** (Android's `FLAG_SECURE`
  or the Expo/RN equivalent) on the screen displaying a buyer's transfer
  QR code.
- **Deep links use platform-verified Associated Domains/App Links before
  any production release.** A custom URL scheme (e.g. `rowbuddy://...`)
  is acceptable during development only, since a custom scheme can be
  squatted by another installed app and must not reach production.
  Verified links are blocked on production-domain control (an external
  prerequisite, review §11) and are not required to begin Sprint 0.
- **Manual retry remains the default** for any mobile mutation whose
  idempotency has not been formally established against the backend
  contract. Bid placement is the sole exception, since its
  `Idempotency-Key` contract is already documented and server-verified
  (a `409` response is explicitly documented as safe to retry with the
  same key). Buyer-payment-method-setup completion and transfer
  confirmation are **not** treated as safely auto-retryable until their
  idempotency is explicitly confirmed against the backend during
  implementation — until then, a failed attempt surfaces a manual retry
  to the user, never an automatic one.

### 8. Mandatory automated test coverage for every new mobile-facing endpoint

No endpoint introduced under Decision 3 (or any later addition to the
`/api/v1` surface) is considered complete without, at minimum:

- **Feature tests** covering its actual request/response behavior.
- **Authorization tests** covering who may and may not call it.
- **IDOR tests where applicable** — any endpoint accepting an identifier
  that could plausibly refer to another user's resource (a transfer id,
  a dispute id, a rating target) must have a test proving a non-owning,
  authenticated caller is rejected.
- **Contract tests for the JSON response** — the response shape itself
  is asserted, not just its status code.

This reaffirms, as an explicit testing obligation rather than only a
design principle, the same IDOR discipline Decision 3's own Consequences
already required of every new controller (deriving the acting user's
identity exclusively from the Sanctum guard). It extends this project's
existing testing culture — every prior phase's own Pest suite already
follows this shape for its own HTTP surface — to mobile's net-new
endpoints specifically, since Ratings, Disputes-filing, and profile/
locale-writing have never had HTTP-layer tests of any kind before.

## Consequences

- `composer.json` gains `laravel/sanctum`; a new `personal_access_tokens`
  migration (Sanctum's own standard migration, not a custom one) is
  added. No change to `config/session.php`, `config/fortify.php`, or any
  existing web route's guard.
- A new `routes/api.php` exists for the first time in this codebase,
  registered alongside (never replacing) `web.php`, **inside `apps/web`**.
  Every route in it is gated by `auth:sanctum`, versioned under
  `/api/v1`. `apps/api` gains no file and no new content — it remains
  exactly as reserved as it was in Phase 0.
- Every new mobile-facing controller derives the acting user's identity
  **exclusively** from the Sanctum guard, never from a client-supplied
  identifier — the same non-negotiable discipline every existing
  bid/transfer/payment controller already follows (review §8), now
  extended without exception to Ratings, Disputes-filing, and profile
  endpoints, which have never had this discipline tested against them
  before because they've never had an HTTP surface at all.
- `packages/Notifications` gains: a `device_tokens` table and its own
  application service for registering/removing tokens; a push-delivery
  implementation of its existing per-type template/locale/content-safety
  contract; and an additive migration to `NotificationDeliveryLedger`
  adding the `channel` column. No change to any of the eight existing
  Mailables, and no change to email delivery behavior for any user who
  never sets a `users.language` preference (the column remains `null`
  by default, exactly as it does today).
- `users.language`/`country_code`/`currency`/`timezone` gain their first
  writer. No migration change — the columns already exist. Any existing
  user who has never set a preference is unaffected; the moment a user
  sets one through the mobile app, their future transactional emails
  render in that locale, per ADR-025 §10's already-accepted behavior.
- No `AuctionStatus`, `PaymentIntent`, or `Transfer` state, transition, or
  migration changes as a result of this ADR. Decision 5 explicitly
  declines to build any composed read-model spanning them.
- `RatingSubmissionService`, `RatingRevealEvaluator`, and
  `DisputeFilingService` gain their first callers outside a test suite —
  no change to any of the three services themselves.
- Every sprint that introduces a new `/api/v1` endpoint (Sprints 1, 4, 5,
  6 per §14's proposed breakdown) now carries an explicit, non-optional
  testing line item per Decision 8 — feature, authorization, IDOR-where-
  applicable, and response-contract tests — as part of that endpoint's
  own definition of done, not a follow-up task.
- No change to any existing Reverb channel, event, or broadcast payload.
  The mobile app subscribes to the existing public `auctions.{id}`
  channel exactly as the web app does today.
- No change to `AdminRole`, any Administration capability, any Gate, or
  any existing admin-only route — Administration remains entirely
  out of the mobile MVP (see Explicitly Out of Scope).

## Explicitly Out of Scope

- **Administration**, in its entirety — no concrete requirement
  surfaced during the review that justifies including it in a first
  consumer-facing mobile MVP.
- **Fraud & Risk** and **seller payout execution** — both remain
  unbuilt, per ADR-015/ADR-018/ADR-026's own existing deferrals; this
  ADR does not reopen either.
- **Any of FG-001 through FG-005** (`docs/qa/enhancement-backlog.md`) —
  none are implemented by this ADR. Decision 4's mobile-side locale
  capability is explicitly not a resolution of web's own FG-001 (see
  Decision 4).
- **A composed Auction→Payment→Transfer backend endpoint** — Decision 5.
- **An in-app notification list/inbox** — Decision 6 adds push as a real-
  time *delivery* channel, not a persisted, browsable in-app notification
  center. Email remains the durable record of every notification
  exactly as ADR-025 already established; nothing about Decision 6
  changes that. Building a browsable in-app inbox is a separate,
  un-made decision, not implied by push's inclusion.
- **Biometric authentication as a real auth factor** — Decision 7.
- **TLS certificate pinning for this MVP** — not adopted; the review's
  own reasoning (defer until the release/rotation process is proven
  through at least one real app-update cycle) stands, and this ADR does
  not revisit it.
- **A bare (ejected) Expo workflow** — Decision 1 commits to managed.
- **Generated/OpenAPI-driven API client types** — Decision 1 commits to
  hand-written types for this MVP.
- **Dispute resolution, queue moderation, restricted-category/
  jurisdiction-rule administration, and audit-log review** on mobile —
  all remain exclusively the existing admin-only web surface.
- **A separate `apps/api` Laravel installation** — Decision 2. `apps/api`
  remains reserved and empty; the `/api/v1` surface lives inside
  `apps/web`. Revisiting this is explicitly conditional on RowBuddy
  becoming a true headless platform serving multiple independent
  products — not on mobile's existence alone.

## References

- `docs/mobile/architecture-review.md` — the full research and
  reasoning this ADR ratifies (§1–§11), including the one point it
  overrides (push notifications, §12 decision 7 of that document,
  resolved here by Decision 6).
- `docs/releases/rc1-readiness-report.md`, `docs/qa/functional-acceptance-final-report.md`,
  `docs/qa/enhancement-backlog.md` — the accepted baseline this ADR
  builds on, and the FG-001–005 deferrals Decision 4 and Explicitly Out
  of Scope both refer back to.
- `apps/api/README.md`, `apps/admin/README.md` — the Phase 0 (2026-07-21)
  reserved-directory convention Decision 2's `apps/api` resolution
  addresses directly; `docs/mobile/sprint-0-validation-report.md` (Finding
  1) is where this question first surfaced.
- ADR-002 (multilingual from start) — the localization discipline
  Decision 4 extends to mobile and, for the first time, to a real
  preference-write path.
- ADR-011, ADR-017, ADR-020 (live-proximity enforcement; transfer
  aggregate/QR mechanics; handoff geolocation) — the domain invariants
  the mobile presence/evidence and transfer-confirmation screens must
  operate within unchanged, per the architecture review §3/§7/§8.
- ADR-014 — the payments/auctions lifecycle-independence decision
  Decision 5 declines to compose around.
- ADR-021, ADR-024 — the Disputes-filing and Ratings domain services
  Decision 3 gives their first-ever HTTP callers, unchanged.
- ADR-025 (Notifications MVP scope) — the exact machinery (closed event
  enum, per-type template/locale/content-safety discipline, delivery
  idempotency ledger) Decision 6 extends with a second channel.
- ADR-026 (Administration MVP scope) — the boundary Explicitly Out of
  Scope reaffirms for mobile.
- ADR-027 (Phase 9 launch-readiness scope) — the "necessity, not
  completeness" principle (§0) this ADR's own restraint (Decisions 5 and
  Explicitly Out of Scope) follows for the same reasons.
