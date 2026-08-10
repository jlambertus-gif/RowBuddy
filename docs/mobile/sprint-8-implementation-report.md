# Mobile Sprint 8 — Implementation Report

Status: held completely uncommitted, pending review. Scope was resolved
via an explicit clarifying question before any work began: my Sprint 7
investigation had already concluded ADR-028's own capability list is
fully closed, so "implement the remaining capabilities explicitly
defined by ADR-028" (Sprint 8 scope item 1) had nothing left to act on.
You chose "in-repo release prep + assessments" — the credential-free,
in-repo release-readiness gaps the architecture review's own research
flags, plus the five closing assessments requested. No ADR-028 capability
work exists in this sprint because none remained.

## 1. What was implemented

### Native launch splash screen wired up (credential-free release-readiness gap)

`apps/mobile/assets/splash-icon.png` existed on disk but was never
referenced anywhere — no `expo-splash-screen` plugin, no `splash` key in
`app.json` at all. The app had no branded native launch screen.

- Installed `expo-splash-screen` (new native dependency — see EAS
  rebuild note below).
- Configured it in `app.json`'s `plugins` array: `image` →
  `./assets/splash-icon.png`, `imageWidth: 200`, `resizeMode: "contain"`,
  `backgroundColor: "#E6F4FE"` — the same background color already used
  by the Android adaptive icon, for visual consistency across icon and
  splash.
- No code changes were needed to hide the splash at the right time —
  `expo-router`'s root already calls the real `expo-splash-screen`
  module's `hideAsync()` once it mounts, and `app/_layout.tsx` has no
  competing splash logic.
- Fixed a resulting visual seam: `app/index.tsx` (the token-bootstrap
  loading screen shown immediately after the native splash hides) had a
  hardcoded white background, which would flash against the new light-
  blue splash. Changed it to the same `#E6F4FE` for a seamless
  hand-off.

### Other release-readiness items audited, found already satisfactory

- **iOS bundle ID / Android package name**: already decided and
  reserved (`com.rowbuddy.mobile` both platforms) — nothing to do.
- **App icon and adaptive-icon assets**: `icon.png` (1024×1024) and the
  Android adaptive-icon foreground/background/monochrome set (512×512
  each) are all present and correctly sized per each store's spec.
- **Signing credentials**: already handled — Sprint 7's own EAS build
  log confirmed "Using remote Android credentials (Expo server)" with
  an existing managed keystore; nothing in-repo to configure.
- **`eas.json` build/submit profiles**: development/preview/production
  build profiles and a production submit profile already exist and are
  correctly shaped; nothing missing that doesn't require real store
  credentials to fill in (see §3 blockers below).

## 2. Validation results

