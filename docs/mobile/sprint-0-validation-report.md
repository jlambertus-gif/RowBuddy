# Mobile Sprint 0 — Validation Report

Status: held uncommitted alongside ADR-028 and the new `apps/mobile`
project, pending your review. No authentication, no product screens, and
no backend consumption were implemented — exactly as scoped. No file
under `apps/web`, `apps/admin`, `apps/api`, or `packages/*` was touched.

## What was created

`apps/mobile` — a new top-level app alongside `apps/web`/`apps/admin`/
`apps/api`, matching this repo's existing convention of a per-app
directory with its own README where reserved (see Finding, below).

| Sprint 0 item | Delivered as |
|---|---|
| 1. React Native/Expo project | `create-expo-app`, blank-typescript template, Expo SDK 57 |
| 2. TypeScript | `tsconfig.json` (strict, `@/*` → `./src/*` path alias, `jest` types registered) |
| 3. Expo Router | `expo-router` + peer deps (`react-native-screens`, `react-native-safe-area-context`, `expo-linking`, `expo-constants`); `app/_layout.tsx` root layout; `experiments.typedRoutes` enabled |
| 4. TanStack Query | `src/lib/queryClient.ts` — `QueryClient` with mutation `retry: false` by default (ADR-028 Decision 7/§9: only a feature that confirms its own idempotency may override this) |
| 5. Project structure | `app/` (routes) + `src/{api,components,features/*,hooks,i18n,lib,types}` |
| 6. i18n | `i18next` + `react-i18next`, `expo-localization` device-locale detection, `en`/`es` `common` namespace — deliberately diverges from web's i18n (which never sets `lng`) per ADR-028 Decision 4 |
| 7. SecureStore | `src/lib/secureStore.ts` — generic get/set/delete wrapper, no auth-specific logic |
| 8. ESLint/Prettier | `eslint-config-expo` (flat config) + `eslint-config-prettier`; `.prettierrc.json`/`.prettierignore` |
| 9. Testing | `jest-expo` preset, `@testing-library/react-native` 14, `test-renderer` (RNTL 14's replacement for deprecated `react-test-renderer`); 4 passing tests |
| 10. EAS | `eas.json` (development/preview/production build profiles); `eas-cli` deliberately **not** added as a project dependency (see Finding 2) |
| 11. Initial folder architecture | `src/features/{auth,auctions,transfers,ratings,disputes,profile,notifications}`, each a reserved placeholder README naming the ADR-028 decision and sprint it's scoped for |
| 12. This report | — |

No screen beyond a single, explicitly-labeled Sprint 0 placeholder route
(`app/index.tsx`) exists — it renders two translated strings to prove
Router + i18n + TanStack Query are wired together, and is not a product
screen (no auth, no navigation to anything, no API call).

## Findings surfaced during scaffolding

**Finding 1 — `apps/api` is a pre-existing reserved directory, and its
own README already anticipates exactly this project.** Phase 0
(2026-07-21, before any mobile work began) reserved `apps/api` with this
note: *"reserved for a future headless, versioned API — the kind of
surface a native mobile app or a third-party integration would need...
[stays] empty until a second real API consumer exists."* ADR-028
Decision 2/3 designed the new `/api/v1` surface as a new `routes/api.php`
**inside `apps/web`**, not as a separate `apps/api` Laravel installation.
This is worth an explicit decision before Sprint 1 touches the backend:
keep the new API surface inside `apps/web` (as ADR-028 currently
specifies — same codebase, same deploy, just a second route file and
guard) or use this project's own reserved `apps/api` slot instead (a
second Laravel installation, more separation, more operational overhead
this early). I did not act on this — it's a real fork in the ADR-028
design that only became visible once `apps/mobile` needed a sibling
directory of its own, and it affects Sprint 1, not Sprint 0. Flagging
rather than deciding it myself.

