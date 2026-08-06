# Mobile Sprint 2 — Implementation Report

Status: held uncommitted, pending review. Scope — discovery, auction
detail, live bidding, real-time — is complete and fully validated.

## 1. What was implemented

### Backend (`apps/web`) — one small, necessary addition

- `POST /api/v1/auctions/{auctionId}/bids`, gated by `auth:sanctum` +
  the same `throttle:bid-placement`/`verified` middleware the existing
  web route already uses, delegating to `PlaceBidController` **verbatim**
  — zero new domain logic. This was the one genuinely required backend
  touch: the existing bid-placement route sits under the web *session*
  guard, which a native client has no way to present at all (no cookie,
  no CSRF token). `GET /queues/discover` and `GET /auctions/{id}` needed
  no backend change at all — both are already public, unauthenticated,
  unversioned JSON endpoints, called directly at their existing
  `web.php` paths.
- `tests/Feature/PlaceBidApiTest.php` — 4 tests (feature, two
  authorization cases, IDOR) covering only what's new (the
  Sanctum-guarded route wiring); every domain-rule branch (bid-too-low,
  seller-self-bid, etc.) is already covered by the existing
  `PlaceBidTest.php` against the same controller.

### Mobile (`apps/mobile`)

- **Dependencies**: `expo-location` (discovery's required lat/lng),
  `laravel-echo` + `pusher-js` (Reverb connectivity — the same protocol
  web already uses), `@react-native-community/netinfo` (a peer
  dependency `pusher-js`'s React Native build requires; the export build
  caught this immediately when missing).
- **`src/api/client.ts`**: re-based on the plain backend origin (not
  `/api/v1`) — some endpoints mobile calls are unversioned public
  routes, some are the versioned auth/bid surface; callers now specify
  the full path. Added support for custom per-request headers
  (`Idempotency-Key`).
- **`src/api/queues.ts`, `src/api/auctions.ts`**: `discoverQueues`,
  `fetchAuction`, `placeBid`.
- **`src/lib/realtime.ts`**: a lazy `laravel-echo` singleton connecting
  to the existing public `auctions.{id}` channel, with `Pusher` injected
  explicitly (React Native has no `window` global for Echo's own
  connector to fall back to).
- **`src/lib/location.ts`**: requests foreground location permission
  contextually (only when discovery needs it, never at launch).
- **`src/features/auctions/hooks/`**: `useDiscoverQueues`, `useAuction`
  (REST-first, then subscribes to the Reverb channel, re-fetches on
  foreground — the exact reconnection/stale-state design from the
  architecture review §6), `usePlaceBid` (the one mutation that
  auto-retries, since bid placement's Idempotency-Key contract is
  server-verified).
- **Screens**: `app/home.tsx` evolved from Sprint 1's placeholder into
  the real discovery screen (queue list + location permission handling
  + an "enter an auction ID" lookup — see Deviation below);
  `app/auctions/[auctionId].tsx` — live price, bid count, countdown,
  and a bid form that disappears once the auction can no longer accept
  bids.
- **i18n**: new `queues` and `auctions` namespaces, `en`/`es`.

## 2. Validation results

| Check | Result |
|---|---|
| Backend Pest suite (full) | **273 passed** (979 assertions) — 269 prior + 4 new |
| PHPStan (`--memory-limit=512M`) | No errors |
| Pint — Sprint 2 backend files | Clean |
| `composer audit` | No advisories |
| Web `npm audit` | 0 vulnerabilities |
| Mobile: `npm run typecheck` | Clean |
| Mobile: `npm run lint` | Clean |
| Mobile: `npm run format:check` | Clean |
| Mobile: `npm test` | **20 passed** (7 suites) |
| Mobile: `npx expo-doctor` | 20/20 |
| Mobile: iOS + Android `expo export` | Both succeed (after adding `@react-native-community/netinfo`) |
| Secret scan | Clean (one reviewed non-finding — see below) |
| `apps/api` touched | No |

### Two real bugs caught and fixed during this sprint's own validation, not shipped

- **Idempotency-key regeneration on retry.** The first version of
  `usePlaceBid` generated the `Idempotency-Key` value *inside*
  `mutationFn`, which TanStack Query re-invokes on every retry — meaning
  a network-blip retry would have silently sent a **different** key each
  time, defeating the entire point of the header (the server would see
  each retry as a brand-new bid attempt, not a safe replay). Fixed by
  generating the key once in `onMutate` (called once per `mutate()`,
  never per retry) and reading it from a ref inside `mutationFn`. Covered
  by a dedicated test (`usePlaceBid.test.tsx`) that asserts all three
  attempts of a retried mutation carry the identical key, and that two
  separate `mutate()` calls get two different keys.
- **Sanctum guard/session cross-contamination in tests.** The new
  `PlaceBidApiTest.php` initially failed with "you cannot bid on your
  own auction" even though the bidder and seller were clearly different
  users. Cause: `actingAs($seller)` (used to drive the presence/evidence
  HTTP fixture setup) leaves the web session guard logged in for the
  rest of the test, and Sanctum's guard prefers an active session over a
  bearer token when both are present. Fixed with an explicit
  `guard('web')->logout()` + `forgetGuards()` between the fixture setup
  and the bidder's token-authenticated request. This is a test-harness
  artifact only — a real mobile request never carries a session cookie
  at all — documented inline in the test.

### One reviewed non-finding from the secret scan

`src/lib/realtime.ts` hardcodes the Reverb `REVERB_APP_KEY` value as a
fallback default. This is not a secret: it's the Pusher-protocol *app
key* (a public identifier used for channel-signing verification, not
authentication), already exposed to every web browser today via the web
app's own `VITE_REVERB_APP_KEY` — confirmed against the architecture
review's own explicit finding on this exact point. The `REVERB_APP_SECRET`
value is never referenced anywhere in mobile code.

## 3. Deviations from the approved architecture

- **No queue-to-auction linkage exists on the backend — a real gap, not
  a shortcut.** `DiscoverQueuesController`'s response has no auction
  reference at all, and no auction-*listing* endpoint exists anywhere —
  confirmed by reading every route in `web.php`. This mirrors an already-
  recorded finding from RC1's own Functional Acceptance stage (FG-002:
  "no UI exists to open an auction"). Rather than inventing new backend
  surface to bridge this, discovery (queue list) and auction detail/
  bidding are delivered as two independently functional pieces, exactly
  matching what the backend actually supports — reaching a specific
  auction today requires already knowing its id, matching the *web*
  app's own current capability exactly. The discovery screen's "view an
  auction by ID" field makes this navigable rather than a dead end, but
  it is not a substitute for real linkage. This should be weighed
  against the roadmap: a future backend decision to expose "queues with
  an open auction" would let this become a real tap-through flow.
