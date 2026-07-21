# Development Roadmap

Status legend: `not started` / `proposed` / `in progress` / `done`.

## Phase 0 — Foundations

Status: **proposed** (structure pending founder review before creation).

- Laravel 12 installed at repository root (PHP 8.4 runtime via Docker).
- Docker Compose: app (PHP-FPM), nginx, postgres, redis, reverb, horizon,
  node (Vite dev).
- PostgreSQL and Redis configured (cache, session, and queue connections
  logically separated).
- React + Inertia + Tailwind CSS wired up.
- Pest, Laravel Pint, PHPStan/Larastan configured.
- GitHub Actions CI: lint, static analysis, tests, frontend build,
  translation-parity check, module-boundary architecture test.
- EditorConfig and fully populated `.env.example`.
- English/Spanish localization scaffolding (backend `lang/`, frontend
  i18next), fallback locale `en`.
- Modular monolith folder skeleton (`app/Modules/*`), no models, no
  business logic, no migrations.

Exit criteria: repository boots locally via Docker, CI pipeline is green on
an empty scaffold, module-boundary and translation-parity tests pass with
zero modules implemented.

## Phase 1 — Catalog

Status: not started.

Queues bounded context: queue definitions, `pending/approved/published/
rejected` status lifecycle (ADR-005), jurisdiction/restricted-category
gating engine, queue discovery/search (PostGIS). Administration moderation
queue for pending user-submitted queues ships in this phase, not later.

Exit criteria: a queue can be created (admin-published or user-submitted),
gated by jurisdiction/restricted-category rules, and discovered by
location. No auctions, no money yet.

## Phase 2 — Presence & Trust

Status: not started.

QueuePresence bounded context: GPS capture, in-app evidence capture,
multi-signal confidence scoring engine v1 (rules-based).

Exit criteria: a seller can register presence at a queue and receive a
computed confidence score/tier, with signals independently recorded and
auditable.

## Phase 3 — Auctions & Bids

Status: not started.

Auction state machine (§7.1 of the MVP analysis), Reverb-backed real-time
bidding, concurrency-safe bid placement, anti-sniping soft-close.

Exit criteria: an auction can run end-to-end (open → closing → winning bid
selected) under simulated concurrent bidding with correct, tested
row-locking behavior. No payments yet.

## Phase 4 — Payments

Status: not started. Blocked until remaining open questions in
`docs/product/claude-mvp-analysis.md` §10.2 (exact fee %, launch market,
KYC tier, protection-period duration) are answered.

Stripe Connect seller onboarding, authorize-now/capture-at-transfer escrow
(ADR-004), platform fee (ADR-006), webhook idempotency ledger,
re-authorization flow for transfer windows nearing expiry.

Exit criteria: a winning bid can be authorized, captured on transfer
confirmation, and paid out to a verified seller, entirely in Stripe test
mode, with idempotent webhook handling proven under replay.

## Phase 5 — Transfers

Status: not started.

QR issuance/validation, handoff confirmation with geolocation cross-check.

## Phase 6 — Disputes & Refunds

Status: not started.

Case management, evidence aggregation (read-only across contexts), refund
orchestration delegated to Payments.

## Phase 7 — Ratings & Notifications

Status: not started.

Cross-channel notification templates (locale-aware), ratings.

## Phase 8 — Administration & Fraud/Risk v1

Status: not started.

Manual KYC/dispute overrides, rules-based risk scoring (Fraud & Risk
context), restricted-category admin UI.

## Phase 9 — Hardening & Launch Readiness

Status: not started.

Load-testing Reverb/bidding under concurrency, security review,
per-jurisdiction legal sign-off for each launch market
(`docs/legal/jurisdiction-requirements.md`), observability, Horizon
capacity tuning.

---

Every phase ships with: automated tests for that phase's domain rules,
translation-key parity checks, and domain-event contract tests — not
retrofitted at the end.
