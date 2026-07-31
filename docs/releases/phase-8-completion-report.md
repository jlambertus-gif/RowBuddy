# Phase 8 (Administration & Fraud/Risk v1) — Completion Report

Tag: `v0.9.0-administration`
Status: **Complete and formally accepted**, 2026-07-31 — Administration
closes with real, operating HTTP/UI surfaces (the second exception to
this project's domain/backend-only closure bar, after Notifications in
Phase 7); Fraud & Risk scoring is explicitly deferred beyond MVP (§8).

## 1. Executive summary

Phase 8 delivers **Administration** (`packages/Administration`): a small,
fixed set of administrative roles authorized via capability-based Laravel
Gates; a single, reversible manual account-suspension state enforced
across every existing transactional entry point (Bids, Queues, Ratings);
operational activation/deactivation of restricted categories and
jurisdiction rules, orchestrated through Queues' own narrow write
capabilities rather than direct table access; read-only dispute
case/evidence review plus administrative correction notes that never
mutate or reopen a resolved `Dispute`; and a code-defined, per-event
admin-display allowlist over the existing audit sink, fail-closed for any
event type without a registered definition.

**Automated, rules-based Fraud & Risk scoring is explicitly out of MVP
scope** (Decision 1) — this phase preserves the architectural boundary a
future Fraud & Risk context would need (every module's own domain events
remain independently readable) without building any scoring engine
against them.

