# Release Notes

## Phase 9 — Hardening & Launch Readiness

Five implementation sprints plus a closeout sprint, executing ADR-027's
eight frozen decisions. See `docs/releases/phase-9-completion-report.md`
for the full report and `docs/releases/launch-readiness-report.md` for
the current launch-readiness position — **this phase does not declare
the project launch-ready**; three items remain, each requiring external
input (see that report).

### New capabilities

- **Public auction discovery and live bidding.** `GET /auctions/{id}`
  returns a strictly allowlisted public snapshot (status, current price,
  closing deadline, bid count, minimum next bid) — no seller/bidder
  identity, no proximity or presence data. `POST /auctions/{id}/bids`
  places a bid with idempotent HTTP-retry safety and a per-user rate
  limit. A public Reverb channel pushes the same allowlisted snapshot to
  connected clients after every bid, close, win, expiry, or
  cancellation.
- **Buyer payment-method setup.** A minimal Stripe SetupIntent flow lets
  a buyer save a reusable payment method — raw card data never reaches
  the backend; only a Stripe-issued reference does.
- **Transfer confirmation.** Buyers and sellers can view their transfer,
  retrieve a buyer-only QR/confirmation token, and confirm the handoff
  from either side — geofence-checked, single-use per side, IDOR-safe.
- **Mandatory email verification.** New accounts must verify their
  email before submitting a queue/listing, placing a bid, starting a
  presence session, or configuring a payment method. Signing in,
  requesting/resending the verification email, resetting or changing a
  password, and viewing the dashboard all remain available while
  unverified. Confirming an already-existing transfer obligation is
  deliberately not gated by verification status.
- **Horizon dashboard**, restricted to Administrators via a new
  `horizon.view` capability.
- **`/up` health endpoint** and structured JSON application logging.

### Hardening

- PHP-FPM worker-pool tuning (`pm.max_children` 5→20), found necessary
  by real load testing — a single request could otherwise take 8.6
  seconds under light concurrency.
- A full-scale load test (250 concurrent users, 100 concurrent auctions,
  10 bids/sec sustained 60 seconds) validated correctness and
  domain-invariant behavior under concurrency; see the launch-readiness
  report for why this doesn't yet constitute a production capacity
  guarantee.
- A structured, whole-system security review against every risk named
  in this project's own risk analysis found zero confirmed
  vulnerabilities, plus four small fixes: a Transfer QR-token docblock
  correction, a new rate limit on transfer confirmation, Stripe
  idempotency keys on payment capture/cancellation, and a `postcss`
  dependency patch.
- `composer audit` and Dependabot now cover every package; every package
  now runs its own test suite in CI, not just indirectly through the
  main app.

### Documentation

- `docs/security/security-review.md` / `security-checklist.md`.
- `docs/operations/{load-test-report,capacity-tuning-report,
  synchronous-bottlenecks,observability,runbook}.md`.
- `docs/legal/us-launch-review.md` (skeleton — see launch-readiness
  report).
- `docs/releases/{phase-9-completion-report,launch-readiness-report,
  project-consistency-review}.md` and this file.
- One ADR correction: ADR-014's citation to a nonexistent section was
  fixed to point to where the `PaymentIntent` state model was actually
  finalized.

### Explicitly not in this release

- General HTTP/UI for Ratings or Disputes.
- Seller payout execution.
- Fraud & Risk scoring of any kind.
- Any market beyond the United States.
- ICU locale-data fixes, full chargeback reconciliation, dedicated
  age-verification infrastructure, physical handoff safety content,
  value/AML transaction limits, low-confidence-auction blocking, in-app
  chat.

Each of the above is a deliberate, documented scope decision (ADR-027
§7), not an oversight.

### Test coverage

884 automated tests passing (+5 skipped, pending real Stripe test-mode
credentials) across `apps/web` and all 11 bounded-context packages.
PHPStan/Larastan and Pint clean; `composer audit` and `npm audit` clean;
frontend build clean.

---

## Prior releases

- `v0.9.0-administration` — Phase 8, Administration & Fraud/Risk
  boundary (real HTTP/UI throughout).
- `v0.8.0-ratings-notifications` — Phase 7, Ratings (domain/backend) and
  Notifications (real end-to-end email delivery).
- `v0.7.0-disputes` — Phase 6, Disputes (domain/backend).
- `v0.6.0-transfers` — Phase 5, Transfers (domain/backend).
- `v0.5.0-payments` — Phase 4, Payments (domain/backend).
- `v0.4.0-auctions` — Phase 3, Auctions & Bids (domain/backend).
- `v0.3.0-presence` — Phase 2, Presence & Trust.
- `v0.2.0-catalog` — Phase 1, Catalog.
- `v0.1.0-foundation` — Phase 0, Foundations.

See each phase's own completion report in `docs/releases/` for full
detail.
