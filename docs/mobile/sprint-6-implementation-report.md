# Mobile Sprint 6 — Implementation Report

Status: held uncommitted, pending review. Scope — account-standing
self-visibility, a SecureStore/session-cache audit, and an IDOR/rate-
limit review pass mirroring Phase 9 Sprint 5's own precedent — is
complete and fully validated. Auction/transfer history and general
"activity views" (originally scope items 1–3) were dropped per your
explicit decision: zero grounding in ADR-028 or the architecture
review, and no such capability exists anywhere in the codebase (web or
mobile) today.

## 1. What was implemented

### Backend (`apps/web`) — one new route, three new rate limiters, zero new domain logic

- `GET /api/v1/account-standing` (ADR-028 §3) — the first HTTP surface,
  web or mobile, exposing a user's own suspended/active status. Injects
  Administration's own canonical `AccountStandingRepository` directly
  (apps/web is already the composition root allowed to depend on it,
  matching the existing `Bids/Queues/RatingsAccountStandingLookup`
  adapters) — no new port, no new domain logic.
  `tests/Feature/ShowAccountStandingApiTest.php` — 4 tests (feature/
  contract, authorization, and an IDOR-shaped test proving two
  different users' tokens each see only their own standing, since
  there's no id-accepting parameter to probe in the first place).
- **IDOR/rate-limit review pass** (`docs/security/mobile-security-checklist.md`)
  — a full pass over every one of the 23 mobile-facing endpoints added
  across Sprints 1–6, mirroring `docs/security/security-checklist.md`'s
  (Phase 9 Sprint 5) exact format and methodology. Found and fixed four
  real gaps:
  - **`throttle:profile-update`** (the one finding with real cross-user
    impact): `PUT /api/v1/profile` had no rate limit at all, on web or
    mobile, and an email change unconditionally re-sends the
    verification notification to whatever address is supplied — not
    only to addresses that already belong to a RowBuddy account. An
    authenticated attacker could repeatedly change their own profile's
    email to a real third party's address, causing RowBuddy to
    email-bomb that victim with verification emails. Fixed on the
    mobile route this sprint owns.
  - **`throttle:rating-submission`, `throttle:dispute-filing`,
    `throttle:device-registration`** — consistency fixes, not
    responses to a demonstrated exploit: bid-placement and transfer-
    confirmation already had rate limiters from the sprints that
    introduced them; these three newer endpoints didn't, for no
    principled reason.
  - Also reviewed and explicitly **not** fixed, since the identical gap
    is pre-existing and app-wide (Fortify's own default routes carry
    it too): `register`, `forgot-password`, `reset-password`, both
    buyer-payment-method routes, and queue submission all lack rate
    limiting on web as well as mobile — out of "mobile Sprint 6" scope,
    matching the exact posture Sprint 5 took toward the
    `SubmitterAccountSuspended` finding.

### Mobile (`apps/mobile`)

- **Account-standing consumed, not just built**: `app/profile.tsx` now
  shows the account owner's own status (active, or a visible warning
  when suspended) — otherwise the new endpoint would exist with no
  client ever calling it.
- **SecureStore audit**: confirmed zero `AsyncStorage` usage anywhere,
  zero `console.log/warn/error` of any kind in production code, and
  `expo-secure-store` imported only inside its own dedicated wrapper
  (`src/lib/secureStore.ts`) — every consumer (auth token, push token)
  goes through it, never a direct import. **One real gap found and
  fixed**: `useLogout()` cleared the auth token and the push token, but
  never TanStack Query's own in-memory cache. On a shared device where
  a second account logs in without the app fully restarting, the
  previous user's cached profile/transfers/ratings could transiently
  resurface before a fresh fetch overwrote them. Fixed with
  `queryClient.clear()`, in the same `finally` block that already
  guarantees the token cleanups run even if the logout network request
  itself fails.

## 2. Validation results

| Check | Result |
|---|---|
| Backend Pest suite | **353 passed** (348 prior + 5 new) |
| PHPStan | No errors |
| Pint | Clean (Sprint 6 files); same 12 pre-existing, unrelated style issues remain untouched |
| `composer audit` | Clean |
| Mobile: typecheck / lint / format | Clean |
| Mobile: `npm test` | **69 passed** (64 prior + 5 new) |
| Mobile: `npx expo-doctor` | 19/20 — same pre-existing, unrelated patch-version drift as prior sprints |
| Mobile: `npm audit` | Same 10 pre-existing moderate advisories, unchanged; zero new (no new mobile dependencies this sprint) |
| iOS + Android `expo export` | Both succeed |
| Secret scan | Clean |
| EAS Android dev build | **Not needed this sprint** — no new native dependency |

### One real Jest tooling gotcha, not a code bug

Writing `useLogout`'s own unit test surfaced a genuine open-handle
issue: the test itself passed in ~80ms, but Jest never exited
afterward, making every `npx jest ... | tail` invocation appear to hang
indefinitely (the pipe was correctly waiting for EOF that never came).
Root cause and fix were identical to the already-documented
`usePlaceBid.test.tsx` gotcha from Sprint 2 — a `QueryClient` needs
`gcTime: 0` in tests, or its internal garbage-collection timer keeps
the process alive.

## 3. Deviations from the approved scope

- **Auction/transfer history and activity views dropped**, per your
  explicit decision (see header) — no ADR-028 grounding exists;
  documented as an open gap for a future ADR if you want this built.
- **Web's own identical gaps left untouched**: the profile-update
  throttle was added only to the mobile route (Fortify's own web
  `PUT user/profile-information` route carries the same gap); the
  register/forgot-password/reset-password/buyer-payment-method/queue-
  submission rate-limiting gaps found during the review pass are all
  pre-existing and app-wide, not fixed this sprint — see
  `docs/security/mobile-security-checklist.md`'s own "reviewed,
  not fixed" section for the complete list and reasoning.

## 4. Remaining risks

- **The four new/changed rate limiters have not been exercised against
  real concurrent traffic** — validated by a request-loop-then-429
  test per limiter (matching this codebase's own established pattern
  for testing throttles), not a live load test.
- **The account-standing display has not been visually confirmed
  against a real suspended account on a physical device** — validated
  by mocked component tests and backend feature tests only.
- **Four rate-limiting gaps remain across the whole application** (web
  and mobile alike) — recorded, not silently assumed resolved. If any
  of these becomes a real incident, `docs/security/mobile-security-checklist.md`
  already has the exact finding, reasoning, and fix pattern ready to
  reuse.

## 5. Project progress

- **Sprint 6 completion: 100%** — all approved, ADR-028-grounded scope
  implemented and validated; held uncommitted pending your review.
- **Mobile application overall: ~78%** — the remaining distinct item
  from the original 8-sprint plan is Sprint 7 (testing/distribution
  readiness: device testing, TestFlight/Play internal-testing setup,
  store-listing assets).
- **Entire RowBuddy project: ~89%**, using the same Backend/Web=100%,
  Mobile=X% → overall=(100+X)/2 basis your own prior figures imply:
  (100+78)/2=89.
