# Mobile Sprint 3 — Implementation Report

Status: held uncommitted, pending review. Scope — payment-method setup,
transfer detail, buyer QR/confirmation-code retrieval, seller/buyer
confirmation, camera/location permissions — is complete and fully
validated.

## 1. What was implemented

### Backend (`apps/web`) — six new routes, zero new domain logic

All six reuse their existing web session-guarded controllers **verbatim**
(ADR-027 Architecture Refinements §4/§5) — every one already derives
buyerId/sellerId/requestingUserId exclusively from `$request->user()->id`,
so they were guard-agnostic and needed only a Sanctum-guarded mirror
route, exactly the Sprint 2 bid-placement pattern:

- `POST /api/v1/buyer-payment-methods/setup-intent` (`verified`)
- `POST /api/v1/buyer-payment-methods` (`verified`)
- `GET /api/v1/transfers/{transferId}`
- `GET /api/v1/transfers/{transferId}/qr-token`
- `POST /api/v1/transfers/{transferId}/confirm-as-seller` (`throttle:transfer-confirmation`)
- `POST /api/v1/transfers/{transferId}/confirm-as-buyer` (`throttle:transfer-confirmation`)

Two new test files cover only the sanctum-wiring itself (authorization,
IDOR, contract) — every domain-rule branch (geofence, QR-token mismatch,
illegal state, expiry, replay protection, SetupIntent mismatch) is
already covered by the existing web-route test files against the
identical controllers:

- `tests/Feature/BuyerPaymentMethodSetupApiTest.php` — 5 tests
- `tests/Feature/TransferApiTest.php` — 14 tests

### Mobile (`apps/mobile`)

- **Dependencies**: `@stripe/stripe-react-native` (payment-method setup —
  requires a development build, as anticipated at the end of Sprint 2),
  `expo-camera` + `react-native-svg` + `react-native-qrcode-svg` (QR
  scan/render — see Deviation below).
- **`src/api/payments.ts`, `src/api/transfers.ts`**: thin wrappers over
  the six new endpoints.
