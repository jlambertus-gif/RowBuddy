# Phase 9 Sprint 5 — Security Review

ADR-027 Decision 4 deliverable: a structured internal review against the
risks named in `docs/product/claude-mvp-analysis.md` §3.2, combined with
automated security tooling already integrated into the development
workflow, with written, independently verifiable findings.

## Scope and methodology

Whole-system review, highest scrutiny on the Phase 9 delivery surfaces
(public auction read, bid placement + Reverb broadcast, buyer
payment-method setup, Transfers/QR confirmation), confirmatory review of
every existing HTTP/admin surface (queue submission/moderation, presence
sessions, evidence photos, dispute review/correction, audit log, and the
Fortify authentication surface added on this branch).

Verified: authentication and authorization; IDOR resistance; webhook
authenticity; race-condition handling; evidence privacy; replay
protection; input validation; secrets management; dependency
vulnerabilities.

Every conclusion below is backed by a specific file:line code reference,
an existing automated test, or a live tool run (`composer audit`,
`npm audit`) — not by design intent alone. Per this sprint's own scope,
only small, evidence-backed fixes were applied; anything requiring a
product/policy decision is recorded as a finding for explicit review, not
silently resolved.

## Findings against the five named risks (claude-mvp-analysis §3.2)

### 1. Evidence files must never be publicly addressable; signed/short-lived URLs only

**CONFIRMED SAFE.**
- The evidence disk (`storage/app/private`) is a distinct filesystem
  disk from `public` and is never reachable through the public storage
  symlink — `apps/web/config/filesystems.php`.
- Retrieval requires the requesting user to own the presence session
  (`PresenceSessionService::assertOwnedBy()`) **and** the requested photo
  id to belong to that exact session (`presence_session_id` cross-check),
  not merely "photo exists" — `packages/QueuePresence/src/Application/PresenceSessionService.php`
  (`evidencePhotoUrl()`).
- The URL returned is a genuine signed, temporary URL (`Storage::disk('local')->temporaryUrl()`)
  with a 300-second TTL — `app/Infrastructure/LocalPrivateEvidenceStorage.php`.
- File-type/size validation is real and content-aware (`image`, `mimes:jpeg`,
  `max:8192`), not extension/Content-Type-based —
  `apps/web/app/Http/Requests/UploadEvidencePhotoRequest.php`.
- EXIF/GPS metadata is genuinely stripped before storage (GD decode +
  re-encode, which does not propagate source metadata segments), called
  unconditionally on every upload — `packages/QueuePresence/src/Infrastructure/Images/GdImageMetadataStripper.php`,
  invoked from `PresenceSessionService`.

No fix required.

### 2. Stripe webhook forgery if signature validation is missed on any endpoint

**CONFIRMED SAFE.**
- The webhook route verifies the `Stripe-Signature` header via
  `Stripe\Webhook::constructEvent()` (HMAC against the configured secret)
  before any payload content is used — `StripeWebhookController.php` →
  `packages/Payments/src/Infrastructure/Stripe/StripeWebhookSignatureVerifier.php`.
  A failed verification returns 400 and never reaches the processor.
- The CSRF exemption in `bootstrap/app.php` is an exact-string route
  match (`'webhooks/stripe'`), not a prefix/wildcard — no other route is
  inadvertently exempted.
- The webhook secret is read only from `STRIPE_WEBHOOK_SECRET`
  (`config/services.php` → env); `.env.example` ships it blank;
  `phpunit.xml`'s test placeholder (`phpunit_placeholder_webhook_secret_not_real`)
  is not `whsec_`-prefixed and cannot be mistaken for a real credential.
- Replayed webhook deliveries (same Stripe event id) are rejected by a
  **database primary key** on `stripe_event_id`
  (`packages/Payments/src/Infrastructure/Eloquent/EloquentWebhookEventRepository.php`,
  migration `create_webhook_events_table`) — not an application-level
  check-then-insert.

No fix required.

### 3. Replay of QR transfer tokens — must be single-use, short-TTL, bound to the specific transfer

**CONFIRMED SAFE, exceeds the documented bar.**
- The token is `bin2hex(random_bytes(32))` (256 bits); only its SHA-256
  hash is ever persisted by Transfers — the plaintext is never stored by
  the domain package at all, only transiently cached by the delivery
  layer, TTL-bound to the transfer's own expiry
  (`packages/Transfers/src/Application/TransferInitiationService.php`,
  `apps/web/app/Listeners/TriggerTransferInitiation.php`).
- Comparison uses `hash_equals()` — constant-time
  (`TransferConfirmationService::assertQrTokenMatches()`).
