# Mobile Architecture Review

Status: **draft, held uncommitted, awaiting approval.** No implementation
code has been written. No web/backend file has been modified. This
review is the first of three required steps (architecture → decisions →
ADR-028) before any mobile implementation begins.

Baseline: RowBuddy Web RC1, commit `6b06a62`, tag `v1.0.0-rc1`.

---

## 1. Current backend readiness for mobile

The backend is more mobile-ready than a first glance suggests: a
meaningful subset of the domain-critical HTTP surface is **already**
plain JSON, not Inertia-coupled, because Phase 9 (ADR-027) deliberately
built several endpoints as public/JSON APIs rather than page responses.

**Already mobile-consumable as-is (no backend change needed):**

| Endpoint | Notes |
|---|---|
| `GET /queues/discover` | Public, unauthenticated JSON (ADR-007). |
| `GET /auctions/{auctionId}` | Public JSON, explicit allowlisted DTO — no seller/bidder identity, no internal IDs. |
| `POST /auctions/{auctionId}/bids` | JSON, `auth`+`verified`, requires an `Idempotency-Key` header, `bidderId` derived server-side only. |
| `POST /buyer-payment-methods/setup-intent`, `POST /buyer-payment-methods` | JSON, `auth`+`verified`, exposes only `{client_secret, publishable_key}` and `{saved, saved_at}` — never a Stripe customer/payment-method ID. |
| `GET /transfers/{transferId}` | JSON, participant-only (403 for non-participants), never exposes the counterpart's identity beyond confirmation booleans. |
| `GET /transfers/{transferId}/qr-token` | JSON, buyer-only. |
| `POST /transfers/{transferId}/confirm-as-seller`, `confirm-as-buyer` | JSON, throttled, both derive the acting user id from auth only. |
| Presence session endpoints (start/continue/evidence) | JSON, `auth`, `verified` deliberately **not** applied to continuation (Sprint 5 security-review rationale: don't mutate an existing obligation based on unverified status). |

**Real-time**: the auction channel (`auctions.{auctionId}`, driven by
`AuctionSnapshotBroadcast`) is a **public** Reverb channel — no
authorization required, and its payload (`id, status, current_price,
minimum_next_amount, closes_at, bid_count`) has zero bidder/winner
identity on any transition, including `AuctionWon`. Mobile can subscribe
to this exact channel with zero backend change.

**Not mobile-ready, and why:**

- **All authentication is session+cookie+CSRF via Fortify.** No Sanctum,
  no token guard, no `routes/api.php`, no CORS config anywhere in the
  repo. A native client cannot authenticate against anything that exists
  today.
- **Registration, login, logout, password reset, email verification,
  dashboard** are Inertia-only closures in `routes/web.php` — full-page
  responses, no JSON contract.
- **Queue submission** (`POST /queues`) uses Inertia's redirect-based
  form pattern (`back()->with(...)` / `back()->withErrors(...)`), not
  JSON — a native client has no way to interpret a 302 the way Inertia's
  own client-side runtime does.
- **No broadcasting-auth endpoint exists at all** (`Broadcast::routes()`
  / `withBroadcasting()` was never registered). The one private channel
  defined in `channels.php` (`App.Models.User.{id}`) is dead code —
  unreachable by anything, web or mobile, today.
- **Ratings and Disputes-filing have zero HTTP surface.** Both have
  complete, tested domain services (`RatingSubmissionService`,
  `RatingRevealEvaluator`, `DisputeFilingService`) with no controller or
  route anywhere calling them from a user-facing action.
- **No in-app notification inbox** exists — Notifications (ADR-025) is
  email-only, with no list/read endpoint and no push infrastructure of
  any kind (no device-token concept, no provider integration, not even a
  stubbed interface).
- **`users.language`/`country_code`/`currency`/`timezone` are read-only
  in practice.** `EloquentRecipientLocalePreferenceLookup` already reads
  `users.language` to pick an email's rendering locale (ADR-025 §9/§10)
  — but nothing in the entire codebase ever *writes* these columns. No
  registration field, profile endpoint, or admin tool populates them.

## 2. Missing mobile API surfaces

Net-new backend work required, each explicitly a thin controller over an
**already-existing** domain service — no new business logic, no changed
bounded-context invariants:

1. **Mobile authentication surface** (Sanctum-based — see §5): register,
   login, logout, forgot-password, reset-password, resend-verification,
   `/api/v1/me`.
2. **Queue submission, JSON variant** — same `QueueSubmissionService`,
   a response shape a native client can parse instead of a redirect.
3. **Ratings HTTP surface** — submit + view, wrapping
   `RatingSubmissionService` / `RatingRevealEvaluator` as-is.
4. **Disputes-filing HTTP surface** — buyer files, buyer views own
   filing status, wrapping `DisputeFilingService` as-is. Resolution
   stays admin-only, unaffected.
5. **Profile/locale-preference write endpoint** — first-ever writer of
   `users.language`/`country_code`/`currency`/`timezone`.
6. **Account-standing self-visibility** — the `AccountStandingLookup`
   port (ADR-026) already exists for enforcement; nothing today lets the
   account owner see their own standing via HTTP.
7. **Device/push-token registration** — only if push is approved (§12,
   decision 7); genuinely new domain concept, doesn't exist in any form.
8. **Broadcasting-auth endpoint** — only needed if/when a *private*
   channel is introduced (e.g., a future personal real-time channel);
   not needed for the public auction channel, which needs nothing.

One item needs verification, not assumption: I could not confirm from
the research whether a single-queue **detail** JSON endpoint (distinct
from the discovery list) already exists. Verify this directly before
ADR-028 scoping rather than assume either way.

Deliberately **not** proposed as new surface, and why:

- **A composed Auction→Payment→Transfer "transaction status" endpoint.**
  ADR-014 explicitly left this uncomposed (Payments' lifecycle is
  independent of `Auction`'s own status by design). Mobile can compose
  2-3 existing calls client-side for MVP; building an aggregate endpoint
  now would be new read-model work, not just routing — defer until
  real UX friction proves it's needed.
- **An in-app notification inbox.** Not required by ADR-025's own
  documented scope; recommend relying on push (if approved) + existing
  email for MVP rather than standing up a third notification surface.

## 3. React Native/Expo vs. Flutter

Both are credible; this is not a case where one is objectively wrong for
this app's technical demands (forms, lists, a live countdown, one
websocket feed, camera/GPS capture — nothing performance- or
animation-heavy that would make Flutter's rendering advantage decisive).

**React Native + Expo + TypeScript**

- Direct skill transfer from the existing web stack: same component/hook
  mental model as the React+Inertia web app, same language family as the
  backend's own TS-adjacent domain-DTO conventions.
- Every native capability this MVP needs has mature, first-party or
  well-maintained Expo tooling: `@stripe/stripe-react-native` (official,
  Expo config plugin available, maps directly onto the SetupIntent flow
  already built for web), `pusher-js`/`laravel-echo` (works in RN,
  speaks Reverb's Pusher-compatible protocol — zero backend change to
  consume the existing public auction channel), `expo-camera` (QR
  scanning), `expo-location` (foreground + background), `expo-secure-store`,
  `expo-notifications` (Expo's push service brokers both APNs and FCM).
- EAS Build/Submit gives one managed pipeline for both platforms' store
  submissions — valuable without deep native-tooling investment.
- Risk, shared with Flutter (not a differentiator): iOS aggressively
  throttles/suspends background location — ADR-011's 15-minute
  live-proximity staleness window is genuinely at risk if the seller's
  app is backgrounded during an active auction. This needs a concrete
  mitigation (local reminder notifications, a documented "keep the app
  foregrounded during an active auction" constraint, or a tolerant
  staleness policy) regardless of which framework is chosen.
- Risk specific to Expo (low for this app): the managed workflow can lag
  on very-latest native features not covered by a first-party module or
  mature community config plugin. Every capability RowBuddy's MVP
  actually needs is already well-covered, so this risk is low here, but
  it's the real category where Expo — not React Native itself — could
  fall short.

**Flutter**

- Genuinely stronger, more consistent rendering (compiles to native, no
  JS bridge) and one language (Dart) end-to-end.
- Official Stripe Flutter SDK, mature `geolocator`/`camera` plugins,
  Pusher-protocol Dart clients exist for Reverb compatibility.
- Real cost for RowBuddy specifically: **zero code, type, or tooling
  sharing with the existing web app.** No shared DTO types, no shared
  i18next-family tooling (Flutter would use `intl`/`easy_localization`
  instead — the translation *content* ports, but none of the tooling,
  including the backend's own `TranslationParityTest` pattern), and a
  second language (Dart) with no overlap with anyone already working on
  this codebase.

**Recommendation: React Native + Expo + TypeScript.** Not by default —
Flutter is technically sound — but because the deciding factor here is
team/stack continuity with an already-large, already-React-shaped
codebase, not raw rendering performance the app doesn't need. This is a
recommendation, not a frozen decision (see §12).

## 4. Recommended mobile architecture

- **React Native, Expo (managed workflow via EAS), TypeScript.**
- **Expo Router** for file-based navigation, mapping cleanly onto the
  screen map in §7.
- **TanStack Query** for server state — GET caching with explicit
  staleness display, mutation retry disabled by default and enabled only
  where the backend contract already proves idempotency (bid placement's
  `Idempotency-Key`; see §9).
- **A hand-written TypeScript API client** mirroring today's actual DTO
  shapes (no OpenAPI spec exists on the backend to generate from) — a
  decision to freeze (§12) if the team later wants to invest in
  generating types from the Laravel side instead.
- **`expo-secure-store`** for the Sanctum token — never `AsyncStorage`
  for anything credential-shaped.
- **`@stripe/stripe-react-native`** with the Expo config plugin, against
  the existing SetupIntent two-step contract — no new payment logic.
- **A Pusher-protocol-compatible client** (`pusher-js` directly, or
  `laravel-echo` + `pusher-js`) subscribing to the existing public
  `auctions.{id}` channel — zero backend change for this specific
  channel.
- **i18next + `react-i18next`**, same library family as web for tooling
  continuity (not literal code sharing — RN and web bundle separately,
  but the en/es JSON namespace files are directly portable content), with
  `expo-localization` for initial device-locale detection plus a real
  user-facing in-app override — mobile is positioned to actually solve
  locale switching for itself (see §12, decision 3), independent of
  whether/when web's own FG-001 gap is ever closed.

## 5. Authentication design

**Laravel Sanctum, personal access tokens — not Sanctum's SPA cookie
mode** (that mode is for first-party JS apps sharing a domain with the
API; wrong model for a native client).

- Fortify's web session flows are **untouched**. A new, parallel
  `routes/api.php` (doesn't exist today — must be created) exposes
  mobile-specific auth endpoints that reuse the exact same underlying
  Fortify Action classes already in `app/Actions/Fortify/`
  (`CreateNewUser`, `ResetUserPassword`, etc.) — only the HTTP response
  contract differs (JSON + token, not redirect + session cookie).
- Concretely: `POST /api/v1/auth/register`, `POST /api/v1/auth/login`
  (reusing Fortify's own login-throttle policy, not a separate one),
  `POST /api/v1/auth/logout` (revokes only the calling token via
  `$request->user()->currentAccessToken()->delete()`),
  `POST /api/v1/auth/forgot-password`, `POST /api/v1/auth/reset-password`,
  `POST /api/v1/auth/email/verification-notification` (resend).
- **Email verification and password-reset links continue to open the
  existing web route** (clicked from an email client, which may not have
  the app installed) — with a "return to the app" deep-link interstitial,
  rather than trying to make a raw email link open a mobile app directly
  via a custom URL scheme before the app may even be installed.
- Tokens are created per-device (`$user->createToken($deviceName)`), so a
  future "manage devices" screen can list and individually revoke
  sessions — never allow revoking another user's token; enforce
  ownership server-side, not just in the UI.
- `verified` middleware works unchanged once a request resolves through
  Sanctum's guard instead of the session guard — no change to any
  existing `verified`-gated authorization logic.
- **CSRF is entirely irrelevant to mobile.** Token-guarded requests never
  touch the session/CSRF mechanism at all — this directly satisfies "must
  not rely on browser-session assumptions, CSRF cookies, or Inertia page
  responses."
- Footnote, not a blocker: Fortify's config has 2FA and passkeys
  *enabled*, with DB tables scaffolded — but **zero frontend code
  anywhere references either feature**. No real user can currently
  enable them, so mobile auth has nothing to account for here today; flag
  for re-review only if 2FA/passkeys are ever actually built out on web.

## 6. API and real-time design

- New `routes/api.php`, prefixed and versioned `/api/v1/...` from day
  one (a native client can't be force-refreshed the way a web page can;
  retrofitting a version segment later is painful — freeze this now).
- Every already-JSON endpoint (§1's table) is reused verbatim behind
  `/api/v1`, gated by `auth:sanctum` instead of the web session guard —
  routing-layer duplication only, zero domain-logic duplication.
- Real-time behavior:
  - **Initial state**: always `GET /api/v1/auctions/{id}` for the
    authoritative snapshot *before* subscribing to the socket — never
    treat a cached/local value as current price.
  - **Then** subscribe to the existing public `auctions.{id}` channel,
    listening for `snapshot.updated` — identical event/payload web
    already uses.
  - **Reconnection**: rely on the client library's built-in transport
    reconnection, but on every reconnect (or `AppState` transition to
    `active`) re-fetch the REST snapshot once — the socket is a
    low-latency layer on top of a periodically-reconciled source of
    truth, never the sole source of correctness.
  - **Background/foreground**: no attempt to keep a live bid feed alive
    while backgrounded (OS-suspended anyway); resync via REST the moment
    the app returns to foreground.
  - **No bidder identity exposure**: already guaranteed server-side by
    the existing allowlisted broadcast payload — inherited for free, not
    something a mobile client could get wrong even if it tried.

## 7. Screen map

| Screen | Backend dependency | Readiness |
|---|---|---|
| Splash/startup | New `GET /api/v1/me` (trivial — current user + verification + standing) | Needs new API |
| Registration | New mobile auth surface (§5) | Needs new API |
| Email verification | New resend endpoint + existing web verification link + deep-link interstitial | Needs new API + a deep-link decision |
| Login | New mobile auth surface (§5) | Needs new API |
| Password recovery | New mobile auth surface (§5) | Needs new API |
| Home/discovery | `GET /queues/discover` | **Ready today** |
| Queue-position listing detail | Unconfirmed — verify a single-queue JSON detail route exists | **Needs verification** |
| Auction detail | `GET /auctions/{id}` | **Ready today** |
| Live bidding | `GET /auctions/{id}` + `POST .../bids` + `auctions.{id}` channel | **Ready today** |
| Payment-method setup | Existing SetupIntent endpoints + RN Stripe SDK | **Ready today** (client-side integration only) |
| Transfer detail | `GET /transfers/{id}` | **Ready today** |
| Confirmation code/QR flow | Existing QR-token + confirm-as-buyer/seller endpoints | **Ready today** (client renders/scans; zero backend change per research) |
| Notifications (in-app) | No list/inbox endpoint exists | **Not ready** — product decision needed (§12) |
| Ratings | No HTTP surface exists | **Not ready** — new API needed (§2) |
| Profile | Fortify's `updateProfileInformation` (name/email only today) + new locale-write fields | **Partially ready**, needs new API for locale fields |
| Account status | No self-visibility endpoint exists | **Needs new, small API** |
| Language selection | Entirely mobile-side, optionally paired with writing `users.language` | **New, mobile-only** |
| Logout | New `POST /api/v1/auth/logout` | Needs new API |

## 8. Security model

- **Secure token storage**: `expo-secure-store` (Keychain/Keystore-backed)
  only, never `AsyncStorage`.
- **Token revocation**: individually revocable Sanctum tokens, per
  device; logout revokes only the current token; ownership enforced
  server-side for any future device-management screen.
- **Biometric access**: explicitly deferred, out of MVP. If ever added,
  it gates *unlocking the on-device stored token*, not a server-side
  auth factor — must never be confused with real authentication.
- **Certificate/network security**: HTTPS-only. Certificate pinning is a
  decision to freeze, not commit to now — pinning adds real operational
  cost (rotation must be coupled to app releases); recommend standard TLS
  validation for MVP, revisit once at least one real app-update cycle has
  shipped.
- **Deep-link verification**: platform-verified Associated
  Domains/App Links (not a raw custom URL scheme, which can be squatted)
  once a production domain is controlled; a custom scheme is acceptable
  only during pre-domain development.
- **Stripe client-secret handling**: unchanged from web's existing
  pattern — scoped to one SetupIntent, consumed once, never logged,
  never persisted client-side beyond the immediate confirmation call.
- **QR replay protection**: the existing mechanism is deliberately
  **not** single-use (re-readable within the transfer window); "replay
  protection" is really the domain state machine rejecting a second
  confirmation attempt. This is a pre-existing, accepted trust model —
  mobile inherits it unchanged, doesn't worsen it.
- **Screenshot/log data exposure**: never log the Stripe client secret,
  the Sanctum token, or the QR token value anywhere client-side.
  Screen-capture protection (Android `FLAG_SECURE` equivalent) on the QR
  display screen specifically is a low-cost decision to freeze in favor
  of adopting.
- **Device permissions**: request contextually, at point of use (camera
  at scan time, location at presence/confirmation time) — both better UX
  and required by both stores' review guidelines.
- **Push-notification privacy**: any future push payload must meet the
  same content bar ADR-025 already imposes on email (no raw provider
  identifiers, no internal IDs, no un-allowlisted participant identity) —
  extending an existing precedent, not inventing a new one.
- **Rate limiting**: existing route-level throttles
  (`throttle:bid-placement`, `throttle:transfer-confirmation`, Fortify's
  login throttle) are guard-agnostic and apply unchanged; the new mobile
  auth endpoints should reuse Fortify's existing throttle policy rather
  than invent a separate one.
- **IDOR resistance**: every reviewed mobile-relevant endpoint already
  derives the acting user id exclusively from the auth guard, never a
  client-supplied identifier. This exact discipline must carry into every
  *new* endpoint (ratings, disputes-filing, profile) without exception —
  a non-negotiable carry-forward, not a per-endpoint judgment call.
- **Offline behavior**: see §9.

## 9. Offline/retry strategy

Conservative by design, per instruction:

- **Read caching where safe**: TanStack Query caches discovery/auction/
  transfer GETs with a short staleness window, always labeled "last
  updated Xs/m ago" — never presented as live truth for a decision that
  commits money or a handoff.
- **No offline bids**: the bid mutation is disabled outright (not
  queued) when connectivity is down — a queued bid fired after
  reconnection could act on a stale price/closing-time and would only add
  confusion on top of a domain rejection that's already correct.
- **No offline transfer confirmations**: a confirmation is a
  point-in-time GPS+QR claim; queuing it for later would submit a
  location reading from the wrong moment.
- **No offline payment operations**: the Stripe SDK itself requires
  connectivity; just surface a clear offline error.
- **Retry only for explicitly idempotent operations**: bid placement
  already has a server-verified `Idempotency-Key` contract (`409` is
  documented as "safe to retry with the same key") — safe to auto-retry.
  Buyer-payment-method-setup completion and transfer confirmation have
  **no documented idempotency-key contract** in what this research
  confirmed — treat as manual-retry-only until their idempotency is
  explicitly verified during ADR-028 scoping, not assumed.

## 10. Testing and distribution strategy

- **Unit tests**: Jest for pure logic (formatters, DTO parsing); mirror
  the backend's own `TranslationParityTest` pattern for the mobile
  en/es namespaces.
- **Component tests**: React Native Testing Library for screen-level
  form/loading/error-state behavior without a full simulator.
- **API-contract tests**: no OpenAPI spec exists to generate from yet —
  start with fixture-based tests asserting today's actual DTO shapes,
  run against a real test-mode backend in CI (not mocks alone) so backend
  drift breaks CI on both sides.
- **Authentication tests**: token storage/retrieval, expired/revoked-token
  handling (a 401 must always clear the local token and route to Login,
  never loop), full register→verify→login flow against a test-mode
  backend.
- **Real-time tests**: exercised against the existing docker-compose
  Reverb service, asserting reconnection + stale-state re-fetch, not just
  "a message arrives."
- **Stripe test-mode tests**: blocked today by the same **already
  documented** external blocker as web — real Stripe test-mode
  credentials are not yet configured in this environment. Mobile
  inherits this blocker, doesn't introduce a new one.
- **Android device testing**: a physical-device + emulator matrix,
  including at least one mid-range/older device for battery/background-
  location sanity given the §3 background-location risk.
- **iPhone testing**: physical device required, not just simulator —
  simulators cannot fully exercise real GPS/camera/push for the
  presence/evidence and transfer-confirmation flows.
- **Expo development builds**: use `expo-dev-client`, not Expo Go —
  Stripe's native SDK isn't supported in Expo Go.
- **APK/AAB generation**: `eas build --platform android`.
- **TestFlight**: `eas submit --platform ios` after an `eas build`.
- **Google Play internal testing**: same EAS pipeline targeting the Play
  Console internal track before any wider rollout.

## 11. External prerequisites

- Apple Developer Program account.
- Google Play Console developer account.
- Expo/EAS account.
- iOS bundle ID + Android package name, decided and reserved before the
  first build.
- Signing credentials: iOS distribution cert/provisioning profile,
  Android upload keystore (EAS-managed vs self-managed — §12 decision).
- App icons and splash assets sized per each store's spec (not yet
  confirmed to exist for mobile specifically).
- A public, reachable privacy-policy URL — confirm it explicitly covers
  mobile-specific data collection (location, camera, push tokens); the
  existing legal-review track (`docs/legal/us-launch-review.md`) should
  be checked against this before store submission.
- A reachable support URL.
- Real Stripe test-mode credentials — same existing blocker as web, not
  a new one.
- Push-notification credentials (APNs key/cert, FCM project) — only if
  push is approved (§12, decision 7).
- Production domain control — needed for verified deep links (§8).

## 12. Product and architectural decisions that must be frozen

1. Mobile auth architecture: Sanctum personal-access-tokens via a
   parallel `routes/api.php` reusing Fortify's Actions (recommended) vs.
   multi-guard shared routes.
2. API versioning: `/api/v1/...` from day one (recommended: freeze now).
3. Does mobile implement real locale switching (device-default + in-app
   override) for itself now, and become the first-ever writer of
   `users.language`/`country_code`/`currency`/`timezone` (recommended:
   yes) — explicitly independent of web's still-deferred FG-001.
4. Confirm whether a single-queue detail JSON endpoint already exists
   (verify, don't assume).
5. Build a composed Auction→Payment→Transfer status endpoint now, or
   compose client-side for MVP (recommended: compose client-side, defer).
6. Build an in-app notification inbox, or rely on push (if approved) +
   email for MVP (recommended: rely on push+email, defer the inbox).
7. **Approve or exclude push notifications for MVP** — new backend
   infrastructure (device-token registration + Expo push service +
   platform credentials) is required either way; this must be an
   explicit yes/no, not an assumption. If yes: which of the 8 existing
   notification events also fire as push.
8. TLS certificate pinning: adopt now or defer until after the first real
   app-update cycle (recommended: defer).
9. Deep-link mechanism: verified universal/app links (needs a production
   domain) vs. a custom scheme for interim development (recommended:
   custom scheme now, verified links required before real release).
10. QR-screen screenshot protection: adopt (recommended, low cost) or
    skip.
11. Expo managed workflow (recommended) vs. bare workflow — freeze
    explicitly.
12. Android keystore management: EAS-managed (recommended to start) vs.
    self-managed.
13. API client typing: hand-written TS types mirroring today's DTOs
    (recommended for MVP speed) vs. investing in a generated OpenAPI spec
    from the Laravel side first.
14. Biometric local-unlock: confirmed out of MVP scope — freeze as
    explicitly deferred, not left ambiguous.
15. Retry policy for setup-completion/transfer-confirmation mutations:
    confirm actual idempotency during ADR-028 scoping before deciding
    auto-retry vs. manual-retry-only (recommended interim default:
    manual-retry-only).

## 13. Proposed ADR-028 scope

ADR-028 should formally ratify:

- Introduction of Sanctum for mobile — the personal-access-token model,
  the new `routes/api.php`, and the `/api/v1` versioned prefix. This is a
  real backend/architecture change (the first-ever token-auth surface in
  a purely session-based app) and belongs in an ADR, not just a
  mobile-side note.
- The specific set of net-new backend endpoints (§2) and the explicit
  constraint that every one is a thin controller over an already-existing
  domain service — zero new business logic, zero changed bounded-context
  invariants, matching this review's own non-duplication requirement.
- The push-notification decision (§12, decision 7) and, if approved,
  which package owns the device-token domain concept.
- Explicit confirmation that Administration, Fraud & Risk, payout
  execution, and every already-deferred web enhancement (FG-001 through
  FG-005) remain out of the mobile MVP — and an explicit note that mobile
  independently solving locale-switching for itself (§12, decision 3) is
  **not** the same as retrofitting it into the web app, so the two are
  never conflated later.
- The mobile technology stack (§3/§4) as a frozen architectural choice,
  since it determines what a "contract test" means and what CI needs to
  run.
- The frozen MVP screen map (§7), explicitly excluding Administration —
  giving a later mobile-phase completion report the same kind of clear,
  citable scope boundary every prior numbered phase has had.

## 14. Proposed sprint breakdown

Proposed only — not yet approved, and sequenced so the longest-lead-time
external prerequisites (§11) start immediately in parallel with backend
work rather than blocking it later.

- **Sprint 0 — Foundations & external setup.** Expo scaffold (managed
  workflow, TypeScript, Expo Router), EAS account + app identifiers +
  signing setup, Apple/Google developer accounts kicked off, CI skeleton,
  ported en/es i18n namespaces.
- **Sprint 1 — Mobile auth backend + screens.** Sanctum install,
  `routes/api.php`, all auth endpoints (§5), `/api/v1/me`; splash, login,
  registration, password recovery, email-verification screens.
- **Sprint 2 — Discovery, auction detail, live bidding.** Consume
  already-existing endpoints and the Reverb channel as-is; implement the
  reconnection/stale-state-recovery design (§6).
- **Sprint 3 — Payments + transfer/handoff + QR.** RN Stripe SDK
  integration; transfer-detail, QR display/scan, confirm-as-buyer/seller
  screens with GPS capture.
- **Sprint 4 — Ratings + disputes-filing.** New thin backend endpoints
  wrapping existing domain services; corresponding screens.
- **Sprint 5 — Profile, account status, language selection, logout.**
  Including the first-ever write path for `users.language` etc.
- **Sprint 6 — Security hardening + push decision execution (if
  approved).** SecureStore audit, deep-link verification (if the
  production domain is ready), device-token registration + Expo push
  integration, an IDOR/rate-limit review pass mirroring Phase 9 Sprint 5's
  own precedent.
- **Sprint 7 — Testing/distribution readiness.** Full test matrix,
  device testing on both platforms, first EAS internal builds,
  TestFlight + Play internal-testing setup, store-listing asset/policy
  confirmation.

---

**Process reminder**: architecture (this document) → decisions (§12,
pending your review) → ADR-028 (§13, drafted only after decisions are
made) → implementation only after ADR-028 is approved. No web/backend
change, no `main` merge, no mobile code has been written.
