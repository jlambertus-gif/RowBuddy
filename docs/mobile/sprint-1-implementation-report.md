# Mobile Sprint 1 — Implementation Report

Status: held uncommitted, pending review. Backend scope (7 endpoints +
the mobile-return interstitial) and mobile scope (auth screens + typed
client + TanStack Query hooks) are both complete and fully validated.

## Backend — `apps/web`

### Sanctum

`laravel/sanctum` installed; `config/sanctum.php` and the
`personal_access_tokens` migration published and migrated;
`App\Models\User` gained `HasApiTokens`. Guard is pure Bearer-token —
Sanctum's SPA/cookie mode is never used anywhere.

### `routes/api.php`

New file, registered in `bootstrap/app.php` alongside (never replacing)
`web.php`. Everything wrapped in `Route::prefix('v1')`, giving every
route its `/api/v1/...` path.

| Endpoint | Guard | Notes |
|---|---|---|
| `POST /auth/register` | guest | `CreatesNewUsers` contract → `CreateNewUser` action, unchanged |
| `POST /auth/login` | guest, `throttle:login` | Reuses the exact named rate limiter `FortifyServiceProvider` already registers |
| `POST /auth/logout` | `auth:sanctum` | Revokes only the calling token |
| `POST /auth/forgot-password` | guest | `Password::sendResetLink()` via its own callback parameter |
| `POST /auth/reset-password` | guest | `ResetsUserPasswords` contract → `ResetUserPassword` action, plus `CompletePasswordReset` |
| `POST /auth/email/verification-notification` | `auth:sanctum`, `throttle:<verification limiter>` | Same config key Fortify's own route reads |
| `GET /me` | `auth:sanctum` | Identity + verification status only — no account-standing (Sprint 5) |

Every controller is a single, focused, one-action class matching this
codebase's existing convention (`RegisterApiUserController`,
`LoginApiUserController`, etc.), sharing response-shaping via
`RendersApiAuthResponses` (never renders a password hash or any field
beyond `id`/`name`/`email`/`email_verified_at`).

### One correction to the approved Sprint 1 architecture

The architecture doc said login would reuse `LoginRequest::authenticate()`.
That method doesn't exist — Fortify's actual login flow is a five-stage
pipeline (`EnsureLoginIsNotThrottled → AttemptToAuthenticate →
PrepareAuthenticatedSession`, run via `Illuminate\Pipeline\Pipeline`) built
around a stateful, session-logging-in guard, which is the wrong shape for
a stateless API and doesn't have a single reusable method. What's
actually reused, precisely: the **named rate limiter** (`throttle:login`
middleware — the pipeline's own `EnsureLoginIsNotThrottled` action isn't
even active in this app once a named limiter is configured; the route
middleware is the real mechanism, for both web and now mobile), and
`Auth::guard('web')->validate($credentials)` for the credential check
itself (checks against the identical guard/provider/hasher, with zero
session side effects — unlike `attempt()`). Corrected in the code; noting
it here since it's a real deviation from what was reviewed and approved.

### The mobile-return interstitial (your explicit requirements)

- **Explicit mobile origin, never User-Agent**: the marker is only ever
  created by the three mobile-only controllers (`register`,
  `forgot-password`, `email/verification-notification`) — the route
  itself, not any request header, is the signal.
- **Signed, validated, allowlisted return target**: `App\Support\MobileReturnMarker`
  — `Crypt::encryptString()` (confidentiality *and* tamper-proofing in
  one primitive), carrying only `{purpose, expires_at}`. The actual
  redirect target is **never** derived from the marker or any request
  input — always `config('mobile.return_url')`, one server-configured
  value.
- **Custom scheme in dev, verified links before production**:
  `config/mobile.php`'s `return_url`, defaulting to
  `rowbuddy://auth/callback`, swappable to a verified universal link via
  one env var — no code change.
- **No secrets in the interstitial**: the Blade view
  (`resources/views/mobile/return-to-app.blade.php`) receives only a
  translated title and the fixed return URL — never a token, hash,
  signature, or marker.
- **Additive only**: `App\Support\Fortify\VerifyEmailResponse`/`PasswordResetResponse`
  rebind Fortify's own response *contracts* (not routes, not
  controllers); every invalid/missing/expired/wrong-purpose case falls
  through to the exact, unmodified default response by explicit
  delegation to the original Fortify response class.
- **Reset flow's actual shape**: the interstitial fires on the reset
  *success* response, after the (unchanged) web form completes the
  reset — not before. The token is never handed to the app. `ResetPassword.jsx`
  gained one additive field (reads `mobile_return` from its own URL,
  threads it through the POST body) — empty for every ordinary web user.

### Relocation required by this app's own architecture preset

`tests/Architecture/PresetTest.php` enforces `App\Http` may only be used
from within `App\Http`. The two response classes were bound directly
from `AppServiceProvider` (outside `App\Http`), so they now live in
`App\Support\Fortify` instead of `App\Http\Responses\Fortify`. Same
classes, same behavior, correct location.

### One scoped, documented arch-preset exception

`RegisterApiUserController` and `SendApiEmailVerificationNotificationController`
must call `sha1($user->getEmailForVerification())` — byte-identical to
`Illuminate\Auth\Notifications\VerifyEmail`'s own internal hash, required
for the existing `signed` route middleware to validate the link. The
security preset's blanket `sha1` ban now excludes exactly these two
classes, with the reason recorded inline in the test file. No other use
of the banned-function list anywhere in `App\*` is exempted.

### A real environment fix, not a workaround