- The requesting user's identity (seller for `confirmBySeller`, buyer for
  `confirmByBuyer`) is checked **before** the QR/geofence/status checks,
  under a row lock (`findByIdForUpdate`) — independently re-verified
  during this review by reading `TransferConfirmationService::confirmWithinTransaction()`
  line-by-line: lock → ownership check → expiry evaluation → status
  check → geofence check → QR-token check → mutation, all inside one
  transaction.
- A second confirmation attempt (even with a correct token) is rejected
  by the aggregate's own state guard (`Transfer::confirmBySeller()`/`confirmByBuyer()`
  throwing `IllegalStateTransition` once already confirmed) — genuinely
  single-use for the confirmation action itself.
- Only the buyer can retrieve the plaintext token at all
  (`ShowTransferQrTokenController`); the seller and any unrelated user
  are rejected.

**Fix applied (documentation only, no behavior change):**
`ShowTransferQrTokenController`'s docblock previously said "one-time-
delivery reveal," which overstated the guarantee — the cached token is
re-readable by the buyer (not deleted after first read) until its TTL
expires, which is the intended behavior (a buyer may need to redisplay
the same QR code after a failed scan). Corrected the docblock to
describe the actual, intended behavior instead of a stronger guarantee
the code doesn't provide.

**Fix applied (defense-in-depth, no demonstrated exploit):** added
`throttle:transfer-confirmation` (20/min per user) to both
`confirm-as-seller` and `confirm-as-buyer`, mirroring bid-placement's
existing rate-limit convention. The 256-bit keyspace plus the
identity-gate-before-token-check ordering already make brute force
computationally infeasible regardless of request rate — this closes a
structural inconsistency (every other mutating financial/handoff
endpoint already had a rate limit; these two didn't) rather than a
demonstrated vulnerability.

### 4. Race conditions on bid placement without row-level locking

**CONFIRMED SAFE.**
- `EloquentAuctionRepository::findByIdForUpdate()` uses a real
  `lockForUpdate()`. `EloquentAuctionGateway::lockAndCheckForBidding()`
  locks the Auction row first, then runs `LiveProximityChecker` and the
  closing evaluator against that same locked row, all inside
  `BidService::place()`'s one transaction — proximity cancellation and
  the highest-bid check share one lock; a concurrent bid cannot land
  between them.
- The idempotency-key claim
  (`packages/Bids/src/Infrastructure/Eloquent/EloquentBidPlacementLedger.php`)
  is enforced by a real DB unique-constraint violation, not
  check-then-insert — two concurrent identical-key requests cannot both
  succeed.
- The same pattern (row lock acquired before ownership/status checks,
  inside one transaction) was independently confirmed for Transfer
  confirmation (above), Payment capture (`PaymentCaptureService`, real
  `lockForUpdate()` + validate→call-Stripe→mutate→persist ordering +
  deterministic Stripe idempotency keys), and Dispute resolution
  (`Dispute::resolve()`'s status guard under lock, preventing a
  concurrent double-refund).

No fix required for the named risk itself. One related, lower-severity
gap was found and fixed (below).

**Fix applied:** `StripePaymentAuthorizationGateway::capture()` and
`::cancel()` were the only two Stripe-mutating calls in that class not
passing a Stripe `idempotency_key`, unlike `authorize()`/`refund()` in
the same file. Added deterministic keys
(`payments.capture.{paymentIntentId}` / `payments.cancel.{paymentIntentId}`)
matching the existing pattern. Risk was low even before the fix — Stripe
itself rejects a second capture/cancel on an already-transitioned
PaymentIntent — but this closes an inconsistency with the class's own
established convention and removes any ambiguity if a network-level
retry occurs.

### 5. IDOR on evidence/dispute endpoints — authorization at the module boundary, not just the controller

**CONFIRMED SAFE.**
- Evidence: see risk #1 above — ownership is asserted inside
  `PresenceSessionService` (the application-service boundary), not just
  by the controller checking route parameters.
- Transfers (the highest-value IDOR surface — money and physical
  handoff): `ShowTransferController`, `ShowTransferQrTokenController`,
  and both confirm controllers all derive the acting user id exclusively
  from `$request->user()->id` and are rejected identically whether the
  caller is an unrelated stranger or the transfer's *other* party (e.g.
  the buyer calling the seller-only confirm action) —
  `TransferConfirmationService::confirmWithinTransaction()`'s ownership
  check, independently re-verified line-by-line during this review.
- Buyer payment methods: `StripeBuyerPaymentMethodGateway::retrieveConfirmedPaymentMethod()`
  cross-checks the Stripe Customer's own `metadata['rowbuddy_buyer_id']`
  against the authenticated buyer id before returning anything — not
  merely trusting a client-supplied SetupIntent id.
- Admin-gated surfaces (queue moderation, dispute review/correction,
  audit log) are authorized via one `Gate::define()` per
  `AdminCapability`, driven by a server-side role lookup
  (`AdminRoleAssignmentRepository` + `AdminRoleCapabilityMap`), fail-closed
  if no role is assigned — never a client-claimed capability.

