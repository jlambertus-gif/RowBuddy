# Stabilization Summary

Follows the Functional Acceptance walkthrough recorded in
`functional-acceptance-checklist.md` and `functional-acceptance-issue-log.md`.
Scope was limited, by explicit instruction, to the three confirmed defects
below — no feature-gap item (`FG-001` through `FG-005`) was implemented.

## Fixes

### FA-001 (Required) — Remove remaining hardcoded user-facing strings

`Login.jsx`, `Register.jsx`, `ForgotPassword.jsx`, `ResetPassword.jsx`, and
`Dashboard.jsx` had literal Spanish strings hardcoded directly in JSX,
while every other page in the app correctly rendered in English via
`react-i18next` (`APP_LOCALE=en`). All five pages were rewritten to source
every string from translation keys, extending the `auth` namespace
(`login`, `register`, `forgot_password`, `reset_password` groups) and
adding a `dashboard` group to the `common` namespace, in both `en` and
`es`.

Live-reverified: Dashboard, Register, Login, and Forgot Password all
render fully in English end-to-end, including Fortify's own flash/error
messages ("We have emailed your password reset link.", "These
credentials do not match our records.") — confirming those already flow
through the localization layer rather than being a separate hardcoded
source.

### FA-002 (Required) — Replace the raw Stripe exception with a friendly message

A misconfigured/absent Stripe API key threw `Stripe\Exception\ExceptionInterface`
during Laravel's own dependency-injection resolution — before any
controller code, including a local `try`/`catch`, could run — so the raw
SDK message (`api_key cannot be the empty string`) reached the client
directly. Fixed with a global exception-render hook in `bootstrap/app.php`
that catches `ExceptionInterface` and returns a translated 503
(`payments.errors.provider_unavailable`) for JSON requests. The original
exception is still captured by Laravel's default exception reporting
(logs), unchanged.

Live-reverified: submitting the payment-method-setup form as a verified
buyer, with Stripe still unconfigured, now shows only "Payment setup is
temporarily unavailable. Please try again later." — no raw exception text
anywhere in the page or browser console. Covered by a new regression test
asserting the 503 status, the translated message, and the absence of the
raw Stripe text from the response body.

### FA-003 (Recommended) — Branded verification/reset email templates

Fortify's built-in `VerifyEmail`/`ResetPassword` notifications rendered
Laravel's stock default template (sender "Laravel <hello@example.com>",
footer "© 2026 Laravel..."), inconsistent with `packages/Notifications`'
own branded, translated style. Added `App\Mail\VerifyEmailMail` and
`App\Mail\ResetPasswordMail`, matching that package's existing Mailable
style (translated greeting/body/action/footer paragraphs, no external
branding), wired via `VerifyEmail::toMailUsing()` /
`ResetPassword::toMailUsing()` in `AppServiceProvider`. The underlying
Fortify verification/reset workflows themselves are unchanged — only the
rendered email content.

A real bug surfaced and was fixed along the way: `Illuminate\Notifications\Channels\MailChannel`
does not automatically set a recipient when `toMail()` returns a raw
`Mailable` (only the `MailMessage` path does), so the first version
failed with *"An email must have a 'To'... header."* Fixed by passing the
recipient email explicitly into each Mailable's `Envelope`.

Live-reverified via two fresh sends inspected directly in
`storage/logs/laravel.log`:
- Password reset: subject *"Reset your password"*, fully translated,
  branded body, no Laravel default text.
- Fresh registration: subject *"Verify your email address"*, fully
  translated, branded body, no Laravel default text.

**Observation, not a defect:** the raw SMTP `From:` header still reads
`Laravel <hello@example.com>` in the local dev environment — that's
`MAIL_FROM_ADDRESS`/`MAIL_FROM_NAME` in the uncommitted local `.env`
(`.env.example` already documents the correct
`hello@rowbuddy.example`/`${APP_NAME}`), not template code. Out of
FA-003's scope, and not something this pass should or did change — flagged
so a real deployment's environment configuration carries the correct
values.

## Validation

All checks below were re-run fresh, against the final stabilized diff,
immediately before this report — not carried over from an earlier run.

| Check | Result |
|---|---|
| Pest suite (full) | 223 passed, 0 failed (799 assertions), including 2 new FA-002/FA-003 regression tests |
| PHPStan | No errors |
| Pint — files touched this pass (10 files) | Clean |
| Pint — full repository | 11 pre-existing style issues, all in Fortify scaffolding last touched in Phase 9 Sprint 5 (`ca2b706`); none in any file this pass touched |
| `composer audit` | No security vulnerability advisories found |
| `npm audit` | 0 vulnerabilities |
| Frontend production build | Succeeds (one pre-existing >500kB chunk-size advisory, not an error, not introduced by this pass) |
| Secret scan over the exact stabilization diff | No matches for API keys, private keys, tokens, or credential-style assignments |

## Live re-verification (affected Functional Acceptance scenarios only)

| Scenario | Result |
|---|---|
| 1, 3, 4, 22 (localization-adjacent pages) | Dashboard, Register, Login, Forgot Password all fully English; no residual hardcoded Spanish found |
| 4 (password recovery) | Reset-link email confirmed branded/translated in `laravel.log` |
| 11 (payment-method setup) | Friendly 503 message confirmed live; raw Stripe exception no longer reachable |
| 14 (notifications) | Fresh verification email confirmed branded/translated in `laravel.log` |

## Regressions

None found. Two test artifacts were created and cleaned up during live
re-verification: `fa-buyer@rowbuddy.test`'s password was reset to a known
value for login testing (the original shared password was not available
in this context), and a throwaway `fa-stabilization-check@rowbuddy.test`
account was created and deleted afterward. No committed file was touched
by either action.

## Pre-commit checks

- Secret scan over the exact stabilization diff: clean.
- No throwaway Functional Acceptance account remains (`fa-stabilization-check@rowbuddy.test`
  confirmed deleted).
- `estructura.txt` remains untracked and excluded from the stabilization
  commit.

## Outcome

Stabilization pass is clean. See `functional-acceptance-final-report.md`
for the closed-out Functional Acceptance stage and
`../releases/rc1-readiness-report.md` for the RC1 determination.