`php artisan test`'s architecture-preset test now exceeds PHP's default
128M CLI memory limit (AST-parsing a now-larger `app/` tree) — confirmed
by testing with a temporary CLI override. Fixed properly: `phpunit.xml`
now sets `<ini name="memory_limit" value="512M"/>`, scoped to the test
runner only — no Docker image rebuild, no change to the FPM pool serving
real requests. `vendor/bin/phpstan` needs the same bump via its own
`--memory-limit=512M` CLI flag (no project-level config file exists for
it to live in); noting this so it isn't a surprise next time it's run
directly.

## Mobile — `apps/mobile`

- **`src/types/auth.ts`** — hand-written types for every request/response
  shape above.
- **`src/api/client.ts`** — the first real `src/api` consumer (reserved
  since Sprint 0). Attaches the stored Bearer token; on any `401`,
  unconditionally clears it (never trusted to keep retrying a dead
  token) — the caller/UI layer decides what to do next, not this layer.
- **`src/api/auth.ts`** — one function per endpoint.
- **`src/lib/authToken.ts`** — a named token-storage wrapper built on
  Sprint 0's generic `secureStore.ts`.
- **`src/features/auth/hooks/`** — `useLogin`, `useRegister`, `useLogout`,
  `useForgotPassword`, `useResetPassword`, `useResendVerification`,
  `useCurrentUser`. No mutation retries automatically (ADR-028 §9) —
  login/register/reset all have real side effects on failure that
  shouldn't silently repeat.
- **Screens**: `app/index.tsx` (splash/token-bootstrap — validates any
  stored token against `/me`, routes to login, verify-email, or home),
  `app/(auth)/{login,register,forgot-password,reset-password,verify-email}.tsx`,
  `app/auth/callback.tsx` (the interstitial's deep-link target — routes
  to login; the verification/reset already completed server-side by the
  time it fires), `app/home.tsx` (a minimal authenticated placeholder —
  real discovery content is Sprint 2).
- **i18n**: new `auth` namespace, `en`/`es`, registered in `src/i18n/index.ts`.

### The `reset-password` screen's honest limitation

It's built and functional — real form, real validation errors, calls
`POST /api/v1/auth/reset-password` directly — but with today's
custom-scheme interstitial flow, the reset always completes via the
*web* form first (the token is deliberately never in the deep link). This
screen's live trigger is the future state: once verified universal links
(ADR-028 Decision 7) let the OS hand the original emailed link straight
to the app, this screen becomes the real entry point with no other
change needed. Reachable today via a manually-constructed deep link for
testing; not part of the current production-shaped flow. Flagging this
explicitly rather than presenting it as complete when it's precisely as
complete as the current backend design allows.

## Validation — all re-run fresh

| Check | Result |
|---|---|
| Backend Pest suite (full) | **269 passed** (960 assertions) — 223 pre-existing + 46 new |
| PHPStan (`--memory-limit=512M`) | No errors |
| Pint — full repo | 13 pre-existing/vendor-published issues (11 unchanged from RC1's own Fortify-scaffold baseline + `config/sanctum.php` + the Sanctum migration, both vendor-generated, never hand-authored); **zero** in any hand-written file |
| `composer audit` | No advisories |
| Web `npm audit` | 0 vulnerabilities |
| Frontend build (`ResetPassword.jsx` change) | Succeeds, no new warnings |
| Mobile: `npm run typecheck` | Clean |
| Mobile: `npm run lint` | Clean |
| Mobile: `npm run format:check` | Clean |
| Mobile: `npm test` | **11 passed** — API client (5), SecureStore (3, Sprint 0), Login screen (3) |
| Mobile: `npx expo-doctor` | 20/20 |
| Mobile: iOS + Android `expo export` | Both succeed |
| Secret scan (backend + mobile, every changed/new file) | Clean |
| `apps/api` touched | **No** — confirmed via `git status`, remains reserved |

### ADR-028 Decision 8 coverage, per endpoint

Every endpoint has feature, authorization (where auth applies),
IDOR (where a target identity exists), and contract tests:

- **register**: feature (happy path, duplicate email, password mismatch), contract.
- **login**: feature (happy path, wrong password, no-enumeration), throttling, contract.
- **logout**: feature, authorization (missing/invalid token), IDOR (revokes only the calling token, proven against a second token for the same user and a different user's token).
- **forgot-password**: feature (existing + unknown email, identical response), validation, contract.
- **reset-password**: feature (happy path, invalid token, mismatch), contract.
- **email/verification-notification**: feature (sends, already-verified no-op), authorization, throttling, IDOR (never emails a different user).
- **me**: feature, authorization, IDOR (two tokens, two distinct identities), contract.
- **the interstitial itself**: 12 dedicated tests covering every one of your explicit requirements (unchanged web flow ×2, valid mobile flow ×2, wrong-purpose/tampered rejection ×2, expiry-still-enforced ×2, open-redirect impossibility, secret non-exposure in body and in logs).

### One test-harness artifact worth knowing about, not a product bug

Sanctum's request guard memoizes the resolved user on itself, and that
guard instance persists across multiple simulated requests *within one
test method* (real HTTP requests never share this — each is a fresh
process). Three IDOR-style tests that authenticate as different
identities in sequence needed an explicit `$this->app['auth']->forgetGuards()`
between calls to avoid reusing a stale cached identity. Documented inline
in each test; no application code involved.

## What's held uncommitted

Everything above, plus this report, `docs/mobile/sprint-1-architecture.md`,
and the earlier `docs/decisions/028-*.md`/`docs/mobile/architecture-review.md`/
`docs/mobile/sprint-0-validation-report.md` (already committed at `82dac73`).
`estructura.txt` remains untracked and excluded. Awaiting your review before
any commit.
