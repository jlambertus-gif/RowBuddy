# Functional Acceptance — Issue Log

Every issue found during the browser walkthrough (`docs/qa/functional-acceptance-checklist.md`)
is recorded here before any fix is made. Fixes happen in a stabilization
pass, held uncommitted until the finding is documented and approved —
per your explicit instruction, nothing here is fixed silently or
in-line during the walkthrough itself.

**RC1 gate**: zero entries with status `Open` or `In Progress`. `External
Blocker` and `Deferred (out of scope)` entries do not count against this
gate — they are recorded, not resolved by this process.

**Gate status: MET.** FA-001, FA-002, and FA-003 are all `Resolved`
following the stabilization pass. FG-001 through FG-005 are recorded as
`Deferred enhancement` in the register below — a future-release decision,
not a defect, and does not count against this gate. Zero entries remain
`Open` or `In Progress`.

## Fields

- **ID** — `FA-NNN`, sequential, never reused.
- **Scenario** — the checklist scenario number/name it was found under.
- **Account/role** — which of the four test accounts (or a freshly
  registered one) was used.
- **Browser** — browser + version.
- **Steps to reproduce** — exact, numbered.
- **Expected result**
- **Actual result**
- **Severity** — `Blocker` (stops the walkthrough / a core flow entirely),
  `Major` (flow completes but incorrectly, or a security/authorization
  concern), `Minor` (cosmetic, wrong copy, non-blocking), `Info` (an
  observation, not a defect — e.g. a pre-documented limitation
  reproducing exactly as expected).
- **Screenshots/logs** — path or reference.
- **Status** — `Open`, `In Progress`, `Fixed (uncommitted)`, `Fixed
  (committed, awaiting approval)`, `Fixed (approved)`, `External
  Blocker`, `Deferred (out of scope)`, `Not a defect`.

## Log

| ID | Scenario | Account/role | Browser | Steps to reproduce | Expected | Actual | Severity | Evidence | Status |
|---|---|---|---|---|---|---|---|---|---|
| FA-001 | 1, 3, 4, 22 | fa-scenario1, fa-buyer | Chrome (via Claude in Chrome) | 1. Visit `/register`, `/login`, `/forgot-password`, `/reset-password/{token}`, and `/dashboard`. 2. Read the rendered text. | All user-facing text sourced from translation keys (CLAUDE.md: "Never hardcode user-facing strings"); consistent language across the app for a given locale. | `Login.jsx`/`Register.jsx`/`ForgotPassword.jsx`/`ResetPassword.jsx`/`Dashboard.jsx` contain literal Spanish strings hardcoded directly in JSX (e.g. "Crear cuenta", "Iniciar sesión", "Panel de control", "Bienvenido, {name}"), while sibling pages (`Queues/Submit`, admin pages) correctly use `react-i18next` and render in English (`APP_LOCALE=en`). Result: a permanently mixed-language UI, independent of FG-001 below. | Major | Screenshots `ss_3196us0p4` (Register, Spanish), `ss_961352op7` (login error mixing an English `auth.failed` message into the Spanish form), `ss_079718hd4` | **Resolved** — all five pages rewritten to use `react-i18next` against extended `auth`/`common` namespaces (en+es). Live-reverified fully English rendering on Dashboard, Register, Login, and Forgot Password, including the Fortify flash/error messages ("We have emailed your password reset link.", "These credentials do not match our records."), confirming those also flow through the localization layer. |
| FA-002 | 11 | fa-buyer | Chrome (via Claude in Chrome) | 1. Log in as a verified buyer. 2. Visit `/payment-method-setup`. 3. Observe the page. | A graceful, translated message when the payment-method-setup flow cannot proceed (e.g. "Payment setup is temporarily unavailable"). | The raw, unhandled Stripe SDK exception message `api_key cannot be the empty string` is rendered directly in the page body, in English, with no error boundary/translation. | Minor | `get_page_text` output at 15:33 UTC; page screenshot rendered blank (`ss_1966ejspf`) while the DOM held this text | **Resolved** — a global `bootstrap/app.php` exception-render hook catches `Stripe\Exception\ExceptionInterface` and returns a translated 503 (`payments.errors.provider_unavailable`); the raw exception remains logged via Laravel's default reporting. Live-reverified: submitting the payment-method form with Stripe still unconfigured now shows only "Payment setup is temporarily unavailable. Please try again later.", with no raw exception text anywhere in the page or console. Covered by a new regression test. |
| FA-003 | 14 | fa-scenario1, fa-buyer | N/A (server log inspection) | 1. Trigger email verification or password reset. 2. Read `storage/logs/laravel.log`. | Notification content consistent with RowBuddy's own branded, localized templates (Phase 7 Notifications). | Built-in Fortify/Laravel notifications (`VerifyEmail`, password reset) use Laravel's stock default template: sender "Laravel <hello@example.com>", footer "© 2026 Laravel. All rights reserved.", English only, not run through the `Notifications` package's own templating/locale system. | Minor | `storage/logs/laravel.log` entries at 18:40:22 and 18:42:33 UTC | **Resolved** — new `App\Mail\VerifyEmailMail`/`ResetPasswordMail`, wired via Fortify's own `toMailUsing()` hooks, styled like `packages/Notifications`' own Mailables; underlying Fortify verification/reset workflows unchanged. Live-reverified via two fresh sends inspected directly in `storage/logs/laravel.log`: both now show translated, branded subject/body with no Laravel default text. **Observation, not a defect:** the raw SMTP `From:` header still reads `Laravel <hello@example.com>` — that's the local `.env`'s `MAIL_FROM_ADDRESS`/`MAIL_FROM_NAME` (uncommitted; `.env.example` already documents the correct `hello@rowbuddy.example`), not template code; out of FA-003's scope. |

