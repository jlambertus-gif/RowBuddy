# ADR 005: Hybrid Queue Authorship

## Status

Approved — 2026-07-21.

## Decision

Queues may originate from two sources:

- **Admin/partner-curated queues** — pre-vetted by RowBuddy or a partner,
  publish directly.
- **User-submitted queues** — any user may submit a queue for a physical
  line, but it enters a `pending` status and cannot be used to create an
  auction until an administrator approves it.

## Rationale

Admin-only queue creation minimizes the fraud/legal surface but limits
coverage and growth. Fully open queue creation maximizes coverage but
creates unmanageable moderation and restricted-category risk for an MVP.
The hybrid model captures most of the growth benefit while keeping a
human review gate between "a queue exists in the system" and "money can be
put at risk against it."

## Consequences

- The Queues bounded context needs an explicit status lifecycle
  (`pending → approved → published` / `rejected`), not just a flat
  `published` flag.
- An Administration-facing moderation queue is required starting in
  Phase 1 (catalog), not deferred to the later administration/trust-safety
  phase, since user-submitted queues are in MVP scope from the start.
- Jurisdiction and restricted-category rule evaluation
  (`docs/legal/restricted-queues.md`,
  `docs/legal/jurisdiction-requirements.md`) must run at approval time, not
  only at auction-creation time.
- The approval SLA (how long a pending queue may wait for review) is an
  open product question — see `docs/product/claude-mvp-analysis.md` §10.2.