| Check | Result |
|---|---|
| Backend Pest / PHPStan / Pint / `composer audit` | N/A this sprint — zero backend files touched |
| Mobile: typecheck / lint / format | Clean |
| Mobile: `npm test` | **73 passed** (unchanged — this sprint's changes are launch-time native config with no new behavior to unit-test; no tests were force-fitted to a config-only change) |
| Mobile: `npx expo-doctor` | 19/20 — same category of pre-existing patch-version drift as every prior sprint, now covering 7 packages (`expo`, `expo-constants`, `expo-dev-client`, `expo-location`, `expo-notifications`, `expo-router`, `jest-expo`) rather than 3, purely from time passing since Sprint 7, not from anything this sprint changed — confirmed via `git diff package.json`, which shows only `expo-splash-screen` added |
| Mobile: `npm audit` | 23 advisories (8 moderate, 15 high) — up by exactly one from Sprint 7's 22. The new entry is `expo-splash-screen`'s own dependency on the same already-documented `@expo/config-plugins` → `xcode` → `uuid` chain every other config-plugin-carrying Expo package pulls in — not a new, distinct vulnerability |
| iOS + Android `expo export` | Both succeed |
| Secret scan | Clean |
| EAS Android dev build | **Triggered and succeeded**, no warnings — `expo-splash-screen` is a new native module. Build: https://expo.dev/accounts/jlambertus/projects/rowbuddy-mobile/builds/5d8d4706-ccd8-421f-aad6-bd75f1408e84 |

## 3. Deviations from the approved scope

- **Sprint 8 scope item 1 ("implement remaining ADR-028 capabilities")
  produced zero implementation work**, by design — see the status note
  above. This was surfaced to you as a clarifying question before any
  code was touched, not discovered mid-sprint.
- **No new tests were added.** Every prior sprint's mandatory test
  checklist item ("mobile unit/component tests") assumed new behavior
  to test; this sprint's two changes are native launch-time
  configuration (a plugin entry, a background-color constant) with no
  branching logic to assert against. Forcing a test into existence here
  would test the config value, not behavior — decided against, per this
  session's "no duplicated/speculative work" posture.

## 4. Final ADR-028 completion assessment

Going through ADR-028's 8 Decisions individually:

| Decision | Status |
|---|---|
| 1. Mobile technology stack (Expo/RN, managed workflow) | **Complete** — in place since Sprint 0, unchanged since |
| 2. Mobile auth (Sanctum personal-access tokens) | **Complete** — Sprint 1 |
| 3. Net-new backend API surface | **Complete** — every item (auth, queue submission, ratings, disputes-filing, profile/locale-write, account-standing, device push-token) built across Sprints 1–6; zero remaining |
| 4. Mobile locale switching | **Complete** — Sprint 4 (mobile is the first-ever writer of `language`/`country_code`/`currency`/`timezone`) |
| 5. No composed transaction-status endpoint | **Complete by design** — a deliberate non-decision; nothing to build |
| 6. Push notifications, second channel | **Complete** — Sprint 4 (device registration, all 8 notification types, delivery ledger channel dimension); production APNs/FCM credentials remain an external prerequisite, not an implementation gap (see §6) |
| 7. MVP security posture | **Complete** — biometric auth correctly excluded; QR-screen screenshot protection implemented Sprint 7; deep-link Associated-Domains verification correctly deferred (blocked on production domain control, explicitly not required pre-launch); manual-retry-as-default and IDOR discipline held throughout |
| 8. Mandatory test coverage for new endpoints | **Complete** — every net-new endpoint across Sprints 1–7 has feature, authorization, IDOR-where-applicable, and contract test coverage |

**Conclusion: ADR-028 is fully implemented.** No further product capability
work is authorized or needed under this ADR. Anything proposed beyond
this point is either release/distribution logistics (§6 below) or would
require a new product decision outside ADR-028's own scope.

## 5. Remaining known limitations

- **No physical-device or emulator verification has occurred at any
  point in this mobile track.** Every validation pass across all 8
  sprints has been Jest/RNTL component tests (mocked hooks/native
  modules), TypeScript/ESLint/Prettier checks, and successful Metro
  bundle exports/EAS cloud builds — never an actual install-and-run on
  hardware or a simulator/emulator. This means real-device concerns the
  architecture review itself flagged — background-location battery
  behavior, actual GPS/camera behavior, real push-notification receipt,
  the new splash screen's and screenshot-protection's real on-device
  appearance — are unverified beyond what a mock can prove.
- **No iOS EAS build has ever been triggered.** Every native rebuild
  this session (Sprint 4's push module, Sprint 7's screen-capture,
  Sprint 8's splash-screen) has been Android-only. `expo export
  --platform ios` succeeding proves the JS bundle is valid; it does not
  prove the iOS native binary builds or runs.
- **Stripe test-mode credentials remain unconfigured** — inherited
  directly from web's own pre-existing, already-documented blocker
  (not a new mobile-specific gap). Payment-method-setup and bid/transfer
  flows touching Stripe cannot be exercised end-to-end against real
  Stripe test mode from mobile until this is resolved for the whole
  project.
- **Android/iOS push-notification production credentials are not
  configured** — see §6.
- **Certificate pinning was deliberately not adopted** (architecture
  review §8) — standard TLS validation only, an accepted MVP-scope
  trade-off, not an oversight.
- **Deep links use a custom URL scheme (`rowbuddy://`)**, acceptable
  only pre-production per Decision 7 — must move to verified Associated
  Domains/App Links before any public release, which requires
  production domain control this project doesn't have yet.

## 6. Technical debt list

1. **`expo-doctor` patch-version drift** — 7 packages (`expo`,
   `expo-constants`, `expo-dev-client`, `expo-location`,
   `expo-notifications`, `expo-router`, `jest-expo`) sit slightly behind
   the versions the installed Expo SDK actually recommends. Low risk,
   but `npx expo install --check` should be run and reviewed before any
   production build.
2. **23 `npm audit` advisories, all pre-existing Expo/Metro/React-Native
   toolchain transitive dependencies** (`image-size`, `metro`/
   `@expo/metro-config`, `uuid`/`xcode`/`@expo/config-plugins`) — dev/
   build-time tooling only, not shipped in the production JS bundle.
   Each new native module this session added has, incidentally, added
   one more edge into this same pre-existing chain (never a distinct
   new vulnerability). Worth a dedicated cleanup pass (`npm audit fix`,
   checked for breaking changes) at some point, but not urgent.
3. **Pint's 12 pre-existing style issues** in `apps/web`'s Fortify/
   Sanctum scaffold files (missing `declare_strict_types`, etc.) —
   present since before this mobile track began, untouched across every
   mobile sprint since they're outside mobile's own scope.
4. **No CI-integrated contract testing against a live backend for
   mobile.** The architecture review (§10) recommended API-contract
   tests running "against a real test-mode backend in CI (not mocks
   alone) so backend drift breaks CI on both sides." Mobile's contract
   tests are fixture/mock-based only — real backend-drift protection
   for mobile's `/api/v1` consumers doesn't exist yet.
5. **`useDiscoverQueues`'s new infinite-scroll pagination (Sprint 7)
   has never been exercised against a real backend with more than one
   page of results** — validated only by mocked hook-return-shape
   tests.
6. **Web's own pre-existing, unfixed gaps mobile deliberately left
   untouched** (documented at the time, not new): `SubmitterAccount-
   Suspended` uncaught in web's own `QueueSubmissionController`;
   Fortify's own `PUT user/profile-information` route has no rate
   limiter (mobile's equivalent does); `register`/`forgot-password`/
   `reset-password`/buyer-payment-method/queue-submission all lack
   rate limiting on web as well as mobile. All out of mobile's own
   scope per this session's established "don't expand into web-only
   fixes" discipline, but still open project-wide debt.

## 7. Release-readiness assessment

**What's ready:**
- Every ADR-028 mobile capability is implemented and tested.
- Bundle identifiers, app icons, adaptive icons, and now the launch
  splash screen are all correctly configured in-repo.
- EAS build/submit profiles exist and Android builds succeed reliably
  (four successful cloud builds across Sprints 4, 7, and 8) using
  Expo-managed remote signing credentials.
- The mobile-specific IDOR/rate-limit security review
  (`docs/security/mobile-security-checklist.md`) is complete.

**What's blocking an actual store submission, and is outside what I can
resolve myself — all require you to create/provide external
accounts or credentials:**

| Blocker | What's needed |
|---|---|
| No Apple Developer Program account confirmed | Required for any iOS build/TestFlight submission |
| No Google Play Console developer account confirmed | Required for any Play internal-testing track |
| No Android push (FCM) production credentials | A Firebase project + server credentials, uploaded to the EAS project — required before push notifications work in a production/standalone build (development builds work without it) |
| No iOS push (APNs) credentials | Requires the Apple Developer account above |
| No public privacy-policy URL confirmed to cover mobile-specific data (location, camera, push tokens) | Required by both app stores before submission; check against `docs/legal/us-launch-review.md` |
| No public support URL confirmed | Required by both app stores |
| Stripe test-mode credentials still unconfigured | Inherited blocker from web; blocks any real end-to-end payment testing on either platform |
| No production domain control | Blocks moving deep links off the dev-only custom URL scheme, per Decision 7 |
| No physical-device testing performed | Recommended before submission regardless of store requirements — at least one iOS and one Android physical device, per the architecture review's own testing strategy |

None of these can be closed by further coding — they require decisions
and account/credential provisioning only you can make.

## 8. Final project progress

- **Sprint 8 completion: 100%** of the scope actually available to
  execute (in-repo release prep + all five requested assessments) —
  held completely uncommitted pending your review and the EAS build
  result.
- **Mobile application overall: ~90%** per your own stated baseline —
  ADR-028's full capability list is closed; the remaining ~10% is
  entirely external-prerequisite-gated release/distribution work (§7),
  not further mobile engineering.
- **Entire RowBuddy project: ~95%** per your own stated baseline —
  Backend/Web at 100%, Mobile at ~90%: (100+90)/2 = 95.
- **What would close the remaining ~5–10%**: none of it is code. It's
  the external-account/credential list in §7, plus whatever real-device
  and store-submission validation follows once those exist.
