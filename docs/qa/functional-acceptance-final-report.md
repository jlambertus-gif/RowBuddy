# Final Functional Acceptance Report

Closes the Functional Acceptance stage entered after Phase 9 (`docs/releases/phase-9-completion-report.md`).
Covers the 23-scenario browser walkthrough (`functional-acceptance-checklist.md`),
the resulting issue log (`functional-acceptance-issue-log.md`), and the
stabilization pass that followed (`stabilization-summary.md`).

## Walkthrough outcome

23 scenarios executed via Claude in Chrome against the four prepared test
accounts. Totals: 16 Pass, 2 Blocked/External-Blocker (Scenarios 11–12,
missing Stripe test-mode credentials), 1 N/A (Scenario 15, Ratings has no
HTTP/UI surface by documented Phase 7 scope), 1 Incomplete (Scenario 18,
Dispute resolution workflow not exercised for lack of a seeded dispute).
No scenario contradicted documented behavior — every Pass reflects a real,
confirmed correct mechanism. Full per-scenario detail is in
`functional-acceptance-checklist.md`.

Three defects and five feature/architecture gaps were found and recorded
before any fix was made, per the walkthrough's own execution rules.

## Defects — all resolved

| ID | Summary | Status |
|---|---|---|
| FA-001 | Hardcoded Spanish strings in five auth/dashboard pages, inconsistent with the rest of the app's English rendering | **Resolved** |
| FA-002 | Raw Stripe SDK exception (`api_key cannot be the empty string`) shown directly to users | **Resolved** |
| FA-003 | Fortify's built-in verification/reset emails used Laravel's unbranded default template | **Resolved** |

See `stabilization-summary.md` for the fix detail, validation results, and
live re-verification evidence for each. The issue log's RC1 gate — zero
entries `Open` or `In Progress` — is met.

## Feature/architecture gaps — deferred enhancement backlog

| ID | Summary | Decision |
|---|---|---|
| FG-001 | No locale-switching mechanism exists; Spanish is unreachable through the product UI despite full translation infrastructure | Deferred until after RC1 |
| FG-002 | No UI exists to open an auction (domain/backend capability only) | Deferred until after RC1 |
| FG-003 | No UI exists to suspend/reinstate an account (domain/backend capability only) | Deferred until after RC1 |
| FG-004 | No UI exists to activate/deactivate a restricted category or jurisdiction rule (domain/backend capability only) | Deferred until after RC1 |
| FG-005 | Dashboard admin navigation doesn't link to Disputes/Audit/Horizon for capable roles | Deferred, recorded as a usability enhancement, does not block RC1 |

None of these were implemented during stabilization, per explicit
instruction. Full detail and rationale in `enhancement-backlog.md`.

## External blockers — unchanged, out of this stage's scope

Recorded, not resolved, by this walkthrough:

- Real Stripe test-mode credentials (blocks Scenarios 11–12 specifically)
- US legal-review determinations (0 of 11 resolved)
- GitHub Secret Scanning owner verification
- Production-like Linux capacity rerun
- `main` branch merge approval

All five remain owner-controlled activities separate from RC1 readiness,
consistent with `docs/releases/launch-readiness-report.md`.

## Gate status

**Met.** Every defect found during the walkthrough is resolved and
live-reverified with zero regressions. Every feature/architecture gap has
an explicit deferral decision recorded, not a silent omission. See
`../releases/rc1-readiness-report.md` for the RC1 determination itself.