No fix required.

## Additional findings outside the five named risks

### Input validation (whole-surface confirmatory review)

**CONFIRMED SAFE** for every Phase 9 + existing mutating endpoint
reviewed: bid amount/currency (`PlaceBidRequest`), GPS coordinate bounds
(`RecordGpsPingRequest`, `ConfirmTransferAsSellerRequest`/`AsBuyerRequest`,
`SubmitQueueRequest` — all real `between:` range rules, not presence-only
checks), evidence-photo file validation (above), and the dispute-
correction "mandatory reason" (enforced twice — once loosely at the
FormRequest layer, once strictly, rejecting whitespace-only input, inside
`DisputeCorrectionService::assertReasonNotBlank()`). No raw card data
field exists anywhere in the application layer — buyer payment-method
setup only ever handles a Stripe-generated `setup_intent_id` string,
consistent with CLAUDE.md's "the platform must never store card details."

**Minor, non-security items noted, not fixed** (product-scope, not a
vulnerability): `SubmitQueueRequest.radius_meters` has a lower bound
(`min:1`) but no upper bound; an absurdly large radius is possible but
carries a compensating control already (user-submitted queues require
admin approval before any auction can be created against them, ADR-005)
and no injection/overflow risk. Left for a product decision on a sane
maximum, not resolved as a security fix.

### Secrets management

**CONFIRMED SAFE.** A whole-repository scan of every tracked file
(`git grep`, excluding lock files) for Stripe-secret-shaped strings
(`sk_(test|live)_...`, `whsec_...`), AWS access keys, PEM private-key
headers, and Google API key patterns found zero matches. `.env` is
git-ignored (`git check-ignore -v` confirmed). `phpunit.xml`'s Stripe
placeholders are deliberately non-`sk_test_`/`whsec_`-shaped and confined
to the test runner's own config, never `.env`.

### Dependency vulnerabilities

**One real vulnerability found and fixed, one already-clean baseline
confirmed:**
- `npm audit` (frontend/build dependencies) found one moderate advisory
  (`postcss` < 8.5.22, GHSA-fxqj-rqcc-2cmp — incomplete fix for an
  attacker-controlled `sourceMappingURL` reading arbitrary `.map` files
  when Vite's `from` option is unset). This is a build-tooling
  dependency (transitively required by `vite`), not code that ships to
  end users, but there was a safe in-range fix available:
  `npm audit fix` bumped it to 8.5.25 with zero other package changes.
  `npm audit` now reports zero vulnerabilities; `npm run build` was
  re-run afterward and completed cleanly (694 modules, no errors).
- `composer audit` was already clean across `apps/web` and all 11
  packages as of Sprint 4's own gate (the `guzzlehttp/guzzle` CVE found
  there was already patched and committed in `57e20d6`); re-run clean
  again during this review.
- CI (`.github/workflows/ci.yml`) already runs `composer audit` for
  every job (`shared-kernel`, the full 10-package matrix, and `web`) —
  this was Decision 4's own approved hardening task, already implemented
  before this sprint.
- Dependabot (`.github/dependabot.yml`) is already configured for every
  Composer package directory plus `apps/web`'s npm dependencies and
  GitHub Actions versions — also already implemented before this
  sprint.
- **GitHub Secret Scanning could not be verified or enabled from this
  environment** — the `gh` CLI is not installed here, and secret
  scanning / push protection is a repository setting (GitHub web UI →
  Settings → Code security and analysis), not a file in this repo. This
  remains an action item for you to confirm/enable directly in GitHub,
  per Decision 4's own "where repository capabilities allow" qualifier.

## Email verification — implemented per explicit decision (2026-08-04)

Email verification was found disabled during this review (recorded
above as a finding pending your decision) and you subsequently decided:
**required before launch**, given this is a transactional marketplace
involving bidding, payments, transfers, disputes, notifications, and
administrative actions.

**What changed:**
- `Features::emailVerification()` enabled in `apps/web/config/fortify.php`;
  `App\Models\User` now implements `MustVerifyEmail`.
- This app has no `App\Providers\EventServiceProvider` (it wires every
  cross-module reaction through an explicit `Event::listen()` call in
  `AppServiceProvider::boot()` instead — see `bootstrap/app.php`'s own
  `withEvents(discover: false)`), so Laravel's default
  `Registered -> SendEmailVerificationNotification` wiring, normally
  configured by the base `EventServiceProvider`'s `boot()`, never ran in
  this app. Registered it explicitly in `AppServiceProvider::boot()`,
  matching this codebase's own established convention rather than
  relying on framework auto-configuration.
