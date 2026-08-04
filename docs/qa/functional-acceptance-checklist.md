# Functional Acceptance — Browser Checklist

Manual, browser-driven validation of the finished web application.
Scope: bug-finding only — no new features, no architecture changes, no
mobile work. Every failure found here becomes an entry in
`docs/qa/functional-acceptance-issue-log.md` and is fixed in a
stabilization pass, held uncommitted until the finding is documented and
approved.

## Environment status (verified before this checklist was written)

| Service | Status |
|---|---|
| `app` (php-fpm) | Healthy — `/up` returns 200 through nginx |
| `nginx` | Healthy — up 12 days |
| PostgreSQL | Healthy — `pg_isready` accepting connections |
| Redis | Healthy — `PONG` |
| Horizon | Healthy — "Horizon is running" |
| Reverb | **Resolved for this session, then reverted.** Host port 8080 remains held by an unrelated process (PID 14576, `java`) — that is unchanged and still true in the default configuration. Per your approved approach, Scenario 10 was executed using a temporary host port remap (8081), decoupled from the backend's own internal Reverb port (which stayed 8080, unchanged, for server-to-Reverb publishing). Live update confirmed working through the remapped port, then the port mapping (`docker-compose.yml`, `apps/web/.env`) was fully reverted — `git status`/`git diff` confirmed clean afterward. The unrelated host process itself was never touched. |

## Test accounts

Four accounts were created directly in the database (not through the
UI, to have them ready before the walkthrough begins), all pre-verified
except where a test specifically needs an unverified state:

| Role | Email | Notes |
|---|---|---|
| Buyer | `fa-buyer@rowbuddy.test` | Verified, no admin role |
| Seller | `fa-seller@rowbuddy.test` | Verified, no admin role |
| Moderator | `fa-moderator@rowbuddy.test` | Verified, `admin_role_assignments.role = moderator` |
| Administrator | `fa-admin@rowbuddy.test` | Verified, `admin_role_assignments.role = administrator` |