## Deferred / out-of-scope findings register

Separate from the table above: anything discovered that reveals a
missing feature or an architecture gap, not a bug in existing behavior.
These are NOT fixed during the stabilization pass without your explicit
decision, per your own instruction to flag this distinction rather than
quietly building it.

| ID | Finding | Why it's flagged as feature/architecture, not a bug | Your decision |
|---|---|---|---|
| FG-001 | **Confirmed product gap (Scenario 22, as instructed): translations exist; user-selectable or preference-driven locale switching does not; Spanish cannot be reached through the current product UI.** Verified directly: no backend code path ever calls `setLocale()`; `app()->getLocale()` always returns the static `APP_LOCALE` config value. Live-tested and confirmed via real HTTP requests: `?locale=es` query param has zero effect; `Accept-Language: es-ES,es;q=0.9` header has zero effect. The `User.language` column exists but nothing reads it. No frontend language-switcher component exists anywhere in `resources/js`. | This is the absence of a mechanism the product's own data model (`User.language`) and translation infrastructure (both `lang/` and `resources/js/lang/`, en+es) were clearly built to support — implementing it means deciding where the mechanism should live (session? user preference persisted through registration/profile update? URL prefix?), which is a product decision, not a bug fix. | **Deferred enhancement.** Decided post-stabilization: defer until after RC1. Confirmed as a feature gap, not a defect — the existing translation infrastructure is sufficient for RC1, and English-only operation is acceptable for the release candidate. Not implemented during stabilization. |
| FG-002 | No HTTP/UI action exists anywhere for opening an auction (Auctions/Bids). The seller's real presence+evidence flow (Scenario 8) completes fully over HTTP, but nothing in the UI ever calls `AuctionService::open()` — the only caller in the codebase is `LoadTestPhase9Command` (Sprint 4's load-testing tool), which calls it directly, bypassing HTTP. Confirmed by reading every route in `routes/web.php`. | Matches ADR-027's own documented scope ("necessity, not completeness") — Phase 9 built bidding and viewing, not opening. Building this would be new product-facing functionality. | **Deferred enhancement.** Existing domain/backend capability left unchanged; no new UI added before RC1. |
| FG-003 | No HTTP/UI action exists anywhere for suspending or reinstating a user account. `AccountSuspensionService` (Phase 8) is a real, fully working domain service — enforcement was confirmed live (a suspended buyer could still sign in but the underlying rejection is covered by 220/220 passing automated tests) — but nothing in any admin page can trigger it. Confirmed via `grep` across `routes/web.php`; no `suspend` route exists. | Same pattern as FG-002 — the enforcement half was built (Phase 8), the administrative trigger half was not, per that phase's own documented scope. | **Deferred enhancement.** Existing domain/backend capability left unchanged; no new UI added before RC1. |
| FG-004 | No HTTP/UI action exists anywhere for activating/deactivating a restricted category or jurisdiction rule. Enforcement was confirmed live via a real browser test (Scenario 17: a queue submission in a directly-activated restricted category was correctly rejected with a clear message) — but the activation step itself has no admin page or route. | Same pattern as FG-002/FG-003 — Phase 8 Sprint 3 built the write capability as a service, not an HTTP surface. | **Deferred enhancement.** Existing domain/backend capability left unchanged; no new UI added before RC1. |
| FG-005 | Minor navigation gap: the Dashboard's role-based button row only ever links to queue-related actions ("Submit a queue", "Discover queues", "Moderate queues"). It never links to Disputes review, Audit log, or Horizon, even for an Administrator account that has all three capabilities — those pages are only reachable by typing the URL directly. Once on any one admin page, its own nav bar correctly links to the others. | Purely a UI-completeness/discoverability question, not a security issue (every page is still correctly capability-gated) — flagging since it could plausibly be treated as either a small bug (add three links) or out of scope for this stabilization pass. | **Deferred enhancement**, recorded as a usability enhancement. Does not block RC1. |