**Finding 2 — a genuine dependency-version conflict, resolved correctly,
not masked.** `npx expo install` failed with an ERESOLVE error on the
very first attempt to add `expo-secure-store`/`expo-localization`. I did
not reach for `--legacy-peer-deps` as a first response — I traced it with
`npm ls` and found the real cause: `expo-router@57.0.10`'s own dependency
tree includes an optional web-only UI chain (`@expo/ui` → `vaul` →
`@radix-ui/*`) that pulls in `react-dom@19.2.8`, whose peer dependency
wants `react@^19.2.8` — newer than the `react@19.2.3` the official
blank-typescript template pins (and than `react-native@0.86.2` itself
was validated against). Bumping `react`/`react-dom` up to `19.2.8`
"worked" but `npx expo-doctor` correctly flagged it as a patch-version
mismatch against what Expo SDK 57 actually tested. The correct fix,
applied instead: an `overrides` entry pinning the transitively-pulled
`react-dom` down to exactly `19.2.3`, matching `react` and everything
Expo SDK 57 validated against. `npx expo-doctor` now reports **20/20
checks passed**, with no override flag anywhere in `package.json`/`.npmrc`.

**Finding 3 — `eas-cli` should not be a project dependency.**
`expo-doctor` flagged this directly (Expo's own stated guidance: install
EAS CLI globally or invoke it via `npx`, not pin it in-project). Removed
it from `devDependencies`; EAS commands in this project are always run as
`npx eas-cli@latest <command>`.

**Finding 4 — RNTL 14's `render()` is now `async`.**
`@testing-library/react-native` 14.x replaced the deprecated
`react-test-renderer` with a new `test-renderer` package and made
`render()` return a `Promise` (a real breaking API change, not a
regression in this project). The placeholder route's test wasn't
awaiting it, causing every `screen.*` query to fail with `` `render`
function has not been called ``. Fixed by awaiting `render()`; not
worked around.

**Finding 5 — no viable non-breaking fix for one transitive advisory.**
`npm audit` reports 10 moderate-severity advisories, all rooted in one
chain: `@expo/config-plugins` → `xcode` → `uuid` (a build-time-only iOS
project-generation dependency, never reachable at runtime). The suggested
`npm audit fix --force` would downgrade `expo` itself to `46.0.21` — a
destructive, wholesale downgrade of the entire SDK. Not applied. This is
a known, accepted, build-tooling-only advisory, not a runtime risk.

## Validation results (all re-run fresh, final state)

| Check | Result |
|---|---|
| `npx expo-doctor` | **20/20 checks passed** |
| `npm run typecheck` (`tsc --noEmit`) | Clean |
| `npm run lint` (`expo lint`, flat ESLint config + Prettier compat) | Clean, 0 warnings |
| `npm run format:check` (Prettier) | Clean |
| `npm test` (Jest, `jest-expo` preset) | **4/4 passed** — 3 `secureStore` wrapper tests, 1 placeholder-route render test |
| `npx expo export --platform ios` | Succeeds — 1,180 modules bundled, real `.hbc` bundle produced |
| `npx expo export --platform android` | Succeeds — 1,309 modules bundled, real `.hbc` bundle produced |
| Secret scan (API keys, private keys, tokens, credential-style assignments) | Clean |
| `npm audit` | 10 moderate, all one build-tooling-only transitive chain (Finding 5); no viable non-breaking fix exists upstream |
| Web/backend repository touched | **No** — `git status` confirms zero changes outside `apps/mobile` and the two `docs/` additions (this report, ADR-028's status update) |

## What is explicitly not yet done (by design, per Sprint 0's own scope)

- No `routes/api.php`, no Sanctum install, no backend change of any kind
  (Sprint 1).
- No auth screens, no token storage wired to any real credential (Sprint
  1) — `secureStore.ts` is a generic wrapper only.
- No real navigation structure beyond the single placeholder route
  (Sprint 1 onward).
- App identifiers (`com.rowbuddy.mobile` for both platforms) and the
  EAS project itself are **placeholders**, not final — real values, and
  actually linking the project to an EAS account, remain blocked on the
  external prerequisites ADR-028/the architecture review already named
  (Apple Developer account, Google Play Console account, Expo/EAS
  account). `eas.json`'s build profiles are configured; `eas init`/`eas
  build` have not been run.

## Outcome

Sprint 0 is complete and clean by every check available without an
external account. Held uncommitted, alongside the ADR-028 update
(Status: Accepted, Decision 8 added) it was authorized under. Awaiting
your review — and, separately, your decision on Finding 1 — before any
commit or before Sprint 1 begins.
