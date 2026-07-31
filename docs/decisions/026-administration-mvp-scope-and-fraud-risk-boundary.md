# ADR 026: Administration MVP Scope and the Fraud & Risk Boundary

## Status

Accepted (2026-07-30). All six Phase 8 product decisions are frozen and
recorded below, including an explicit, intentional exclusion of manual
KYC review/override capability — confirmed as deliberate scope, not an
omission (see Decision 7 and Explicitly Out of Scope).

## Context

`claude-mvp-analysis.md` §4 describes Administration and Fraud & Risk as
two genuinely distinct bounded contexts sharing one phase number:
Administration is "a thin orchestration layer over other contexts'
application services" (moderation, restricted-category management,
manual KYC/dispute overrides, feature flags); Fraud & Risk is "a
cross-cutting scoring context that consumes events... to compute
account-level and transaction-level risk scores," judging accounts and
patterns over time rather than one case at a time. `mvp-scope.md`
explicitly lists Administration and Audit events as included in MVP, but
never mentions Fraud & Risk in either its Included or Excluded list —
the same scope ambiguity Notifications carried into Phase 7 before that
phase's own Decision 1 resolved it.

Every prior phase that touched this boundary drew it the same way:
ADR-021 §6 ("any future automated or rules-based decision-making belongs
exclusively to the future Fraud & Risk bounded context... Disputes
resolves one case at a time, on a human's explicit say-so"), ADR-018
§4, ADR-014 §6, and ADR-024/ADR-025's own Consequences sections all
independently deferred any automated, score-driven, or cross-account
correlation behavior to "a future Fraud & Risk phase (Phase 8)," without
ever building the scoring mechanism itself. This decision resolves
whether Phase 8 is the phase that finally builds it.

## Decision

### 1. Phase 8 MVP is Administration-focused; automated Fraud & Risk scoring is deferred beyond MVP

Phase 8 delivers **Administration capabilities only**: roles and
permissions, manual moderation, manual dispute administration,
restricted-category administration, manual account actions, and audit
visibility. It does **not** deliver any part of the Fraud & Risk scoring
engine — no `risk_scores`/`risk_rules` tables, no automated enforcement,
no automated suspensions, no automated dispute resolution, no automated
bidding restrictions, and no machine-generated account sanctions of any
kind.

This is a direct extension of the manual-only precedent Disputes already
established (ADR-021 §6/§7, Phase 6 Decision 7): every consequential
action in Phase 8 is an explicit human administrator's decision, never a
computed score's. It also matches this project's standing discipline of
resolving the smallest concrete, already-motivated scope first (the same
reasoning behind Notifications' eight-event narrowing and Ratings'
rejection of multi-dimensional scoring) rather than building a scoring
engine now with no real production data to calibrate it against, and
before Verification & Trust (the bounded context most of Fraud & Risk's
realistic signals would depend on) has ever been built.

**Phase 8 must nonetheless preserve the architectural boundary for a
future Fraud & Risk context**, by ensuring the platform events and data
that context will eventually consume remain independently readable,
without requiring any architectural change to the bounded contexts
completed in Phases 1–7. Concretely: no Phase 8 capability may alter
how Auctions, Bids, Payments, Transfers, Disputes, Ratings, or
Notifications publish their own domain events, and no Phase 8 read
access to those events/aggregates may be implemented in a way a future
Fraud & Risk context could not equally reuse or replace independently
(the same "consumer owns the port" discipline already proven to scale
across Ratings and Notifications reading the same upstream data with no
coupling between them).

This leaves `claude-mvp-analysis.md` §10.2 items 7 (a per-transaction
value cap while fraud/AML controls mature) and 8 (blocking low-confidence
auctions outright vs. surfacing them) explicitly unresolved — not
regressed, simply not advanced by this phase.

### 2. A small, fixed, closed set of administrative roles, authorized via Gates/Policies

Administrative roles are represented as a closed `AdminRole` enum —
fixed in code, extensible later only by adding a new case, never at
runtime. No role-management UI exists in the MVP: no runtime role
creation, no runtime permission editing. This mirrors the same "small
closed enum, fixed in code" shape already proven for `NotificationType`
(ADR-025 §6) and `DisputeResolutionOutcome` (ADR-021) — extensible by
adding a case, never by building a configuration system nobody has
asked to configure.

Roles are assigned to users through persistent storage (a real column/
table, not the bare `is_admin` boolean it replaces). Authorization
itself remains **capability-based**, expressed through Laravel Gates/
Policies, not embedded directly in the `AdminRole` enum itself — a role
*grants* one or more capabilities; the capability, not the role, is
what any given action actually checks (mirroring `queues.moderate`'s
existing `Gate::define()` shape, generalized). This deliberately allows
multiple roles to share the same underlying capability without
duplicating authorization logic per role, and keeps every individual
admin action's authorization check readable as "can this user do X,"
never "is this user role Y."

Dynamic RBAC (admin-configurable roles/permissions at runtime) is
explicitly deferred beyond MVP — nothing in `mvp-scope.md` or
`claude-mvp-analysis.md` motivates it, and building it now would be the
same class of speculative complexity this project has declined
elsewhere (no fee-specific behavior invented ahead of need, no
multi-dimensional rating scores nobody asked for).

### 3. Dispute administration excludes reopening; `Dispute.Resolved` stays terminal

`Dispute.Resolved` remains exactly as terminal as ADR-021 §5 built it —
no reopening, no appeal, no retroactive change to a closed dispute's
own resolution. Administrators may review any dispute, its evidence,
and its full history, but "manual dispute administration" grants no
capability to reopen or overwrite a past resolution.

When an administrator later determines a resolved dispute's outcome was
wrong, the correction is a **new, separate administrative action** —
never a second resolution of the same `Dispute`. Any financial
correction is modeled as a distinct administrative payment/account
action (Decision 4 defines this further), not a second refund/release
triggered through `Dispute`'s own resolution path. Every such
correction carries its own audit trail, independent of and additional
to the original dispute's own audit record — the correction is a fact
about *itself*, not an edit to history.

This deliberately avoids reopening the harder problem a true reopening
capability would require — reconciling money that already moved based
on the first resolution (a second refund, a clawback, a net adjustment)
— a problem no current decision has sized, in favor of the same
"admin error is an out-of-band administrative correction, not a
modeled workflow" posture Phase 6 Decision 4 already established for
`Dispute` itself. It also preserves `Dispute` completely unchanged from
its Phase 6-accepted state machine.

### 4. A single, reversible account-suspension state; forfeiture explicitly deferred

Phase 8 implements one account-standing consequence: a manual,
admin-triggered, reversible **suspension**. Suspension and reinstatement
are each their own explicit administrative action — never automated,
never triggered by any score, rule, or system process. Every suspension
and reinstatement action requires an explicit reason and records the
acting administrator, the target account, the reason, a timestamp, and
the resulting account state, mirroring Decision 3's audit-trail
discipline for administrative corrections generally.

A suspended account may still authenticate — solely to reach any
required account-status or support experience — but may not perform any
new business action. At minimum, suspension blocks: placing bids;
submitting new listings or queue entries; submitting ratings; and
initiating any other new transactional activity the application
currently supports. Suspension is deliberately non-destructive: it never
erases data and never automatically cancels an existing obligation —
any in-flight transfer, payment, dispute, refund, or administrative
review continues through its own domain workflow entirely unaffected by
the suspension itself.

**No additional standing tiers** (e.g., `Restricted`, `UnderReview`)
are included in MVP — a single suspended/active distinction is the
entire model, consistent with this project's preference for the
smallest capability that delivers a real, usable consequence.

**"Forfeiture" (business rule 17's other half) is explicitly deferred
beyond Phase 8.** This platform has no payout-execution mechanism and no
held-funds/escrow-pending-forfeiture model of any kind — seller payout
execution itself remains unbuilt since Phase 4/5's own explicit
deferral. Implementing forfeiture would require designing what there
actually is to forfeit against infrastructure that does not exist yet;
attempting it now would mean inventing a consequence with no real funds
flow behind it. Suspension, by contrast, is fully self-contained and
requires no such infrastructure, so it proceeds in Phase 8 regardless of
forfeiture's deferral.

### 5. Restricted-category administration is operational only — rule content stays immutable through the UI

Administrators can view every restricted category and jurisdiction
rule, and can activate or deactivate an *existing* row — each such
change requiring a mandatory reason and recording the acting
administrator, timestamp, previous state, new state, and reason,
mirroring Decision 4's suspension-action audit shape exactly. The admin
UI grants no ability to create a new restricted category, delete one,
edit an existing rule's legal definition/criteria, create a new
jurisdiction rule, or edit jurisdiction-rule logic.

This keeps the actual legal content of what is restricted, where, behind
the same engineering-and-legal-review process `jurisdiction-
requirements.md` already implies for launching in any market — Phase 8
adds a fast operational lever (toggle a rule on or off) without letting
that lever also originate new legal determinations. New or modified
legal restrictions continue to ship exactly as they do today: through
the existing deployment process, not through this admin capability.

### 6. Audit visibility uses an explicit per-event admin-display allowlist, never raw payload pass-through

Every auditable event type must explicitly define which of its fields
are visible in the administration UI; any field not explicitly exposed
is not displayed, regardless of what the underlying audit record
stores. This mirrors ADR-025 §9's identical discipline for notification
content exactly — an explicit per-type allowlist, never an unrestricted
serialization of whatever a payload happens to contain.

The audit store itself remains the system of record and continues to
retain each event's complete payload unchanged — this decision governs
**presentation only**. The administration UI is a presentation layer
over that data, never a direct serialization of it; nothing about how
`AuditableAction`/the audit sink persists data changes as a result of
this decision.

Sensitive information — private evidence references, internal
infrastructure identifiers, secrets, tokens, credentials, storage
paths, or other implementation details — must never be displayed unless
a dedicated administrative workflow explicitly requires it (and even
then, only through that workflow's own deliberate design, not as a
side effect of generic audit visibility).

A new auditable event type must define its administrative display
contract **before** it becomes visible in the audit interface — adding
a new `AuditableAction` implementation anywhere in the codebase does
not automatically make it admin-visible; visibility is an explicit,
separate choice for the Administration module to make about each event
type, one at a time.

### 7. Administration operates only on data already produced by implemented bounded contexts; KYC/Verification & Trust is intentionally excluded

**Administration may only operate on data that already exists within
the bounded contexts implemented in Phases 1–7.** This is an explicit
architectural principle, not merely a consequence of Decisions 1–6:
Administration is deliberately a thin orchestration layer over other
contexts' own data and application services (per its own definition in
`claude-mvp-analysis.md` §4), never a place where a new kind of
first-party data is introduced to support an administrative workflow
that has no underlying domain source yet.

Manual KYC review, identity-verification workflows, document review, and
KYC overrides are therefore **intentionally excluded** from Phase 8 —
not an oversight discovered at ADR finalization, but a direct
consequence of this principle: no Verification & Trust bounded context
exists to produce the identity-verification submissions an admin would
review or override. Building KYC-adjacent admin tooling now would mean
inventing the underlying data model *as part of* an admin feature,
inverting the dependency this principle establishes. These capabilities
remain deferred until a dedicated Verification & Trust bounded context
is introduced, in its own phase, with its own product decisions.

## Consequences

- `packages/Administration` (or equivalent) gains an `AdminRole` enum
  and a persisted role assignment (replacing `users.is_admin`), plus one
  Gate/Policy per capability rather than per role — existing gates such
  as `queues.moderate` will need to be re-expressed in terms of the new
  capability model rather than the raw boolean, a mechanical migration
  addressed in a later sprint, not a further product decision.
- No role-management UI, no runtime role/permission editing, and no
  `roles`/`permissions` database tables beyond the fixed enum and its
  assignment column/table.
- `Dispute` gains no new method, state, or transition from Phase 8 — an
  administrative correction is implemented entirely as a new
  Administration-owned action/record, never a change to
  `packages/Disputes`' own aggregate or its migrations.
- Every administrative correction persists its own audit trail,
  distinct from (and never overwriting) the original `DisputeResolved`
  event/record it corrects.
- `users` (or an Administration-owned equivalent) gains a persisted
  suspended/active distinction plus a suspension-action log (actor,
  target, reason, timestamp, resulting state) — a single boolean-shaped
  state, not a multi-tier standing model.
- Every existing entry point for placing a bid, submitting a queue/
  listing, or submitting a rating gains a suspension check; no other
  module's own aggregate or state machine changes to accommodate this.
- No payout/held-funds/forfeiture capability is introduced in Phase 8;
  this is a named, accepted limitation, not a silent gap.
- `packages/Queues`' `restricted_categories`/`jurisdiction_rules` tables
  gain an active/inactive toggle and an accompanying action log (actor,
  timestamp, previous/new state, reason); no new column expresses legal
  content, and no admin-facing create/edit/delete path exists for the
  rules' own definitions.
- Every existing `AuditableAction` implementation (roughly 20+ event
  types across Auctions, Bids, Payments, Transfers, Disputes, Ratings,
  Queues, and QueuePresence) needs its own admin-display definition
  before it appears anywhere in the audit interface — this is real,
  proportional Phase 8 sprint scope, not a one-time setup cost.
- No change to `AuditableAction`, the audit sink, or any `audit_events`
  persistence — this decision is additive presentation-layer scope
  only.
- No `risk_scores`, `risk_rules`, or any scoring-engine table is created
  in Phase 8.
- Business rule 17 ("fraudulent evidence may result in suspension and
  forfeiture according to platform policy") is satisfied only by its
  suspension half (Decision 4) — always an explicit admin action, never
  score-triggered. Its forfeiture half is explicitly deferred beyond
  Phase 8 (Decision 4), pending payout/held-funds infrastructure that
  does not exist yet.
- Verification & Trust (the KYC-adjacent bounded context) is not built
  in Phase 8, and no Phase 8 capability depends on it — "manual KYC
  overrides," named in `claude-mvp-analysis.md`'s original Administration
  description and the roadmap's own Phase 8 line, is intentionally
  excluded per Decision 7, not an oversight (see Explicitly Out of
  Scope).
- Every Phase 8 capability reads or acts on data already produced by an
  existing bounded context (Auctions, Bids, Payments, Transfers,
  Disputes, Ratings, Notifications, Queues, QueuePresence, Identity) —
  Administration introduces no new first-party domain data source of
  its own beyond its own operational records (role assignments,
  suspension/toggle action logs, admin-display definitions).
- A future Fraud & Risk phase, whenever it is scheduled, reads Auctions/
  Bids/Payments/Transfers/Disputes/Ratings/Notifications events and
  Administration's own records independently, through its own
  read ports — mirroring exactly how Ratings and Notifications each
  independently read Transfers today with zero dependency on each
  other.
- The roadmap does not currently schedule a phase after Phase 9
  (Hardening & Launch Readiness) for Fraud & Risk — this decision does
  not resolve when or whether that gap is filled before launch, only
  that Phase 8 itself does not fill it.

## Architecture Refinements (recorded before Sprint 1)

Phase 8 is, alongside Notifications (ADR-025 §1/§8), the second
exception to this project's domain/backend-only closure bar — it
requires real administrative HTTP endpoints and UI surfaces for the
capabilities Decisions 2–6 describe. Before any implementation began,
the following refinements were applied to the architecture the accepted
decisions imply:

1. **Roles stay separate from capability authorization.** `AdminRole`
   is a closed enum representing role *identity* only — it must not
   embed authorization decisions itself (no `AdminRole::capabilities()`
   comparing roles directly). A separate, code-defined capability map
   or authorization service is consumed by Laravel Gates/Policies;
   application code authorizes *abilities* ("can this user do X"),
   never roles ("is this user role Y") — the exact discipline Decision
   2 already stated, made explicit at the implementation level too.

2. **Role assignment is one-role-per-user for MVP, migrated without an
   access gap.** `admin_role_assignments` enforces a unique constraint
   on `user_id`. Migrating off `users.is_admin` must: backfill every
   existing administrator into the highest administrative role;
   re-point `queues.moderate` at the new capability model without a
   window where existing admins lose access; remove the `is_admin`
   column only after every consumer has moved; and use a documented,
   engineering-controlled mechanism to assign roles (console
   command/seeder), never a UI — consistent with Decision 2's explicit
   exclusion of any role-management UI.

3. **`admin_actions` is typed and fail-closed, and is not a second Audit
   subsystem.** Its `action` column is a closed
   `AdministrativeActionType` enum, not an arbitrary string. Every row
   records the administrator, action type, target type/id, a mandatory
   reason, previous/resulting state where applicable, and a timestamp.
   `previous_state`/`new_state` use explicit per-action-type schemas or
   allowlists — never an unrestricted serialization of a domain object,
   mirroring ADR-025 §9's identical discipline for notification content
   and Decision 6's for audit visibility. The platform-wide Audit sink
   (`AuditableAction`) remains the one immutable, complete record of
   every domain event; `admin_actions` is the separate, narrower
   operational record of deliberate administrative decisions only, and
   must never attempt to replace or duplicate it.

4. **Suspension enforcement is centralized, not scattered.** A single
   account-standing policy or port is consumed by every relevant
   application entry point — no controller or service checks
   `suspended_at` directly. Suspension blocks new transactional
   activity (bidding, new queue/listing submission, rating submission,
   and any other newly initiated commercial action) but must never be
   implemented as blanket middleware that also blocks authentication,
   viewing account status, accessing support, reviewing existing
   transfers/payments/disputes, or completing/responding to an existing
   obligation — unless a separately approved domain rule already
   prohibits that specific action. Existing workflows are never
   cancelled or mutated automatically as a side effect of suspension.

5. **No generic financial-correction engine.** The dispute
   administration surface is read-only case/evidence/history review.
   `Dispute.Resolved` remains immutable and terminal (Decision 3).
   Phase 8 may record a separate administrative correction decision,
   note, or referral — but must not implement a generic payment
   adjustment, refund, clawback, or balance mutation. If a real
   financial correction capability is ever needed, that requires its
   own, separately frozen financial-correction design before any
   implementation — Sprint 4 does not include one.

6. **Bounded-context ownership is preserved through explicit ports, not
   direct table access.** Administration authorizes and orchestrates;
   Queues and Disputes remain responsible for their own rule-state
   mutation and invariants. Restricted categories already have an
   `active` column supporting exactly the toggle Decision 5 describes.
   **Jurisdiction rules do not** — `jurisdiction_rules` has no `active`
   field or equivalent invariant separate from its own legal content
   (`permitted`) and legal-effectiveness window
   (`effective_from`/`effective_to`); reusing either for an admin
   on/off toggle would mean editing the rule's actual legal
   effectiveness through the admin UI, which Decision 5 already
   prohibits. The Queues-domain change this requires (an
   `active`-equivalent column, additive and owned by `packages/Queues`)
   will be presented as its own proposal before Sprint 3, not assumed.

7. **Audit visibility is presentation-only, code-defined, and
   fail-closed.** The display registry is per-event-type and
   allowlist-based; an event type with no registered definition simply
   does not appear. Private evidence, storage references, internal
   infrastructure identifiers, credentials, tokens, Stripe identifiers,
   and unrestricted raw payloads must never render through the generic
   audit UI — dedicated evidence access remains solely within the
   Disputes administration workflow (item 4 above), never surfaced
   generically through audit visibility.

**Approved sprint sequence**: (1) package, roles, persistence,
capability authorization, migration off `is_admin`; (2) typed
`admin_actions` and account suspension; (3) restricted-category and
jurisdiction-rule administration; (4) read-only dispute administration
and bounded corrective records; (5) audit display registry and
administrative audit UI; (6) full validation, documentation, completion
report, and release. Each sprint requires explicit review and approval
before the next begins.

## Explicitly Out of Scope

Consolidating individual notes above into one list, for the same reason
ADR-021 §"Explicitly Out of Scope" did — so the boundary reads as
deliberate scope in any future audit, not an oversight:

- Any automated, rules-based, or score-driven behavior of any kind
  (Decision 1) — no risk scores, risk rules, automated enforcement,
  automated suspensions, automated dispute resolution, automated
  bidding restrictions, or machine-generated sanctions.
- Dynamic, admin-configurable roles or permissions at runtime
  (Decision 2) — roles are a closed enum, fixed in code.
- Reopening or altering a resolved dispute's outcome (Decision 3) —
  `Dispute.Resolved` stays terminal; corrections are new, separate
  actions.
- Any account-standing tier beyond a single suspended/active
  distinction (Decision 4) — no `Restricted`, no `UnderReview`.
- Forfeiture of funds, or any payout/held-funds mechanism (Decision 4)
  — no such infrastructure exists yet to implement it against.
- Creating, deleting, or editing the legal content of any restricted
  category or jurisdiction rule through the admin UI (Decision 5) —
  operational toggle only; new legal restrictions ship through the
  existing engineering/deployment process.
- Raw audit-payload display of any kind (Decision 6) — every event
  type requires its own explicit admin-display definition first.
- **Manual KYC overrides and any part of the Verification & Trust
  bounded context** (Decision 7) — an intentional exclusion, not an
  omission: named in this project's earlier product documents as part
  of "Administration," but deliberately deferred until a dedicated
  Verification & Trust bounded context is introduced in its own phase.
  No identity-verification submission model, review queue, or
  KYC-specific admin action exists in Phase 8's approved scope.

## References

- `docs/product/claude-mvp-analysis.md` §4 (Administration and Fraud &
  Risk bounded-context descriptions), §10.2 items 7–8 (left unresolved)
- ADR-021 §6/§7 (Disputes' manual-only precedent, extended here to all
  of Administration)
- ADR-018 §4, ADR-014 §6 (prior deferrals to "a future Phase 8")
- ADR-024/ADR-025 Consequences (both anticipate a future Fraud & Risk
  context reading their data independently)
- ADR-025 §9 (the notification-content allowlist discipline Decision 6
  mirrors exactly for audit visibility)
- Phase 6 Decision 4 (the "admin error is an out-of-band correction, not
  a modeled workflow" precedent Decision 3 extends)
- `docs/product/jurisdiction-requirements.md` (the per-market legal
  review process Decision 5 keeps restricted-category legal content
  behind)
- `docs/product/business-rules.md` item 17 (the suspension/forfeiture
  rule Decision 4 partially satisfies)
- `AppServiceProvider::boot()`'s existing `Gate::define('queues.moderate', ...)`
  (the capability-based authorization shape Decision 2 generalizes)
- Phase 8 architecture review (this conversation)
