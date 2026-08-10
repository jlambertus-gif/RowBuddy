# Mobile Sprint 7 — Implementation Report

Status: held completely uncommitted, pending review. Scope — every
remaining mobile capability ADR-028 explicitly defines — is complete and
fully validated. This sprint closes the ADR-028 capability list: after
an exhaustive re-read of ADR-028 itself, `docs/mobile/architecture-
review.md` §7's screen map, and §8/§9's security and offline sections,
exactly two concrete, unbuilt, explicitly-grounded gaps were found. Both
are implemented. No backend changes were required for either.

## 1. What was implemented

### QR-screen screenshot protection (ADR-028 Decision 7, explicit)

Decision 7 states: "QR-screen screenshot protection is adopted
(Android's `FLAG_SECURE` or the Expo/RN equivalent) on the screen
displaying a buyer's transfer QR code." A full-codebase search
(`FLAG_SECURE`/`screen-capture`/`ScreenCapture`/`screenshot`) confirmed
this had never been implemented anywhere, on either platform.

- Installed `expo-screen-capture` (new native dependency — see EAS
  rebuild note below). It ships no config plugin, so no `app.json`
  change was needed.
- `app/transfers/[transferId].tsx`'s `BuyerQrCode` component now calls
  `ScreenCapture.preventScreenCaptureAsync()` in a `useEffect` scoped
  specifically to the moment the QR code is actually revealed
  (`reveal.data` truthy), with `allowScreenCaptureAsync()` as the
  cleanup — not applied to the component's whole mounted lifetime,
  which also covers the "reveal code" button shown before the QR
  exists. This matches the ADR's literal wording ("on the screen
  displaying a buyer's transfer QR code") rather than the broader,
  unstated alternative of protecting the entire transfer-detail screen
  regardless of what's currently shown.
- Three new tests in `app/transfers/__tests__/[transferId].test.tsx`:
  protection is NOT engaged while only the reveal button is shown;
  it IS engaged once the QR renders; and it's released on unmount.

### Discovery pagination (real web/mobile parity gap, zero new backend surface)

Comparing web's `Discover.jsx`/`Pagination.jsx` (page-number buttons,
`per_page: 20`) against mobile's `useDiscoverQueues`/`home.tsx` (zero
references to `page`/`per_page`/`has_more`) confirmed mobile's discovery
never paginated — it always implicitly fetched only page 1. The backend
(`DiscoverQueuesController`, `QueueDiscoveryService::discover()`) already
fully supports `page`/`per_page` with a `has_more` flag and a default
`per_page` of 20, so this was purely a mobile-side change:

- `useDiscoverQueues` rewritten from `useQuery` to `useInfiniteQuery`
  (`getNextPageParam` reads `has_more`/`page` from the last page's
  `meta`), matching mobile's GPS-driven, form-less discovery — an
  infinite-scroll pattern rather than web's page-number buttons, since
  there's no manual search form to attach page controls to.
- `app/home.tsx` flattens `discovery.data.pages` into a single list,
  wires `FlatList`'s `onEndReached` to `fetchNextPage()` (guarded
  against re-triggering while a fetch is already in flight), and shows
  a footer spinner (`testID="discovery-loading-more"`) while the next
  page loads.
- Two new tests in `app/__tests__/home.test.tsx` (plus the existing
  "lists discovered queues" test updated for the new `{ pages: [...] }`
  shape): the footer spinner appears and does not double-fire while a
  fetch is in flight; a fresh `onEndReached` does trigger `fetchNextPage`
  when no fetch is already running.

### Investigated and deliberately NOT built

- **Queue-position listing detail screen** — architecture review §7
  flagged this only as "unconfirmed, needs verification," not as an
  explicit mandate. Verified via routes/controllers that no single-queue
  JSON detail endpoint exists anywhere (only list-discovery). This
  resolves the ADR's own "needs verification" language (answer: it
  doesn't exist) without treating that as authorization to build new
  UI/backend — consistent with this sprint's "no speculative
  functionality" scope item.
- **In-app notifications inbox** — ADR-028's own "Explicitly Out of
  Scope" section excludes this outright (Decision 6 adds push only;
  a list/inbox needs its own future product decision).
- **Deep-link Associated Domains/App Links verification** (Decision 7)
  — explicitly gated on production-domain control, an external
  prerequisite, and explicitly "not required to begin Sprint 0" (or, by
  the same logic, any sprint before that prerequisite exists).
- **Biometric authentication** — explicitly out of MVP (Decision 7).
- **Explicit offline banners on payment-method-setup/transfer-confirm**
  screens (architecture review §9) — considered and not built. §9 only
  requires these operations not be silently auto-queued and that a
  manual retry remain the default; both are already true (no queuing
  mechanism exists anywhere in the app, and both screens already
  surface a generic error with manual retry on any network failure).
  Unlike bid placement, which the ADR explicitly requires to disable
  outright when offline, this is soft UX guidance rather than a
  discrete missing capability — treated as discretionary polish, not a
  Sprint 7 gap.

