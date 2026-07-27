# RowBuddy Development Instructions

## Product

RowBuddy is a multilingual marketplace where verified users may auction
their physical position in eligible queues.

The platform does not grant ownership of a queue position and does not
guarantee that a business or organizer will accept a transfer. RowBuddy
positions itself legally and rhetorically as a **paid line-standing /
time-brokering service** — compensation for time and effort already spent
waiting, plus a verified handoff — never as the sale of a position itself.
See `docs/decisions/003-line-standing-service-framing.md`.

The system must block restricted, unsafe, or legally sensitive queues.

## Initial locales

- English: en
- Spanish: es

English is the fallback locale.

Never hardcode user-facing strings.

All interface text, validation messages, notifications, and emails must
use translation keys.

Language, country, currency, and timezone are separate user preferences.

## Technology

- Laravel 12
- PHP 8.4
- PostgreSQL
- Redis
- Laravel Reverb
- Laravel Horizon
- React
- Inertia
- Tailwind CSS
- i18next
- Stripe Connect
- Pest
- Docker

## Architecture

Use a modular monolith. Modules communicate through domain events and
explicit application-service interfaces — never through direct cross-module
Eloquent model access. Module boundaries must be enforced by an automated
architecture test, not just convention.

Main modules:

- Identity
- Localization
- Queues
- QueuePresence
- Verification
- Auctions
- Bids
- Payments
- Transfers
- Disputes
- Ratings
- Notifications
- Administration
- Audit
- Fraud & Risk

Do not create microservices unless explicitly requested.

## Core business rules

- A seller must be physically present before publishing a position.
- GPS alone is not sufficient verification.
- A seller cannot auction the same position more than once.
- A seller must remain near the queue while an auction is active.
- All accepted bids are immutable.
- Prices and fees must be calculated by the server.
- Payment must remain protected until transfer confirmation.
- A transferred position cannot be transferred again in the MVP.
- Restricted queue categories must be blocked.
- Every financial and verification action must create an audit record.
- The platform must never store card details.
- User-uploaded evidence must be private by default.

## Approved architectural decisions

The following are resolved and binding for implementation (see the
corresponding ADR for full rationale and consequences):

- **Legal framing**: paid line-standing service, not position sale
  (`003-line-standing-service-framing.md`).
- **Escrow**: authorize at bid-win, capture at transfer confirmation — no
  intermediate platform-held balance. Auction/transfer windows are bounded
  by card authorization validity (~5–7 days)
  (`004-escrow-authorize-then-capture.md`).
- **Queue authorship**: hybrid — admin/partner queues publish directly;
  user-submitted queues require admin approval before any auction can be
  created against them (`005-hybrid-queue-authorship.md`).
- **Fee model**: percentage of winning bid, charged to the buyer on top of
  the bid; seller receives the full winning bid minus standard payment
  processing costs (`006-fee-model-buyer-side-percentage.md`).

Open questions that remain unresolved are tracked in
`docs/product/claude-mvp-analysis.md` §10.2.

## Localization rules

- Never hardcode user-visible text.
- Add English and Spanish translations for every new key.
- Tests must confirm translation keys exist in both languages.
- Do not store translated system content in a single text column.
- User-generated content must store its original locale.
- Use locale-aware date, time, and currency formatting.
- Never assume USD based only on the selected language.

## Security rules

- Use authorization policies.
- Validate file type and size.
- Remove metadata from uploaded images where appropriate.
- Protect evidence with signed or authenticated URLs.
- Use database transactions for bids, payments, and transfers.
- Use idempotency for payment webhooks.
- Use row-level locks where race conditions are possible.
- Never expose private evidence publicly.
- Never commit secrets.

## Development workflow

For each task:

1. Read the existing documentation and code.
2. Explain ambiguities and risks.
3. Propose a small implementation plan.
4. Modify only files required for the task.
5. Add automated tests.
6. Run the complete relevant test suite.
7. Report changed files, test results, and pending risks.

## Repository status

Phase 0 (Foundations), Phase 1 (Catalog), and Phase 2 (Presence & Trust)
are complete and formally accepted — tagged `v0.1.0-foundation`,
`v0.2.0-catalog`, and `v0.3.0-presence`. Phase 3 (Auctions & Bids) is now
in progress, authorized 2026-09-02 after ADR-009 and ADR-010 resolved the
Auctions–Presence contract and the minimum confidence tier; Sprints 1–4
(`Auction` aggregate scaffold, persistence, the ADR-009/ADR-010
verification contract and Evidence Verified gate, and the ADR-011
live-proximity enforcement policy) are complete. See `docs/roadmap.md`
for the phase plan and sprint progress, and
`docs/releases/phase-1-completion-report.md` /
`docs/releases/phase-2-completion-report.md` for the completion reports of
the closed phases.
