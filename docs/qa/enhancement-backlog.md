# Remaining Enhancement Backlog (post-RC1)

Every item below was found during the Functional Acceptance walkthrough,
recorded as a feature/architecture gap (not a defect) in
`functional-acceptance-issue-log.md`, and explicitly deferred past RC1.
None were implemented during the stabilization pass. This backlog exists
so they are not lost, not so they are treated as scheduled work — none
have a target release yet.

| ID | Gap | Current state | Deferral rationale |
|---|---|---|---|
| FG-001 | Locale switching | Full `en`/`es` translation infrastructure exists on both backend (`lang/`) and frontend (`resources/js/lang/`); nothing reads `User.language` or exposes a way to change it. `app()->getLocale()` always resolves to the static `APP_LOCALE=en`. | The existing translation infrastructure is sufficient for RC1; English-only operation is acceptable for the release candidate. Implementing this requires a product decision on where the mechanism lives (session, persisted user preference, URL prefix) before any code is written. |
| FG-002 | Open an auction from the UI | `AuctionService::open()` is a real, tested domain capability; its only caller is a Sprint-4 load-testing command, bypassing HTTP entirely. | Matches ADR-027's documented Phase 9 scope (bidding and viewing, not opening). Leave the existing domain/backend capability unchanged; no new UI before RC1. |
| FG-003 | Suspend/reinstate an account from the UI | `AccountSuspensionService` (Phase 8) is a real, tested domain capability enforced across three independent lookup ports; no admin page or route triggers it. | Same pattern as FG-002 — Phase 8 built enforcement, not an administrative trigger, per that phase's own documented scope. |
| FG-004 | Restricted-category/jurisdiction-rule administration UI | `RestrictedCategoryActivationService`/`JurisdictionRuleActivationService` (Phase 8 Sprint 3) are real, tested write capabilities; enforcement was confirmed live, but activation has no admin page or route. | Same pattern as FG-002/FG-003. |
| FG-005 | Dashboard admin navigation | The Dashboard's role-based button row only links to queue actions, never to Disputes review, Audit log, or Horizon — even for roles capability-gated to reach them. Every page is still correctly access-controlled; this is discoverability only, not a security gap. | Recorded as a usability enhancement. Does not block RC1. |

## Notes for whoever picks these up

- FG-002/FG-003/FG-004 all share the same shape: a real, tested Phase 8/9
  domain service with no HTTP/UI surface. Each is a comparatively small
  controller + Inertia page addition against an already-correct and
  already-tested service layer — the risk is in the UI/authorization
  wiring, not the domain logic.
- FG-001 is the one item here that is a genuine product decision, not
  just missing plumbing — resolve the "where does locale preference live"
  question before scoping the implementation.
- FG-005 can likely be resolved in isolation from the others, and does not
  require any of them to land first.