- A new Inertia page, `resources/js/Pages/Auth/VerifyEmail.jsx`, wired
  via `Fortify::verifyEmailView(...)` — using `react-i18next` with a new
  `auth` translation namespace (en/es), not the hardcoded-Spanish
  pattern the pre-existing Login/Register/ForgotPassword/ResetPassword
  scaffold pages use. Those pre-existing pages were left untouched — out
  of this sprint's scope — but the new page was not written to match a
  known CLAUDE.md violation.
- `verified` middleware (Laravel's own, unmodified, single
  implementation — `Illuminate\Auth\Middleware\EnsureEmailIsVerified`)
  added to exactly five routes, each an initiation of new transactional
  activity: `POST /queues` (submit a queue/listing), `POST
  /auctions/{auctionId}/bids` (place a bid), `POST /presence-sessions`
  (a seller starting the listing process), `POST
  /buyer-payment-methods/setup-intent` and `POST /buyer-payment-methods`
  (configure a payment method).
- **Deliberately NOT gated**: presence-session continuation endpoints
  (gps-pings/end/evidence-photos — they only ever continue a session
  that already required verification to start), and both Transfer
  confirmation endpoints (`confirm-as-seller`/`confirm-as-buyer` — these
  fulfill an *existing* obligation created by an already-won bid and
  already-authorized payment; gating them on verification status would
  let the transfer silently expire, exactly the "existing obligation
  mutated because of unverified status" outcome you explicitly
  prohibited). Also not gated: `GET /email/verify`, `POST
  /email/verification-notification`, `GET /forgot-password`, `POST
  /forgot-password`, `GET /reset-password/{token}`, `POST
  /reset-password`, `PUT /user/password`, `GET /dashboard`, and login —
  all Fortify's own routes or this app's own, already requiring only
  `auth` (or nothing), unaffected by this change.
- **No HTTP endpoint exists yet for "submit a rating"** (Ratings closed
  Phase 7 as domain/backend-only, per the roadmap's own completion
  report) or for a buyer filing a dispute (only admin-facing
  review/correction endpoints exist). Nothing to gate for either — noted
  here so this isn't mistaken for an oversight. Whichever HTTP surface
  is eventually built for either must include `verified` middleware from
  the start.
- One incidental fix this change required: `App\Actions\Fortify\UpdateUserProfileInformation`
  had a defensive `$user instanceof MustVerifyEmail` check (dead code
  now that the interface is unconditionally implemented — flagged by
  PHPStan as `instanceof.alwaysTrue`). Simplified to always take the
  "reset verification on email change" path, preserving identical
  behavior for a real user.

**Verification derivation and duplication:** verification status is
read only via Laravel's own `$request->user()->hasVerifiedEmail()`
inside the single `verified` middleware alias — no parallel/duplicate
check was added anywhere else in this codebase.

**Tests added** (`apps/web/tests/Feature/EmailVerificationTest.php`, 9
tests, 47 assertions): registration sends a real `VerifyEmail`
notification (`Notification::fake()`-asserted); an unverified user can
still sign in; an unverified Inertia-style request to each of the five
gated routes redirects to `/email/verify` (the real frontend's actual
experience — Inertia doesn't send `Accept: application/json`, so
Laravel's own middleware takes its redirect branch, not its abort
branch); an unverified JSON-API-style request to the same five routes
gets a 403; a verified user reaches real controller/validation logic
(422/400/200, never a 403) on all five; a validly signed verification
link verifies the email and a tampered-hash link is rejected; an
expired signed link is rejected even with a correct hash; resending is
rate-limited (Fortify's own default `6,1` throttle — confirmed as the
7th request in one minute returns 429); and password reset, password
change, dashboard access, the verification-notice screen, and resending
all remain available to an unverified user.

## Findings recorded for explicit product/security-policy decision (not fixed)

**2FA and passkeys are optional, not mandatory.** Both features are
wired and available (`Features::twoFactorAuthentication()`,
`Features::passkeys()`) but not required for any account type,
including admin accounts. Standard posture for MVP-stage auth — recorded
for completeness, not flagged as a defect.

## Summary

Zero confirmed vulnerabilities across all five risks named in
`claude-mvp-analysis.md` §3.2, and across the broader whole-system
confirmatory review (input validation, secrets management, admin
authorization). Four small, evidence-backed fixes applied (QR-token
docblock correction, Stripe capture/cancel idempotency keys, transfer-
confirmation rate limiting, and — per your explicit decision — email
verification enabled and enforced on five new-transactional-activity
routes) — all verified via the existing/re-run test suites, `PHPStan`,
and `Pint`, with zero regressions. One real dependency vulnerability
(`postcss`, moderate) found and patched. GitHub Secret Scanning remains
an external action item for you — see
`docs/security/security-checklist.md`.

See `docs/security/security-checklist.md` for the per-item verification
table this review's conclusions are drawn from.