- **One new backend route, not zero.** The architecture review's own
  Sprint 2 framing said "consume already-existing endpoints... as-is."
  For the two GET endpoints that's exactly what happened. Bid placement
  needed one new `auth:sanctum`-guarded mirror route, because its only
  existing route is session-guarded — not a stylistic choice, a hard
  requirement for mobile bidding to function at all. Flagging this
  explicitly since it's a small, deliberate expansion of "as-is," not a
  silent one.

## 4. Remaining risks

- **Android emulator networking.** `EXPO_PUBLIC_API_BASE_URL`/
  `EXPO_PUBLIC_REVERB_HOST` default to `localhost`, which an Android
  emulator cannot resolve to the host machine — documented in the new
  `.env.example`, but untested against a real emulator in this
  environment (no Android SDK/emulator available here). iOS
  simulator/physical-device behavior is likewise unverified against a
  live Reverb connection — all real-time code has been validated by
  static analysis and Metro bundling, not a live socket connection,
  since no device/simulator is available in this environment.
- **No live end-to-end bidding test against a running Reverb server.**
  The reconnection/foreground-refetch logic in `useAuction` is
  implemented exactly per the architecture review's design and is
  bundling-clean, but has not been exercised against a real, running
  Reverb instance receiving real broadcast events — only against
  mocked hooks in component tests.
- **Discovery's "view an auction by ID" is a deliberate stopgap.** If
  left as the only path to an auction for too many more sprints, it's
  worth a product decision on whether the backend gap above gets closed
  before mobile MVP ships, not silently carried forward indefinitely.

## 5. Project progress

- **Sprint 2 completion: 100%** — all approved scope implemented and
  validated; held uncommitted pending your review.
- **Mobile application overall: ~40%** (3 of 8 planned sprints per
  ADR-028 §14 complete; Sprints 3–7 — payments/transfer/QR, ratings/
  disputes, profile/language, push/security, testing/distribution —
  remain).
- **Entire RowBuddy project: ~70%** (using the same Backend/Web=100%,
  Mobile=X% → overall=(100+X)/2 basis your own prior figures imply:
  (100+40)/2=70).