All seven Phase 8 product decisions were frozen individually, one at a
time, in direct conversation — trade-offs, a recommendation, explicit
approval — before ADR-026 was drafted, mirroring Phases 6 and 7's own
process. Seven additional architecture refinements were then applied
before Sprint 1 began. One correction was applied mid-phase after
requested verification (Sprint 3, RestrictionActivationService's
authorization posture) and one after requested verification post-
implementation (Sprint 5, audit fail-closed behavior and the
`audit.view` capability's role grant) — both corrected before the
affected sprint was committed, the same discipline every prior phase's
own mid-phase corrections have followed. **765 automated tests pass
across the full ecosystem** (up from 656 at Phase 7 close), with clean
PHPStan/Larastan/Pint throughout.

## 2. Product decisions (frozen individually, before any ADR)

Mirroring Phases 6 and 7's process, all seven Phase 8 product decisions
were resolved one at a time in direct conversation before ADR-026
drafting began:

1. Phase 8 MVP is Administration-focused; automated Fraud & Risk scoring
   is deferred beyond MVP, but the architectural boundary for a future
   Fraud & Risk context is preserved — no Phase 8 capability changes how
   any completed bounded context publishes its own domain events.
2. A small, fixed, closed `AdminRole` enum, assigned through persistent
   storage, with no runtime role/permission editing. Authorization stays
   capability-based via Laravel Gates/Policies, never embedded in the
   role enum itself.
3. Dispute administration excludes reopening — `Dispute.Resolved` stays
   exactly as terminal as Phase 6 built it. Administrative corrections
   are new, separate actions, never a second resolution.
4. A single, reversible account-suspension state; forfeiture (business
   rule 17's other half) explicitly deferred beyond Phase 8, pending
   payout/held-funds infrastructure that does not exist yet.
5. Restricted-category/jurisdiction-rule administration is operational
   only — activate/deactivate an existing row, mandatory reason,
   full audit trail. No creation, deletion, or legal-content editing
   through the admin UI.
6. Audit visibility uses an explicit per-event admin-display allowlist,
   never raw payload pass-through. The audit store itself is unchanged
   and remains the complete system of record.
7. Administration operates only on data already produced by implemented
   bounded contexts. Manual KYC review/override and the
   Verification & Trust bounded context are intentionally excluded — not
   an oversight, but a direct consequence of this principle, since no
   Verification & Trust context exists yet to produce the submissions an
   admin would review.

Seven further architecture refinements were then applied, before Sprint
1 began, resolving implementation-level questions the decisions above
did not: roles/capabilities separation; role-assignment cardinality and
a no-access-gap migration off `users.is_admin`; a typed, fail-closed
`admin_actions` log distinct from the platform audit sink; centralized
(never blanket-middleware) suspension enforcement; no generic
financial-correction engine; bounded-context ownership preserved through
explicit ports (surfacing the `jurisdiction_rules` activation-state gap
resolved in Sprint 3); and presentation-only, fail-closed audit
visibility.

## 3. Sprint-by-sprint deliverables

| Sprint | Commit | Delivered |
|---|---|---|
| 1 | `cb9cb27` | ADR-026 accepted (all seven decisions plus the seven architecture refinements). `packages/Administration` scaffold: closed `AdminRole` enum, `AdminRoleCapabilityMap` (capabilities kept separate from role identity), one Gate per `AdminCapability` case registered generically in `AdministrationServiceProvider::boot()`. Migration off `users.is_admin` (backfill → drop) with no access-gap window, verified against the real dev database. `admin:assign-role` console command (engineering-controlled; no role-management UI). |
| 2 | `e87b353` | Typed `AdministrativeActionType`/`AccountStandingState` enums and the `admin_actions` log — a separate, narrower operational record, never a duplicate of the platform audit sink. `AccountSuspensionService` (suspend/reinstate, mandatory reason, already-suspended/already-active guards). Independent `AccountStandingLookup` ports owned by Bids, Queues, and Ratings, each bridged by its own `apps/web` adapter — enforcement wired into `BidService::attemptPlacement()`, `QueueSubmissionService::submitForApproval()`, `RatingSubmissionService::submit()`, proven to reject before any domain mutation or event publication. |
| 3 | `16822af` | The Queues-domain gap identified during architecture refinement — `jurisdiction_rules` had no activation state independent of its legal content — closed with an additive `active` column, migrated with the existing-row default verified against real Postgres. `RestrictedCategoryActivationService`/`JurisdictionRuleActivationService`, narrow Queues-owned write capabilities exposing only the toggle, never legal-content editing. `RestrictionActivationService` orchestrates both, initially with its own internal authorization check — **corrected during this sprint's review** to remove that check entirely, restoring the same "service enforces business invariants only, the boundary enforces authorization" posture Sprint 2 established, before this sprint was committed. |
| 4 | `9bb8dfe` | `DisputeCaseLookup`, Administration's own consumer-owned read port into Disputes (bridged via Disputes' own `DisputeRepository`, never raw Eloquent queries), backed by an additive `DisputeRepository::findAll()` — no change to `Dispute`'s aggregate or state machine. `DisputeCorrectionService` records a mandatory-reason correction note as its own `admin_actions` entry; `Dispute.Resolved` stays untouched. The first real Administration HTTP/UI: `/admin/disputes` (JSON) and `/admin/disputes/review` (Inertia), gated on the new `disputes.review` capability. A manual browser verification caught and fixed a real issue before commit: evidence rendering was exposing a photo's raw private storage path — corrected to expose written-statement content only, with photo evidence limited to safe metadata. |
| 5 | `1f03e95` | `AuditEventDisplayRegistry` — 36 hand-reviewed, explicit per-event-type allowlists, one for every `AuditableAction` event type in the codebase (verified by direct code search, not assumed), excluding storage/evidence references and Stripe identifiers. `AuditLogService`/`AuditEventLookup` (Administration's read-only view of the existing, unchanged `audit_events` sink) and the `/admin/audit-events` HTTP/UI, gated on a new `audit.view` capability. **Corrected after requested verification, before commit**: an unregistered event type initially still appeared as a metadata-only placeholder row — changed so it is excluded from the result entirely, exactly as ADR-026 §6's fail-closed rule requires; and `audit.view` was initially granted to both admin roles — changed to Administrator-only, since the generic log can surface account, payment, dispute, and geolocation business information even read-only. |
| 6 | (this report) | Phase closure: full validation pass across every affected package and `apps/web`, completion report, roadmap/architecture-overview/CLAUDE.md updates, release tag. |

## 4. ADRs created

- **ADR-026 — Administration MVP Scope and the Fraud & Risk Boundary**:
  records all seven Phase 8 decisions and the seven pre-Sprint-1
  architecture refinements described in §2. Status: Accepted.

ADRs 001–025 were pre-existing and remain unchanged and binding.

## 5. Architecture changes

- One new package, `packages/Administration`, following the same shape
  established since Phase 1.
- **The second exception to this project's domain/backend-only closure
  bar**, after Notifications in Phase 7: Sprints 3–5 each shipped a real
  HTTP endpoint and a minimal Inertia/React page, gated by a real
  capability-based Gate, not a domain/backend-only surface deferred to a
  later phase.
- **Bounded-context write orchestration without direct table access**: a
  new pattern this phase introduces alongside the existing "consumer owns
  the port" *read* pattern — Administration orchestrates restricted-
  category/jurisdiction-rule activation only through Queues' own narrow,
  Queues-owned write-capability services (`RestrictedCategoryActivationService`/
  `JurisdictionRuleActivationService`), bridged by an `apps/web` adapter,
  never by writing to `restricted_categories`/`jurisdiction_rules`
  directly.
- Three independent, identically-shaped `AccountStandingLookup` ports
  (Bids, Queues, Ratings) — the "consumer owns the port" pattern's read
  side, now proven across a fourth kind of cross-cutting concern
  (account standing) after Ratings/Notifications' `TransferParticipantLookup`
  precedent in Phase 7.
- A second, narrower operational ledger (`admin_actions`), deliberately
  never a duplicate of or replacement for the platform-wide `audit_events`
  sink introduced in Phase 2 — the two coexist with clearly separated
  purposes throughout this phase.
- `AuditEventDisplayRegistry` establishes this codebase's first
  presentation-layer allowlist keyed by plain event-name strings rather
  than producer classes, so Administration's own display definitions
  never create a compile-time dependency on any of the eight packages
  whose events it displays.
- A consistent authorization posture, corrected twice into its final
  shape during this phase (Sprint 3, Sprint 5): every Administration
  application service enforces business invariants only; capability
  authorization belongs exclusively at the application boundary (a real
  Gate check in Sprints 3–5's controllers, or the equivalent for a future
  caller) — mirrored by every `Admin*Service` in the package by Phase 8's
  close.

## 6. Database changes

`packages/Administration/database/migrations/`:
- `create_admin_role_assignments_table` — one role per user (Sprint 1).
- `create_account_standings_table`, `create_admin_actions_table` (Sprint 2).

`packages/Queues/database/migrations/`:
- `add_active_to_jurisdiction_rules_table` — additive; default backfills
  every existing row to active (Sprint 3).

`apps/web/database/migrations/`:
- `backfill_admin_role_assignments_from_is_admin`,
  `drop_is_admin_from_users_table` (Sprint 1) — verified against the real
  dev database to preserve uninterrupted administrative access across the
  migration.

No changes to any Phase 1–7 package's schema beyond the additive
`jurisdiction_rules.active` column above. `audit_events` (Phase 2) is
completely unchanged.

## 7. Test and validation results

| Package | Tests | Notes |
|---|---|---|
| `packages/shared-kernel` | 31 | Unaffected. |
| `packages/Queues` | 101 (was 83 at Sprint 2 close) | +18: the `active` column, gate logic, and the two new write capabilities (Sprint 3). |
| `packages/QueuePresence` | 81 | Unaffected. |
| `packages/Auctions` | 66 | Unaffected. |
| `packages/Bids` | 27 | +3 for `AccountStandingLookup` enforcement (Sprint 2). |
| `packages/Payments` | 93 | Unaffected. |
| `packages/Transfers` | 58 | Unaffected. |
| `packages/Disputes` | 47 (was 45 at Phase 7 close) | +2: `findAll()` (Sprint 4). No aggregate or state-machine change. |
| `packages/Ratings` | 43 (was 41 at Phase 7 close) | +2 for `AccountStandingLookup` enforcement (Sprint 2). |
| `packages/Notifications` | 35 | Unaffected. |
| `packages/Administration` | 48 | New package — roles/capabilities, suspension, restriction activation, dispute correction, audit display registry, across five sprints. |
| `apps/web` | 135 (was 101 at Phase 7 close) | Includes end-to-end wiring tests for every new capability/Gate, three new admin HTTP surfaces, and the real-database proofs that sensitive fields (private storage paths, Stripe identifiers) never reach a JSON response even when present in the underlying stored record. |

**Total: 765 automated tests.** PHPStan level 8 clean on
`packages/Administration`, `packages/Queues`, and `packages/Disputes`;
Larastan clean on `apps/web`. Pint clean on all three packages; `apps/web`
carries the same 11 pre-existing style issues present since Phase 1
(reduced from 12 during Sprint 4, when an incidental `routes/web.php` fix
landed alongside unrelated route additions), confirmed unrelated and
unchanged throughout every Phase 8 sprint. Frontend `vite build` clean
throughout. No pending migrations at any sprint boundary.

**Two corrections were requested and applied before their sprint's own
commit, not left latent**:
- Sprint 3: `RestrictionActivationService` initially checked
  `restrictions.moderate` authorization internally — corrected to remove
  the check entirely, restoring the "service enforces invariants only,
  the boundary enforces authorization" posture `AccountSuspensionService`
  (Sprint 2) had already established, before Sprint 3 was committed.
- Sprint 5: an unregistered audit event type initially still produced a
  metadata-only placeholder row, and `audit.view` was initially granted
  to both admin roles — both corrected (excluded entirely; Administrator-
  only) before Sprint 5 was committed.

**One real issue was caught and fixed during Sprint 4's own manual
browser verification, before that sprint's commit**: the dispute-review
JSON response was exposing a photo evidence record's raw private storage
path. Corrected to expose only written-statement content (genuine
evidence text) plus safe metadata for photo evidence — no admin surface
in this codebase now renders a raw evidence storage reference.

## 8. Closure-scope decision

Phase 8 closes with the same posture Notifications established in Phase
7 — a real, operating surface is required, not a domain/backend-only
model:

- **A real HTTP endpoint and a minimal Inertia/React page exist for
  every Phase 8 capability that needs one** (restricted-category/
  jurisdiction-rule toggling, dispute review/correction, the audit log),
  each behind its own capability-based Gate — Decision 2's own
  requirement that authorization be expressed as abilities, not roles,
  proven at the real HTTP boundary in every case.
- **No role-management UI** — roles are assigned exclusively through the
  `admin:assign-role` console command, per Decision 2's explicit
  exclusion of runtime role/permission editing.
- **No automated, rules-based, or score-driven behavior of any kind** —
  no risk scores, risk rules, automated enforcement, automated
  suspensions, or machine-generated sanctions exist anywhere in this
  phase (Decision 1).
- **No generic financial-correction engine** — dispute administration is
  read-only case review plus a plain-text correction note; no payment
  adjustment, refund, or balance mutation capability exists (Decision
  3/Architecture Refinements §5).
- **No manual KYC review/override, and no Verification & Trust bounded
  context** — an intentional exclusion, not an omission (Decision 7).

## 9. Known limitations and deferred product decisions

### Accepted limitations (by design, not oversights)

- **No automated Fraud & Risk scoring, rules, or account correlation of
  any kind** — deferred beyond MVP; the architectural boundary for a
  future Fraud & Risk context is preserved but nothing reads it yet
  (Decision 1).
- **No forfeiture capability** — business rule 17's suspension half is
  satisfied; forfeiture requires payout/held-funds infrastructure that
  does not exist yet (Decision 4).
- **No standing tier beyond a single suspended/active distinction** — no
  `Restricted`, no `UnderReview` (Decision 4).
- **No creation, deletion, or legal-content editing of restricted
  categories or jurisdiction rules through the admin UI** — operational
  toggle only; new legal restrictions continue to ship through the
  existing engineering/deployment process (Decision 5).
- **No dispute reopening or outcome change of any kind** — `Dispute.Resolved`
  stays exactly as terminal as Phase 6 built it (Decision 3).
- **No photo-evidence viewing in the dispute-review admin surface** —
  only written-statement evidence content renders; resolving a signed URL
  for photo evidence was explicitly scoped out of Sprint 4 as its own,
  separately deferred capability.
- **No manual KYC review/override, and no Verification & Trust bounded
  context** — deferred until that context is introduced in its own,
  separately authorized phase (Decision 7).

### Deferred product decisions

- Whether and when a Fraud & Risk phase is scheduled — the roadmap does
  not currently place one after Phase 9 (Decision 1's own Consequences).
- `claude-mvp-analysis.md` §10.2 items 7–8 (a per-transaction value cap
  while fraud/AML controls mature; whether to block or merely surface
  low-confidence auctions) remain unresolved, not regressed by this
  phase.
- Whether dispute-evidence photo viewing is ever built as its own
  dedicated, deliberately designed admin capability (Architecture
  Refinements §7 anticipates this as a possible future addition, never a
  side effect of generic audit or review visibility).
- Real signed-URL evidence-photo access for the dispute-review surface,
  should that capability ever be approved.

## 10. Phase 9 readiness assessment

Phase 9 (per `docs/roadmap.md`) is Hardening & Launch Readiness. Phase 8
provides:

- A complete, capability-gated administrative surface covering every
  operational lever this project's product decisions have approved so
  far — a natural point from which to layer load-testing, security
  review, and observability without redesigning any admin-facing
  capability.
- A working precedent (Sprints 3–5) for adding a real, minimal HTTP/UI
  surface behind a capability Gate, directly reusable if Phase 9's
  hardening work surfaces a need for additional operational tooling.
- A fully populated audit-display registry proving every existing
  `AuditableAction` event type has been reviewed once for admin-safe
  presentation — a useful checklist to re-run if Phase 9's security
  review adds new auditable events.

**No blockers identified for Phase 9 at the domain level.** Per this
project's established phase-gating discipline, Phase 9 implementation
requires its own separate authorization.
