# Mobile Sprint 1 Architecture — Auth Backend + Auth Screens

Status: presented for review. No code written yet, on either side of the
repo. Per ADR-028's proposed sprint breakdown (§14) and Decision 8's
mandatory test-coverage requirement.

## Backend: the new `/api/v1` surface, inside `apps/web`

Per ADR-028 Decision 2's `apps/api` resolution: everything below lives in
`apps/web`, not a new application.

### Wiring

- `composer require laravel/sanctum`, `php artisan vendor:publish
  --tag=sanctum-config` (adds `config/sanctum.php` and the
  `personal_access_tokens` migration — Sanctum's own standard migration,
  unmodified).
- `bootstrap/app.php`: add `api: __DIR__.'/../routes/api.php'` to the
  existing `withRouting()` call, alongside `web`/`commands`/`channels`/
  `health`. No change to any existing entry.
- New `apps/web/routes/api.php`, entirely wrapped in
  `Route::prefix('v1')->group(...)` — giving every route below its
  `/api/v1/...` path. We are **not** using Sanctum's SPA/cookie mode
  (`EnsureFrontendRequestsAreStateful`) anywhere — every protected route
  below uses `auth:sanctum` (the token guard) only, per ADR-028 Decision
  2's explicit rejection of cookie-based auth for a native client.

### Endpoints

| Method & path | Guard | Reuses | Notes |
|---|---|---|---|
| `POST /api/v1/auth/register` | guest | `app(CreatesNewUsers::class)->create(...)` — resolves the bound `App\Actions\Fortify\CreateNewUser`, same as web | Issues a Sanctum token immediately on success (mirrors web's own "an unverified user may sign in" posture, confirmed by the existing `EmailVerificationTest` suite). Fortify's own `Registered` event fires automatically from `User::create()`'s creation path exactly as it does for web, so the verification email send is unchanged, not duplicated. |
| `POST /api/v1/auth/login` | guest | `Laravel\Fortify\Http\Requests\LoginRequest::authenticate()` | This is the actual reusable unit here — it already encapsulates the named `login` rate limiter (`FortifyServiceProvider`'s `RateLimiter::for('login', ...)`, keyed by email+IP, 5/min) and lockout-event firing. We call `$loginRequest->authenticate()` for the credential check, then issue a token — we never establish a session. |
| `POST /api/v1/auth/logout` | `auth:sanctum` | — | `$request->user()->currentAccessToken()->delete()` — only the calling token, never another. |
| `POST /api/v1/auth/forgot-password` | guest | `Password::sendResetLink($request->only('email'))` | This step has no distinct Fortify Action class to reuse — Fortify's own web controller calls the same `Password` broker directly. This is not a gap in the "zero new domain logic" principle; there was never a separate Action here to duplicate. |
| `POST /api/v1/auth/reset-password` | guest | `Password::reset($credentials, fn ($user) => app(ResetsUserPasswords::class)->reset($user, $input))` | Genuinely reuses `App\Actions\Fortify\ResetUserPassword`, resolved via its contract. |
| `POST /api/v1/auth/email/verification-notification` | `auth:sanctum` | `$request->user()->sendEmailVerificationNotification()` | Throttled identically to web: `throttle:'.config('fortify.limiters.verification', '6,1')` — read from the same config key Fortify's own package route reads, not a hardcoded duplicate of the "6,1" value. |
| `GET /api/v1/me` | `auth:sanctum` | — | Returns `{ id, name, email, email_verified_at }` only. Deliberately **not** account-standing (ADR-028 assigns that self-visibility endpoint to Sprint 5) — kept minimal, scoped to what splash/token-check actually needs. |

**Not a new endpoint**: clicking the emailed verification or
password-reset link still opens the existing signed **web** route
(`verification.verify`, `password.reset`) exactly as it does today — see
the one open question below for what happens next.

### One open question — the deep-link interstitial mechanism

ADR-028 Decision 2 says the verification/reset link "continues to open
the existing web route... with a 'return to the app' deep-link
interstitial," but doesn't specify how. Two ways to build it, and I'd
like your call before touching any existing web behavior:

- **(a) Modify the existing web success page** (`verification.verify`'s
  post-redirect / the reset-password success state) to always show a
  generic "you're verified — return to the app if you're on mobile" deep
  link. Simplest, but changes something *every* web user sees, not just
  mobile ones — a small UX change to already-accepted behavior.
- **(b) A separate, additive branch**: detect a mobile-originated link
  (e.g. the mobile app requests the reset/verification email include a
  `?client=mobile` marker, or a dedicated mirrored route) and only show
  the deep-link interstitial on that branch, leaving the existing web
  success behavior for everyone else completely untouched.

**Recommendation: (b)** — it's strictly additive, matching this sprint's
own "zero new domain logic, minimal footprint" posture, at the cost of
one small new conditional branch instead of a shared one. I have not
implemented either — flagging for your decision before I touch any
existing web route's behavior.

### Mandatory test coverage (ADR-028 Decision 8) — one plan per endpoint

Every endpoint above gets, at minimum:

- **Feature**: happy path + key domain-validation-error paths (duplicate
  email on register, wrong password on login, invalid/expired token on
  reset, unverified vs. verified state where relevant).
- **Authorization**: unauthenticated requests to `logout`,
  `email/verification-notification`, and `me` get 401; a revoked token
  gets 401 on its next use.
- **IDOR**: `me` returns only the calling token's own user — tested with
  two distinct users' tokens to confirm neither ever sees the other's
  data; `logout` revokes only the calling token, confirmed by checking a
  second token for the same user (or another user's token) still works
  immediately after.
- **Contract**: exact response-shape assertions for every endpoint (no
  password hash, no internal Sanctum token id leaking into the body
  where it shouldn't, no unexpected extra fields).

## Mobile: `src/features/auth` + supporting infrastructure

- **`src/api`**: a small fetch wrapper reading the token from
  `src/lib/secureStore.ts` and attaching `Authorization: Bearer <token>`;
  one function per endpoint above. First real consumer of `src/api`
  (reserved since Sprint 0).
- **`src/types`**: hand-written types for each request/response shape in
  the table above (ADR-028 Decision 1 — no generated client).
- **Screens** (`src/features/auth`): splash/token-check (reads
  SecureStore → calls `GET /me` → routes to an authenticated placeholder
  or clears the token and routes to Login), Login, Registration, Forgot
  Password, Reset Password (only reachable via the deep-link decision
  above), and a "verify your email" state with a resend button for a
  logged-in-but-unverified user.
- **i18n**: a new `auth` namespace (`en`/`es`) under
  `src/i18n/locales`, mirroring web's own `auth.json` key groups where it
  makes sense, adapted for mobile copy.
- **TanStack Query**: one mutation per write endpoint (register, login,
  logout, forgot-password, reset-password, resend-verification), one
  query for `me`. Per ADR-028 Decision 7/§9: none of these mutations
  retry automatically — login/register/reset all have real side effects
  on failure paths that shouldn't be silently repeated.

## What Sprint 1 does not touch

No change to any existing web session/Inertia auth route, `verified`
middleware's existing behavior, or any other bounded-context package. No
`AccountStandingLookup`-based self-visibility endpoint (Sprint 5). No
push-notification device-token registration (Sprint 6).