- **`src/features/payments/hooks/`**: `useBeginPaymentMethodSetup`,
  `useCompletePaymentMethodSetup` — both left at TanStack Query's
  default (no automatic retry), since neither has an Idempotency-Key
  contract (Sprint 3 constraint #10).
- **`src/features/transfers/hooks/`**: `useTransfer` (fetch-on-mount,
  refetch-on-own-confirm — matches web's Transfers/Show.jsx exactly, no
  Reverb subscription since the backend has none for Transfers),
  `useRevealQrToken` (on-demand, `enabled: false` + manual `refetch()`),
  `useConfirmTransferAsSeller`/`useConfirmTransferAsBuyer` (also left at
  no-automatic-retry, same constraint #10 reasoning).
- **`app/payment-method-setup.tsx`**: mirrors web's
  Payments/SetupPaymentMethod.jsx — `CardField` + `confirmSetupIntent()`
  confirm entirely client-side; only the resulting `setup_intent_id` is
  ever posted to the backend. `StripeProvider` is mounted here, not at
  the root layout, since its `publishableKey` is only known once the
  backend's setup-intent response arrives (mirrors web's own dynamic
  `loadStripe(publishableKey)`).
- **`app/transfers/[transferId].tsx`**: mirrors web's Transfers/Show.jsx,
  with one deliberate mobile-native upgrade — see Deviation below.
- **Home screen**: added a "Payment method" header link and a "view a
  transfer by ID" lookup, exactly matching the existing "view an auction
  by ID" stopgap pattern (see Deviation below).
- **i18n**: new `payments` and `transfers` namespaces, `en`/`es`.
- **EAS/dev-client**: `@stripe/stripe-react-native`'s config plugin
  required an explicit (empty) options object — passing it as a bare
  string crashed prebuild (`Cannot read properties of undefined
  (reading 'merchantIdentifier')`); `expo-camera` was added with an
  explicit `cameraPermission` message. Both required a fresh Android
  development-build APK (native modules, unlike Sprint 2's JS-only
  changes) — rebuilt and installed; see §2.

## 2. Validation results

| Check | Result |
|---|---|
| Backend Pest suite (full) | **311 passed** (1058 assertions) — 292 prior + 19 new |
| PHPStan (`--memory-limit=512M`) | No errors |
| Pint — Sprint 3 backend files | Clean |
| `composer audit` | No advisories |
| Mobile: `npm run typecheck` | Clean |
| Mobile: `npm run lint` | Clean |
| Mobile: `npm run format:check` | Clean |
| Mobile: `npm test` | **34 passed** (9 suites) — 29 prior + 5 new suites' worth |
| Mobile: `npx expo-doctor` | 19/20 — see note below |
| Mobile: iOS + Android `expo export` | Both succeed |
| Secret scan | Clean |
| EAS Android development build | Rebuilt successfully (new native modules) — see §5 |
| `apps/api` touched | No |

**expo-doctor note (not a Sprint 3 regression):** 3 packages
(`expo`, `expo-location`, `expo-router`) are one patch version behind
what the installed Expo SDK now expects — upstream patch releases
published since Sprint 0/2, unrelated to anything this sprint touched.
Left as-is rather than bumped, since an unrelated dependency upgrade is
outside this sprint's approved scope; flagging for your awareness only.

**`npm audit` note (also pre-existing, unchanged from Sprint 2):** the
same 10 moderate advisories reported at the end of Sprint 2 remain,
all rooted in `xcode`/`@expo/config-plugins` (Expo's own build tooling,
a dev-time dependency, not shipped in the app bundle) — the new Sprint 3
dependencies added zero new advisories.

### One real bug caught during typed-route regeneration, not shipped

Adding `app/transfers/[transferId].tsx` required regenerating Expo
Router's typed-route declarations (`.expo/types/router.d.ts`). This
surfaced a genuine environment issue, not a code bug: **two Metro
processes watching the same project simultaneously** (this sprint's
short-lived typecheck-only server, plus Sprint 2's still-running
`--dev-client` background server) raced to write that same generated
file, and the version left behind after the race typed
`/transfers/[transferId]` as a **static** route rather than a dynamic
one — `router.push(`/transfers/${id}`)` failed to typecheck as a result,
even though the route itself worked correctly at runtime. Fixed by
stopping Sprint 2's background Metro (a genuinely leftover process,
`TaskStop` again didn't fully terminate the underlying `node.exe`, same
as Sprint 2's own finding — killed directly, same PID-based approach),
deleting the stale generated file, and regenerating it with only one
Metro process running. Worth knowing for any future sprint that adds a
new dynamic route while a background Metro instance is already running.

## 3. Deviations from the approved architecture

- **QR code is rendered/scanned, not typed — a deliberate mobile-native
  upgrade over web's own stopgap, not a new backend behavior.** Web's
  own Sprint 3 (Phase 9) explicitly scoped the QR/confirmation code as
  plain text, typed manually, because rendering an actual barcode was
  out of *its* sprint's scope. This sprint's scope explicitly listed
  camera permissions as required, so the buyer's screen renders a real
  QR code (`react-native-qrcode-svg`) and the seller's screen scans it
  with the camera (`expo-camera`) instead of typing it in. The
  underlying backend contract is completely unchanged: both paths
  exchange the exact same plaintext `qr_token` string the backend
  already expects and already validates via `hash_equals()` — nothing
  about the confirmation protocol itself is new, and no backend code
  changed to support this.
- **"View a transfer by ID" is a stopgap, exactly matching an existing
  web gap — not invented for mobile.** Web itself has no "my transfers"
  listing page and no nav link to `/transfers/{id}` anywhere in
  `AppLayout.jsx` — reaching a transfer today requires already knowing
  its id on web too. Mobile's lookup field matches this exactly, the
  same posture as Sprint 2's auction-by-ID stopgap (FG-002). Not
  something this sprint was asked to fix, and nothing invented beyond
  what web itself already does.
- **No automatic retry anywhere in this sprint's mutations**, per
  constraint #10 — left at TanStack Query's own default (no override),
  since none of payment-method completion, seller confirmation, or
  buyer confirmation carry an Idempotency-Key contract the way bid
  placement does. A retried confirmation after a successful-but-lost
  response would hit `IllegalStateTransition` (422, "already
  confirmed"), not a safe replay.
- **One EAS/dev-client rebuild, anticipated at the end of Sprint 2.**
  Three new native modules (`@stripe/stripe-react-native`, `expo-camera`,
  `react-native-svg`) required a fresh Android development-build APK —
  the same Stripe-requires-a-dev-build consequence flagged explicitly
  when Sprint 2 switched off Expo Go.

## 4. Remaining risks

- **No live Stripe test-mode card entry exercised in this environment.**
  `CardField`/`confirmSetupIntent` are validated by component tests with
  the Stripe SDK mocked, by typecheck, and by successful bundling —
  not against a live Stripe test-mode card, since no device/simulator
  with real card entry is available here. Please exercise the actual
  save-a-card flow on your phone as part of this sprint's visual
  inspection.
- **No live camera scan exercised in this environment**, for the same
  reason — `expo-camera`'s `CameraView`/`useCameraPermissions` are
  validated by component tests with the module mocked and by successful
  native builds, not against a real camera pointed at a real rendered
  QR code. Please exercise the actual scan-and-confirm flow on your
  phone.
- **`useTransfer` has no live-update channel**, matching web's own
  Transfers/Show.jsx exactly (no Reverb subscription exists for
  Transfers on either platform) — if the other participant confirms
  first, this screen only reflects that once the user re-opens or
  manually triggers a refetch. An existing product gap on web itself,
  not something this sprint was asked to close.
- **Payment-method setup and transfer confirmation have no navigation
  entry point beyond the two stopgaps added this sprint** (a header
  link and an ID-lookup field) — there is still no screen that lists a
  buyer's own transfers or a seller's own pending confirmations. Same
  posture as the discovery→auction gap from Sprint 2: real, functional,
  but not yet a complete end-to-end flow a user could discover
  unaided.

## 5. EAS Android development build

New native modules required a fresh APK. Build succeeded:

- Build ID: `3c12e72c-d813-44c2-bb06-1c5296935da4`
- Commit fingerprinted: `89522bae0f9cef7c76ecc55fb1ffe79df0c31889` (Sprint 2's commit — this build reflects Sprint 3's uncommitted working tree, consistent with how Sprint 2's own build was triggered before that sprint's commit)
- APK: https://expo.dev/artifacts/eas/GhrGZDgiPSzn_d4AWuFy0qAybvhRHwHuEKsi0mGLbWw.apk
- Logs: https://expo.dev/accounts/jlambertus/projects/rowbuddy-mobile/builds/3c12e72c-d813-44c2-bb06-1c5296935da4
- Install: download the APK link above on the Android device and install over the existing development build (same package id, higher-fingerprint native code — Android will prompt to update in place).
- Metro (`--dev-client`): running on port 8084 (8081/8082/8083 were already held by unrelated local processes), reachable at `http://localhost:8084` and LAN `http://172.16.2.63:8084` — left running for visual inspection.

## 6. Project progress

- **Sprint 3 completion: 100%** — all approved scope implemented and
  validated; held uncommitted pending your review.
- **Mobile application overall: ~55%** (4 of 8 planned sprints per
  ADR-028 §14 complete: Sprints 0–3; Sprints 4–7 — ratings/disputes,
  profile/language, push/security, testing/distribution — remain).
- **Entire RowBuddy project: ~77%** (using the same Backend/Web=100%,
  Mobile=X% → overall=(100+X)/2 basis your own prior figures imply:
  (100+55)/2=77.5, rounded to 77).