Shared password given separately (not written into this committed
file, per CLAUDE.md's "never commit secrets" — a throwaway local test
password doesn't need to be in git history either). A fifth,
**unverified** account is not pre-created — Scenario 1 below creates one
live, which is the more realistic test of the actual registration/
verification flow than seeding one artificially.

## External blockers — record, do not attempt to fabricate

Per your instruction, these are recorded here and must be marked
**External blocker**, not a pass/fail, wherever they gate a scenario:

- **US legal-review determinations** — 0 of 11 items resolved
  (`docs/legal/us-launch-review.md`). Does not block any scenario below
  directly (no legal-gate UI exists to test), but the restricted-
  category/jurisdiction-rule scenario (17) can only exercise the
  *mechanism*, not a real launch determination — confirmed: see FG-004
  and Scenario 17's result.
- **Real Stripe test-mode credentials** — not configured. Blocks
  Scenario 11 (payment-method setup) from completing against real
  Stripe, and cascades to Scenario 12 (transfer creation); confirmed —
  see the results summary above.
- **Production-like Linux capacity rerun** — out of scope for this
  checklist entirely (a load-testing concern, not a browser-functional
  one); recorded here only so it isn't mistaken for something this
  checklist could close.
- **GitHub Secret Scanning owner verification** — same as above, not a
  browser-testable item.
- **`main` branch merge approval** — same, not a browser-testable item.

## Results summary

Walkthrough executed via Claude in Chrome, this session. Full defect and
feature-gap entries are in `docs/qa/functional-acceptance-issue-log.md`.

| # | Scenario | Result |
|---|---|---|
| 1 | Registration | **Pass** (mechanism) — FA-001 found (hardcoded Spanish) |
| 2 | Email verification | **Pass** — full flow (gate redirect, resend, verify link, re-attempt) confirmed |
| 3 | Login / logout | **Pass** — correct credentials, wrong-password rejection, logout all confirmed |
| 4 | Password recovery | **Pass** — reset link, new password, login all confirmed |
| 5 | Queue/listing submission | **Pass** |
| 6 | Moderation | **Pass** — approve→publish, reject-with-reason, admin-403-for-buyer all confirmed |
| 7 | Public auction viewing | **Pass** — allowlisted fields only, no PII |
| 8 | Presence, evidence, opening an auction | **Pass** (presence/evidence mechanism) + **FG-002** (no UI to open an auction at all) |
| 9 | Bidding | **Pass** — valid bid, self-bid rejection, below-price rejection all confirmed |
| 10 | Live Reverb updates | **Pass** — confirmed via the approved temporary port workaround, then reverted |
| 11 | Payment-method setup | **External Blocker** (Stripe credentials) — reaches Stripe.js correctly; FA-002 found (raw exception shown) |
| 12 | Transfer creation | **Blocked** by the same Stripe-credentials external blocker |
| 13 | Buyer/seller confirmation + QR | **Pass** (substantially) — QR retrieval, IDOR rejection, geofence rejection all confirmed live; full successful confirmation not completed (see issue log) |
| 14 | Notifications | **Pass** (mechanism) — FA-003 found (unbranded/unlocalized built-in auth emails) |
| 15 | Ratings | **N/A** — no HTTP/UI surface exists (documented Phase 7 scope) |
| 16 | Account suspension | **Pass** (enforcement, via automated tests) + **FG-003** (no UI to suspend an account at all) |
| 17 | Restricted-category controls | **Pass** (enforcement, confirmed live) + **FG-004** (no UI to activate a restriction at all) |
| 18 | Dispute review | **Incomplete** — page loads correctly; resolution flow not exercised this session (see issue log) |
| 19 | Audit visibility | **Pass** |
| 20 | Horizon access | **Pass** (Administrator-allowed case only; Moderator-blocked case not re-verified live) |
| 21 | Authorization/IDOR checks | **Pass** (substantially, via scenarios 6/9/13; one sub-case not re-verified live) |
| 22 | English/Spanish rendering | **FG-001 confirmed** (no locale-switching mechanism exists) + FA-001 (hardcoded Spanish) |
| 23 | Responsive layout | **Pass** (spot-checked: dashboard, audit log, at one mobile width) |

**Totals**: 16 Pass, 2 Blocked/External-Blocker (11, 12), 1 N/A (15), 1 Incomplete (18), plus 3 defects (FA-001/002/003) and 5 feature-gap findings (FG-001–005) recorded in the issue log — none of which are "Fail" in the sense of contradicting documented behavior; every Pass reflects a real, confirmed correct mechanism.

## Scenarios

Each scenario: steps, expected result, and which account/role to use.
Actual results, screenshots, and issue IDs go in the issue log, not
here — this document is the plan, not the record.

### 1. Registration
Visit `/register` (logged out). Submit a new name/email/password.
**Expected**: account created, logged in immediately, redirected to
`/dashboard`, `email_verified_at` is null for this new user.

### 2. Email verification
Continuing from Scenario 1 (or any unverified session). Visit an action
gated by `verified` (e.g. `/queues/submit` → submit) — **expected**:
redirected to `/email/verify`, not shown a raw error. On that screen,
click resend — **expected**: success message, no error. This
environment's mail driver is `log` (`MAIL_MAILER=log`) — there is no
Mailpit/inbox UI; the verification link must be read out of
`storage/logs/laravel.log` (`docker compose exec app tail -f storage/logs/laravel.log`
or equivalent, since the log is structured JSON per
`docs/operations/observability.md`, look for the message body containing
the signed URL). Open that link — **expected**: redirected away from the
verify screen, `email_verified_at` now set. Re-attempt the same gated
action — **expected**: now succeeds past the gate (may still fail on its
own validation, that's fine — the gate itself must not block).

### 3. Login / logout
Buyer account. Log in with correct credentials — **expected**: success,
redirected to dashboard. Log out — **expected**: session ends, protected
pages redirect to `/login`. Attempt login with a wrong password —
**expected**: rejected, no account-existence leak in the error message.

### 4. Password recovery
Logged out. Visit `/forgot-password`, submit the buyer's email —
**expected**: success message regardless of whether the email exists
(no enumeration). Retrieve the real reset link from
`storage/logs/laravel.log` (see Scenario 2 — same `log` mail driver) and
follow it — **expected**: reset form, submit a new password, then log in
with the new password successfully.

### 5. Queue/listing submission
Seller account, verified. Submit a new queue via `/queues/submit`.
**Expected**: appears in the admin moderation queue as `pending`, not
immediately published; English and Spanish validation messages both
readable (see Scenario 21).

### 6. Moderation
Moderator account. View the pending-queue list. Approve one, reject
another with a reason. **Expected**: approved queue becomes `published`
and discoverable; rejected queue shows the reason to nobody but is
recorded; a `queues.moderate` capability check — log in as the Buyer
account and confirm `/admin/queues` is inaccessible (403 or redirect,
not a rendered admin page).

### 7. Public auction viewing
Logged out (or any account). Once a queue is published and an auction
opened against it (this requires driving the seller through presence +
evidence first — see Scenario 8), visit the auction's public page.
**Expected**: status, current price, closing deadline, bid count visible;
no seller identity, no bidder identity, no GPS/proximity data anywhere
in the page or its network responses.

### 8. Presence, evidence, and opening an auction
Seller account. Start a presence session against the published queue,
submit a GPS ping within the geofence, upload an evidence photo, then
open the auction (via whatever UI path exists for this — confirm one
does; if not, note it as a finding, don't invent a workaround).
**Expected**: real presence flow completes without the LiveProximityChecker
rejecting it; the resulting auction is immediately visible via
Scenario 7.

### 9. Bidding
Buyer account, verified, on the open auction from Scenario 8.
**Expected**: a valid bid is accepted and reflected as the new current
price; a bid below the current price is rejected with a clear message
(English and Spanish); a duplicate rapid double-submit does not create
two bids (idempotency).

### 10. Live Reverb updates
**Blocked pending port 8080** (see Environment status above). Once
unblocked: with the auction page open in two separate browser sessions
(or two tabs, different accounts), place a bid from one and confirm the
other updates without a manual refresh.

### 11. Payment-method setup
Buyer account. Visit the payment-method setup page and attempt to add a
card via Stripe test mode. **If real Stripe test credentials are
configured**: complete with Stripe's own test card number, confirm a
payment method is saved with no raw card data ever visible in network
requests to this app's own backend. **If not configured** (current
state): proceed only as far as confirming the flow correctly reaches
Stripe's own client-side SDK, then mark this scenario **External
blocker**, not pass or fail.

### 12. Transfer creation
Requires an auction to actually resolve to a winner (may need to let
Scenario 9's auction's closing deadline pass, or use whatever
admin/dev mechanism exists to force this in a test environment — note
if none exists cleanly). **Expected**: a `Transfer` is created for the
winning bid once payment is authorized (blocked by Scenario 11's
external blocker if no real Stripe credentials exist — note the
dependency explicitly if this scenario can't proceed for that reason).

### 13. Buyer/seller confirmation and the QR/confirmation-code flow
Given a `Transfer` from Scenario 12. Buyer retrieves the QR/confirmation
token; seller confirms using it; buyer confirms their own side.
**Expected**: both confirmations succeed once genuinely both parties
act; a wrong-role attempt (buyer trying the seller-only confirm action)
is rejected; an unrelated third account is rejected identically to a
stranger.

### 14. Notifications
Across Scenarios 5–13, check `storage/logs/laravel.log` (this
environment's `log` mail driver — no real inbox) for the expected
transactional emails: registration verification, any
transfer-confirmed notification, etc. **Expected**: each fires exactly
once, in the recipient's own stored locale, with no missing translation
key rendered literally (e.g. no `transfers.confirmed.subject` showing up
as literal text instead of translated content). Note: given Scenario
22's finding below, "the recipient's own stored locale" may not be
reachable at all in practice if no mechanism ever sets it away from the
default — check what locale these emails actually render in, and record
it as observed, not assumed.

### 15. Ratings
Note: per Phase 7's own closure, Ratings has **no HTTP/UI surface** —
domain/backend only. This scenario cannot be executed from the browser
at all. Record this as a documented limitation, not a failure — nothing
to test here without building a new feature, which is out of scope for
Functional Acceptance.

### 16. Account suspension
Administrator account. Suspend the Buyer test account (via whatever
admin action exists — confirm the actual UI path). **Expected**: the
suspended Buyer can no longer place a bid (rejected with a clear
reason), but can still sign in and see their own account status —
confirm exactly what a suspended user can/cannot still do matches
Phase 8's documented behavior.

### 17. Restricted-category controls
Administrator account. Activate a restriction against a category/
jurisdiction. **Expected**: a new queue submission in that category/
jurisdiction is blocked at submission time with a clear reason; this is
purely a mechanism test — no real legal determination backs whichever
category is chosen for this test, and this must not be reported as
"restricted categories are legally validated."

### 18. Dispute review
Requires a filed dispute. Per Phase 6, disputes are filed only against
a `Confirmed` transfer, and there's no general buyer-facing "file a
dispute" HTTP endpoint — confirm whether one exists; if not, this
scenario may only be reachable by whatever mechanism disputes are
actually created through in this environment (note it plainly). Once a
dispute exists: Administrator account reviews it via `/admin/disputes`,
records a resolution. **Expected**: resolution outcome (release/refund/
split/cancel) is applied correctly; a resolved dispute cannot be
resolved again.

### 19. Audit visibility
Administrator account. View `/admin/audit-log`. **Expected**: the
actions taken during this walkthrough (queue approval, dispute
resolution, restriction activation, account suspension) appear with
acting administrator, timestamp, and reason where applicable; only the
36 allowlisted event types render with real fields — confirm no
unregistered event type produces a broken/blank row.

### 20. Horizon access
Administrator account: visit `/horizon` — **expected**: accessible.
Moderator account: same URL — **expected**: rejected (403), confirming
Administrator-only, not just "any admin."

### 21. Authorization and IDOR checks
Deliberately attempt cross-account access with each non-admin account:
- Buyer tries to view/confirm a Transfer they're not a party to.
- Buyer tries the seller-only transfer-confirm action on a transfer
  they *are* a party to, but as the wrong role.
- Any authenticated non-admin tries `/admin/*` routes directly by URL.
- Buyer tries to retrieve another user's evidence-photo URL by guessing
  a photo id.
**Expected**: every attempt rejected, none leak whether the resource
exists to an unauthorized party beyond a generic "not found"/"forbidden".

### 22. English and Spanish rendering

**Pre-existing concern found while writing this checklist, not yet
confirmed as a real defect**: I could not find any mechanism anywhere
in the backend that ever calls `setLocale()` — `app()->getLocale()`
always returns the static `APP_LOCALE` config value; no middleware,
controller, or route reads the User model's own `language` column or
an `Accept-Language`/query-param signal to change it. I also could not
find a language-switcher UI component anywhere in the frontend. If
this is accurate, there may currently be **no way for a user to
actually change locale at all** through the running application — the
bilingual translation *content* exists (both `lang/en`/`lang/es` and
`resources/js/lang/en`/`es`), but nothing switches which one renders.

Test this directly rather than assuming: try every plausible mechanism
(a UI element I may have missed, `?locale=es`, setting
`Accept-Language: es`, changing the `language` column directly via
tinker and reloading) against at least registration, a validation
error, and one transactional email. **Expected, if a mechanism exists**:
content switches correctly, no literal translation key ever renders as
visible text, date/currency formatting is locale-appropriate (aside
from the already-known ICU number/date-formatting gap ADR-027 §7
documents, which is not a new finding if it's what you see). **If no
mechanism exists at all**: this is a functional-acceptance finding —
record it, but flag to José explicitly that this reads more like a
missing feature (nothing currently reads the stored preference) than a
small bug, per his own instruction that this distinction needs his
decision, not a quiet fix.

### 23. Responsive layout
At minimum: a common mobile viewport width and a common tablet width, on
the login page, dashboard, an auction page, and the admin moderation
page. **Expected**: no horizontal scroll, no overlapping/unreadable
content, primary actions remain reachable.

## Execution order note

Scenarios 5→8→9→10→11→12→13 are a dependency chain (queue → moderation
→ presence/evidence → auction → bid → live update → payment → transfer
→ confirmation) — later ones in that chain can't run until earlier ones
succeed. If an earlier one fails, everything downstream gets recorded as
**Blocked by [issue ID]**, not independently re-tested with a workaround.