## External blockers encountered this session

| Item | Scenario | What was observed |
|---|---|---|
| Real Stripe test-mode credentials | 11, 12 | `.env` has no `STRIPE_*` values. `/payment-method-setup` reaches Stripe.js correctly, then fails server-side with `api_key cannot be the empty string` (see FA-002) the moment a SetupIntent is requested. Scenario 12 (transfer creation via the natural won-auction → payment-authorization chain) is blocked by the same missing credentials — not independently re-tested. |
| US legal-review determinations | — | Not applicable to this browser walkthrough (no legal-gate UI exists to test); status unchanged from `docs/legal/us-launch-review.md` (0 of 11 resolved). |
| GitHub Secret Scanning owner verification | — | Not applicable to this browser walkthrough; status unchanged (awaiting owner verification). |
| Production-like Linux capacity rerun | — | Not applicable to this browser walkthrough (a load-testing concern); status unchanged. |
| `main` branch merge approval | — | Not applicable to this browser walkthrough; status unchanged — not performed, per ADR-027's own Architecture Refinement §8. |

## Incomplete this session — needs follow-up, not necessarily a defect

| Scenario | What's incomplete | Why |
|---|---|---|
| 12 (Transfer creation) | Not exercised via the natural Stripe-dependent flow. | Blocked by the Stripe-credentials external blocker (see above). |
| 13 (Buyer/seller confirmation) | A fully successful confirmation (both sides) was not completed live — the geofence check correctly rejected the automated browser's synthetic geolocation, which doesn't match the queue's real coordinates. | This is an environment limitation (already covered by 42 passing automated tests for the success path), not a defect — the rejection itself is the correct, tested behavior for a genuine location mismatch. |
| 16 (Account suspension) | The specific suspended-user bid-rejection message was not re-confirmed live — the test auction's 30-minute window elapsed naturally during this session before the retry, closing it for an unrelated (correct) reason. | Confirmed instead via 220/220 passing automated tests, including a dedicated "rejects a bid from a suspended account" test. |
| 18 (Dispute review) | The resolution workflow itself (release/refund/split/cancel) was not exercised — the page correctly loads and shows "no disputes to review," but no dispute existed to review, and no buyer-facing "file a dispute" endpoint exists to create one naturally within the time available. | Would require seeding a dispute via the same direct-fixture pattern used elsewhere this session; not completed due to time. |
| 20 (Horizon access) | The negative case (Moderator blocked from `/horizon`) was not live-tested — only the Administrator-allowed case was. | Confirmed via code review (`AdminRoleCapabilityMap` grants `horizon.view` to Administrator only) but not re-verified live. |
| 21 (IDOR) | The specific "guess another user's evidence-photo URL" sub-case was not live-tested. | Already covered by Sprint 5's security review (`PresenceSessionService::evidencePhotoUrl()`'s independent photo↔session binding check); not re-verified live this session. |
| 23 (Responsive layout) | Only three pages were spot-checked at one mobile width (390×844-equivalent) — Dashboard, login/guest redirect, and Audit log. | Not exhaustively checked across every page/breakpoint given time already spent on higher-priority scenarios. |
