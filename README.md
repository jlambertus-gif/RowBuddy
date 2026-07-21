# RowBuddy

RowBuddy is a multilingual marketplace for auctioning verified positions in eligible physical queues.

## Initial scope

- Languages: English and Spanish
- Architecture: Modular monolith
- Backend: Laravel 12 / PHP 8.4
- Frontend: React + Inertia
- Database: PostgreSQL
- Cache and locks: Redis
- Real-time: Laravel Reverb
- Queues: Laravel Horizon
- Payments: Stripe Connect
- Tests: Pest
- Local environment: Docker

## Current status

This repository currently contains product, architecture, and legal
documentation only. The Phase 0 (foundation) implementation plan has been
proposed and is pending structural review. Application code, migrations,
models, and controllers must not be generated until that review is
approved.

See `CLAUDE.md` for the operating instructions used when working in this
repository, and `docs/` for the full product, architecture, legal, and
decision record.

## Documentation map

- `docs/product/` — product definition, business rules, auction rules,
  verification model, user flows, MVP scope, and the full MVP analysis.
- `docs/architecture/` — architecture overview, localization approach,
  security model.
- `docs/decisions/` — Architecture Decision Records (ADRs).
- `docs/legal/` — jurisdiction requirements and restricted-queue policy.
- `docs/roadmap.md` — development roadmap and phase plan.