## 2. Validation results

| Check | Result |
|---|---|
| Backend Pest suite | **353 passed** (unchanged — zero backend files touched this sprint) |
| PHPStan | No errors |
| Pint | Same 12 pre-existing, unrelated Fortify/Sanctum-scaffold style issues as documented since Sprint 6; unchanged |
| `composer audit` | Clean |
| Mobile: typecheck / lint / format | Clean |
| Mobile: `npm test` | **77 passed** (73 prior + 4 new: 2 pagination, 2 screenshot-protection... see note) |
| Mobile: `npx expo-doctor` | 19/20 — same pre-existing patch-version drift as prior sprints (expo, expo-location, expo-router) |
| Mobile: `npm audit` | 22 advisories (7 moderate, 15 high) — up from the 10 previously documented. Verified via `git diff --stat package.json` that `expo-screen-capture` was the only dependency this sprint added, and every flagged advisory (`image-size`, `metro`/`@expo/metro-config`, `uuid`/`xcode`/`@expo/config-plugins`) belongs to the pre-existing Expo/Metro/React Native toolchain dependency graph, unrelated to it. The count increase reflects the npm advisory database's own drift since Sprint 6, not a regression introduced here. |
| iOS + Android `expo export` | Both succeed |
| Secret scan | Clean |
| EAS Android dev build | **Triggered and succeeded** — required since `expo-screen-capture` is a new native module. Build: https://expo.dev/accounts/jlambertus/projects/rowbuddy-mobile/builds/57803129-4613-4ee0-adec-00c93f260a06 |

Note on the test count: the actual new-test tally is 4 (2 pagination in
`home.test.tsx`, 2 screenshot-protection in `[transferId].test.tsx`), on
top of the existing "renders a real QR code" test, which already
exercised the QR-display path the new effect hooks into — 73 → 77.

## 3. Deviations from the approved scope

None. Both implemented items are explicitly grounded in ADR-028
(Decision 7 for screenshot protection; Decision 3's own zero-new-
backend-surface posture combined with a confirmed real parity gap for
pagination — the ADR doesn't name pagination directly, but the backend
contract it inherited from Decision 3 already carries full pagination
support, and leaving it unused on mobile while web uses it is the kind
of "final mobile behavior alignment with the existing web application"
item 6 of this sprint's own scope names). Every item considered and
rejected above is documented with its specific reason in §1.

## 4. Remaining risks

- **The new Android development build has not been installed on a
  device or emulator yet.** The EAS build itself succeeded (`expo-
  screen-capture` is a new native dependency, confirmed via `git diff
  --stat package.json`/`package-lock.json` — only 12 lines changed,
  adding exactly this one package), but no one has installed it or
  launched Metro against it in this session.
- **Screenshot protection has not been visually confirmed on a real
  device.** `expo-screen-capture`'s actual OS-level behavior (Android
  `FLAG_SECURE`, iOS screen-recording/screenshot prevention) is only
  validated by mocked component tests here, not a live screenshot
  attempt against the new build.
- **Infinite-scroll pagination has not been exercised against a real
  backend with more than one page of results** — validated by mocked
  hook-return-shape tests only, not an end-to-end fetch across page
  boundaries.
- **The `npm audit` advisory count (22, up from 10)** is documented
  above as pre-existing/toolchain-only and unrelated to this sprint's
  own dependency addition, but has not been independently re-verified
  against the npm advisory database's history — taken on the evidence
  that `expo-screen-capture` alone was added and shares no dependency
  path with any of the three flagged packages.
- **This sprint closes ADR-028's own capability list.** What remains
  distinct from ADR-028 entirely is release/distribution readiness
  (physical-device testing beyond mocked component tests, TestFlight/
  Play internal-testing setup, store-listing assets, a first production
  EAS build) — none of which ADR-028 itself scopes, and none of which
  this sprint's "ADR-028 only" framing authorizes starting without a
  separate scoping decision from you.

## 5. Project progress

- **Sprint 7 completion: 100%** of the grounded scope found — held
  completely uncommitted pending your review and pending the EAS
  rebuild.
- **Mobile application overall: ~83%** — every ADR-028-defined
  capability is now implemented; the remaining ~17% is release/
  distribution readiness (see Remaining risks), not further product
  capability.
- **Entire RowBuddy project: ~91%**, using the same Backend/Web=100%,
  Mobile=X% → overall=(100+X)/2 basis your own prior figures imply:
  (100+83)/2=91.5, rounded to 91.
