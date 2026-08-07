# Mobile Sprint 5 — Implementation Report

Status: held uncommitted, pending review. Scope — queue creation,
auction-participation/offline/error/loading polish, and a verification
pass on deep-link completion — is complete and fully validated. Queue
*management* (item 2) was descoped per your explicit decision: no
"view/manage my submitted queues" capability exists anywhere in the
codebase today, and ADR-028 never designs one.

## 1. What was implemented

### Backend (`apps/web`) — one new route, zero new domain logic

- `POST /api/v1/queues` — the JSON variant of the existing web
  session-guarded `/queues` route (ADR-028 §3), reusing
  `QueueSubmissionService::submitForApproval()` and `SubmitQueueRequest`
  verbatim. submittedByUserId is derived exclusively from the
  authenticated user.
- Along the way, found (but deliberately did **not** fix, since it's
  outside this sprint's mobile scope) a pre-existing gap: web's own
  `QueueSubmissionController` never catches `SubmitterAccountSuspended`
  at all — a suspended user submitting a queue on web today gets an
  uncaught 500, not a clean rejection. My new endpoint handles it
  correctly (403); web's own controller does not. Flagging this for a
  future web-side fix, not silently patching adjacent code this sprint
  didn't ask me to touch.
- `tests/Feature/SubmitQueueApiTest.php` — 7 tests (feature/contract,
  authorization, IDOR, every domain-rule branch already covered by
  `QueueSubmissionTest.php` against the existing web route, plus the
  suspended-account case web's own controller doesn't handle).

### Mobile (`apps/mobile`)

- **`app/queues/submit.tsx`** (new) — mirrors web's Queues/Submit.jsx
  fields exactly; adds a "use my current location" convenience button
  (GPS requested contextually, only on that button press) that web
  doesn't have, since manual lat/lng entry is web's own only option.
- **Offline/retry polish** (architecture review §9, designed at the
  very start of this project but never actually built before this
  sprint): `src/lib/network.ts`'s `useIsOnline()` now disables bid
  submission outright (not queued) when connectivity drops, with a
  visible "you're offline" notice on the auction detail screen —
  exactly the behavior the architecture review specified and no prior
  sprint implemented.
- **Staleness labels** (same architecture-review section, same gap):
  `src/lib/useStalenessLabel.ts` adds a ticking "Updated Xs/m ago" label
  to auction detail, transfer detail, and discovery — cached reads are
  no longer presented as live truth with no indication of age.
- **Error-state polish**: discovery (`app/home.tsx`) previously had no
  branch at all for a `useDiscoverQueues` failure distinct from a
  location failure — a real gap where the screen simply went blank on
  an API error. Auction detail and transfer detail previously showed a
  static error with no way to recover short of leaving the screen; both
  now distinguish 404 (genuinely gone) from any other failure (retry
  button, calls the query's own `refetch()`).
- **Deep-link completion (item 7)**: verified the existing
  `rowbuddy://auth/callback` custom-scheme flow (Sprint 1) still works
  end-to-end. No further code was possible or written — platform-
  verified Associated Domains/App Links remain blocked on the external
  production-domain prerequisite (confirmed still unresolved:
  `apps/web/.env`'s `APP_URL` is still `http://localhost`), exactly as
  ADR-028 itself anticipated. Nothing to build here until that
  prerequisite is met.

## 2. Validation results

| Check | Result |
|---|---|
| Backend Pest suite | **348 passed** (341 prior + 7 new) |
| PHPStan | No errors |
| Pint | Clean (Sprint 5 files); 12 pre-existing, unrelated style issues remain in untouched Fortify/Sanctum scaffolding files, unchanged from prior sprints |
| `composer audit` | **Fixed a real, newly-disclosed finding** — see below |
| Mobile: typecheck / lint / format | Clean |
| Mobile: `npm test` | **64 passed** (51 prior + 13 new) |
| Mobile: `npx expo-doctor` | 19/20 — same pre-existing, unrelated patch-version drift as Sprints 3–4 |
| Mobile: `npm audit` | Same 10 pre-existing moderate advisories, unchanged; zero new (no new mobile dependencies this sprint) |
| iOS + Android `expo export` | Both succeed |
| Secret scan | Clean |
| EAS Android dev build | **Not needed this sprint** — no new native module was added |

**`composer audit` finding, fixed:** a medium-severity advisory
(CVE-2026-71478, disclosed the same week as this sprint) in
`league/commonmark` 2.8.3, a transitive dependency of `laravel/framework`
itself — confirmed via `grep` that this codebase never calls
`Str::markdown()` or touches CommonMark directly anywhere, so actual
exposure was already zero, but I updated it to 2.9.0 (within
`laravel/framework`'s own `^2.8.1` constraint) since a clean `composer
audit` is one of this sprint's explicit validation requirements. Full
backend suite (348 tests) and PHPStan re-ran clean after the bump.

### Two real bugs caught during this sprint's own validation, not shipped

- **A copy-paste-wrong translation key** in the queue-submission
  screen's generic-error fallback (`t('submit.submitting')` instead of
  a real error key) — caught while writing the screen, fixed before any
  test ran against it.
- **An ESLint "impure render"/"set-state-in-effect" pair** in
  `useStalenessLabel`: the first version called `Date.now()` directly
  in the render body (flagged as impure), and the fix that moved it into
  an effect then called `setState` synchronously within that effect
  (a second, different lint rule). Resolved by deferring the initial
  tick through a zero-delay `setTimeout`, matching the same
  effect-purity discipline already established for `home.tsx`/
  `profile.tsx` in earlier sprints.

A genuine Jest-environment gotcha, not a bug: `@react-native-community/
netinfo` has no automatic Jest mock despite living in a root-level
`__mocks__/@react-native-community/netinfo.js` — Jest's documented
automatic-mocking-for-scoped-node-modules behavior did not trigger in
this project's setup, requiring an explicit `jest.mock('@react-native-
community/netinfo')` call in every test file that renders a component
using `useIsOnline()`. Documented inline in the mock file itself for
the next sprint that needs it.

## 3. Deviations from the approved scope

- **Queue management dropped**, per your explicit decision (see
  header) — not designed, not built, documented as an open gap for a
  future ADR.
- **Deep-link completion produced no code changes** — verified-only,
  blocked externally on the production-domain prerequisite exactly as
  ADR-028 anticipated. Not a shortfall in this sprint's effort; there
  was genuinely nothing further achievable.
- **One dependency bump** (`league/commonmark` 2.8.3 → 2.9.0) to keep
  `composer audit` clean, unrelated to any of this sprint's own feature
  work — flagged explicitly since it wasn't part of the original scope
  list, but necessary to meet an explicit validation requirement.
- **A pre-existing web bug was found, not fixed**: web's own
  `QueueSubmissionController` doesn't handle `SubmitterAccountSuspended`
  (see §1) — left alone since fixing web-facing code wasn't in this
  sprint's mobile scope.

## 4. Remaining risks

- **Offline behavior validated by mocked NetInfo state changes in
  component tests, not a real airplane-mode test on a physical
  device.** Please toggle airplane mode on the auction detail screen as
  part of this sprint's visual inspection to confirm the bid button
  actually disables and the message actually appears.
- **Queue submission has not been exercised against a real GPS fix on
  a physical device** — the "use my current location" button reuses
  the same `getCurrentCoordinates()` helper already proven in Sprints
  2–3, but this specific screen's wiring of it is only covered by
  mocked tests here.
- **The newly-submitted queue is genuinely invisible to its own
  submitter afterward** — with queue management descoped, a user who
  submits a queue has no way to check on it (pending/approved/
  rejected) from mobile, matching web's own current capability exactly
  (web also has no such visibility), but worth having in mind as a
  candidate for a future sprint if it becomes a real point of user
  confusion.

## 5. Project progress

- **Sprint 5 completion: 100%** — all approved scope (minus the
  explicitly-descoped queue-management item) implemented and
  validated; held uncommitted pending your review.
- **Mobile application overall: ~72%** — the original 8-sprint plan's
  remaining distinct item is Sprint 7 (testing/distribution readiness);
  this sprint covered the bulk of the "polish" work that would
  otherwise have been spread across it.
- **Entire RowBuddy project: ~86%**, using the same Backend/Web=100%,
  Mobile=X% → overall=(100+X)/2 basis your own prior figures imply:
  (100+72)/2=86.
