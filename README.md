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

- **Phase 0 — Foundations**: done (`v0.1.0-foundation`).
- **Phase 1 — Catalog**: done (`v0.2.0-catalog`). The Queues bounded
  context is implemented end-to-end: queue submission, jurisdiction/
  restricted-category gating, admin moderation (approve/reject/publish),
  PostGIS-backed geospatial discovery, and the Inertia/React frontend for
  all of the above. Manually accepted via full browser walkthrough.
- **Phase 2 — Presence & Trust**: not started, pending authorization.

See `docs/roadmap.md` for the full phase plan and
`docs/releases/phase-1-completion-report.md` for the Phase 1 completion
report. See `CLAUDE.md` for the operating instructions used when working
in this repository, and `docs/` for the full product, architecture,
legal, and decision record.

## Running locally

```
docker compose up -d
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed
```

App: `http://localhost:8000`. Vite dev server: `http://localhost:5173`
(runs inside the `node` service).

## Documentation map

- `docs/product/` — product definition, business rules, auction rules,
  verification model, user flows, MVP scope, and the full MVP analysis.
- `docs/architecture/` — architecture overview, localization approach,
  security model.
- `docs/decisions/` — Architecture Decision Records (ADRs).
- `docs/legal/` — jurisdiction requirements and restricted-queue policy.
- `docs/roadmap.md` — development roadmap and phase plan.
