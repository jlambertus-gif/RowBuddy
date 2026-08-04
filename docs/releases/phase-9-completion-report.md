# Phase 9 (Hardening & Launch Readiness) — Completion Report

Status: **Domain/backend/documentation work complete. Not yet formally
accepted as launch-ready** — three items resolved during this phase are
external inputs this implementation cannot supply on its own (real
Stripe test-mode credentials, US legal-review determinations, and the
`main` branch/release merge, each requiring your own action, not code).
This report documents what Phase 9 built and verified, and states
plainly what remains before "launch-ready" is true rather than declared.

## 1. Executive summary

Phase 9 executed ADR-027's eight frozen decisions across five sprints:
CI/security-tooling foundations; a minimum Auctions/Bids HTTP+Reverb
surface; a minimum BuyerPaymentMethod+Transfers HTTP surface;
evidence-driven load testing and PHP-FPM/Horizon tuning; and a
structured, whole-system security review that also implemented mandatory
email verification per your own added decision mid-sprint. Sprint 6
(this report) closes out documentation, verifies every ADR-027
commitment against the actual codebase, corrects one documentation
inconsistency found during that verification, and states the project's
real launch-readiness position.

Governing principle throughout: "necessity, not completeness" (ADR-027
Decision 0) — no delivery surface was added beyond what one of the five
roadmap responsibilities (load-testing, security review, legal sign-off,
observability, Horizon tuning) required. Ratings and Disputes still have
no general HTTP/UI surface; seller payout execution and Fraud & Risk
still do not exist in any form — exactly as every prior phase's own
scope decisions already committed to.

**Total automated test count at Phase 9's close: 884 passing (+5
skipped)** across `apps/web` (220) and all 11 packages (`shared-kernel`
31, `Queues` 101, `QueuePresence` 81, `Auctions` 66, `Bids` 44,
`Payments` 105+5 skipped, `Transfers` 62, `Disputes` 47, `Ratings` 43,
`Notifications` 35, `Administration` 49) — up from Phase 8's close.
PHPStan/Larastan clean across every package and `apps/web`. Pint clean
for every file any Phase 9 sprint created or substantively edited (a
small number of pre-existing, unrelated Fortify-scaffold files carry a
pre-existing missing-`declare(strict_types=1)` gap, left untouched by
explicit precedent set in Sprint 4 and reconfirmed in Sprint 5 — not a
Phase 9 regression).

## 2. Sprint-by-sprint deliverables

| Sprint | Commit | Delivered |
|---|---|---|
| 1 | `24e695c` | CI hardening (`composer audit`, Dependabot, every package running its own test suite in CI, not just indirectly through `apps/web`); structured JSON application logging (Monolog `JsonFormatter`); `/up` health endpoint; the `horizon.view` `AdminCapability` gating Horizon's dashboard; the US legal-review skeleton (`docs/legal/us-launch-review.md`), structurally complete, every item explicitly "Awaiting determination." |
| 2 | `2d26078` | The minimum Auctions/Bids HTTP+Reverb surface: `AuctionPublicSnapshotAssembler`'s strict allowlist (no seller/bidder identity, no proximity/presence data), idempotent rate-limited bid placement (`bidder_id`+`Idempotency-Key` composite claim, DB-enforced), a public Reverb channel broadcasting only the same six allowlisted fields after six specific domain events. |
| 3 | `53c138f` | The minimum `BuyerPaymentMethod`+Transfers HTTP surface: Stripe SetupIntent-based payment-method acquisition (raw card data never reaches Laravel), real `AuctionWon`→authorization and `PaymentAuthorized`→transfer-initiation wiring, a Transfers IDOR fix, QR-code delivery via a TTL-bound cache read. |
| 4 | `57e20d6` | Full-scale (250-user/100-auction/10-bids-per-sec) load test against Decision 3's own targets; PHP-FPM worker-pool tuning (`pm.max_children` 5→20, evidence-driven); a `guzzlehttp/guzzle` CVE patch discovered by the sprint's own `composer audit` gate; Horizon left unchanged (no contention demonstrated) — `docs/operations/{load-test-report,capacity-tuning-report,synchronous-bottlenecks}.md`. |
| 5 | `ca2b706` | Structured whole-system security review (`docs/security/{security-review,security-checklist}.md`) against the five risks named in `claude-mvp-analysis.md` §3.2 — zero confirmed vulnerabilities; four small evidence-backed fixes (QR-token docblock, transfer-confirmation rate limit, Stripe capture/cancel idempotency keys, a `postcss` CVE patch); mandatory email verification implemented end-to-end per your own added decision, gating exactly five new-transactional-activity routes, deliberately leaving existing-obligation routes (transfer confirmation, presence-session continuation) ungated. |
| 6 | *(this sprint, held uncommitted pending your review)* | Documentation finalization (`docs/operations/observability.md`, `docs/operations/runbook.md` — previously missing); a real ADR cross-reference bug found and corrected (ADR-014's dangling citation to a nonexistent "ADR-004/§7.3"); a stale `docker-compose.yml` comment corrected; this completion report, `docs/releases/RELEASE_NOTES.md`, `docs/releases/launch-readiness-report.md`, and `docs/releases/project-consistency-review.md`. |

## 3. ADRs

No new ADR file was created during Phase 9 — every Phase 9-specific
decision lives inside ADR-027 itself (`docs/decisions/027-phase-9-launch-readiness-scope.md`),
including its own "Architecture Refinements," "Sprint 2 approved
implementation decisions," "Sprint 3 approved implementation decisions,"
and "Sprint 4 note" subsections, recorded incrementally as each sprint's
own implementation surfaced judgment calls ADR-027's original text
hadn't yet resolved. All 26 prior ADRs (001–026) remain unchanged and
binding.

One correction was made to an existing ADR during this sprint's
cross-reference verification: ADR-014 §2 cited "ADR-004/§7.3" for the
`PaymentIntent` lifecycle's exact state set — ADR-004 has no numbered
sections at all, and the cited state names (`held`/`released`) were
never actually implemented (ADR-019 §2 explicitly declined to model
them). Corrected to point to where the real state set was actually
finalized (ADR-019 §2's `Captured`/`CaptureFailed`/`Cancelled`, ADR-022's
later `Refunded`), with a note explaining why the original citation was
wrong rather than silently rewriting history. See
`docs/releases/project-consistency-review.md` for the full verification
this correction came from.

## 4. Architecture changes

- A minimum, strictly allowlisted public HTTP+Reverb surface for
  Auctions/Bids — the first HTTP reachability either package has ever
  had.
- A minimum HTTP surface for buyer payment-method acquisition and
  Transfers confirmation — the first real money-movement path reachable
  over HTTP in this codebase; Stripe stays fully isolated behind
  Payments-owned ports throughout (no new integration pattern).
- `Illuminate\Auth\Middleware\EnsureEmailIsVerified` (`verified`,
  Laravel's own, unmodified) added as this codebase's first
  email-verification gate — exactly five routes, chosen by whether the
  action initiates new transactional activity or merely continues/
  fulfills an existing obligation.
- `infrastructure/docker/php/www.conf` (new) — the first PHP-FPM
  worker-pool tuning since Phase 0's stock Alpine defaults.
- One new `AdminCapability` case (`horizon.view`), Administrator-only,
  extending Phase 8's existing generic capability-Gate pattern by
  exactly one case — the only new Administration capability this phase
  introduces.
- No change to any accepted Payments/Transfers/Bids/Auctions state
  machine. No new framework-listener orchestration replacing existing
  direct application-service calls. No seller payout execution. No
  Fraud & Risk capability of any kind. No general HTTP/UI for Ratings or
  Disputes.

## 5. Database changes

None. Phase 9 introduced no new migrations in any package — every
schema this phase needed (webhook idempotency, bid-placement claims,
notification-delivery ledger, etc.) was already built in earlier phases;
Phase 9 is delivery-surface, tuning, and documentation work on top of an
already-complete domain model.

## 6. Test and validation results

- `apps/web`: 220 passed, 782 assertions.
- `shared-kernel`: 31 passed, 42 assertions.
- `Queues`: 101 passed, 281 assertions.
- `QueuePresence`: 81 passed, 165 assertions.
- `Auctions`: 66 passed, 168 assertions.
- `Bids`: 44 passed, 102 assertions.
- `Payments`: 105 passed, 5 skipped (real Stripe test-mode integration,
  self-skipping — see §7), 274 assertions.
- `Transfers`: 62 passed, 191 assertions.
- `Disputes`: 47 passed, 111 assertions.
- `Ratings`: 43 passed, 75 assertions.
- `Notifications`: 35 passed, 60 assertions.
- `Administration`: 49 passed, 150 assertions.
- **Total: 884 passed, 5 skipped, across all 12 test suites.**
- PHPStan/Larastan: clean, every package and `apps/web`.
- Pint: clean for every file created or substantively edited this
  phase; pre-existing, unrelated Fortify-scaffold `declare(strict_types=1)`
  gaps left untouched (Sprint 4/5 precedent).
- `composer audit`: clean, `apps/web` + all 11 packages.
- `npm audit`: clean (one moderate `postcss` advisory found and patched
  in Sprint 5).
- Frontend build (`npm run build`): clean.
- Secret scan: clean across every file touched or created in every
  Phase 9 sprint.
- Docker image rebuild from scratch (`docker compose build --no-cache`):
  succeeds.
- Horizon startup: succeeds ("Horizon started successfully").
- Storage write validation as `www-data`: succeeds, minimum-required
  permissions.

## 7. Known limitations and external launch blockers

Three items ADR-027 §7 named as "resolved during Phase 9" are **not**
resolved — each requires your own action, not further implementation,
and none is invented here:

1. **Real Stripe test-mode credentials are not configured.** `.env` has
   no `STRIPE_*` values at all (not even placeholders). Five tests in
   `packages/Payments` (`StripeBuyerPaymentMethodGatewayTest.php` and
   related) continue to self-skip, exactly as they were designed to
   when no real `sk_test_`-prefixed key is present. This has been an
   acknowledged gap since Phase 4 and remains one.
2. **The US legal-review document is a complete, correctly disciplined
   skeleton, not a completed review.** All 11 items in
   `docs/legal/us-launch-review.md` still read "Awaiting determination."
   Per ADR-027's own Architecture Refinement §7, this report does not
   claim legal launch-readiness — see
   `docs/releases/launch-readiness-report.md`.
3. **The `main` branch/release merge has not happened.** `main` remains
   at Phase 0's own completion commit (`704ea49`); all of Phases 1
   through 9 exist only on `feat/phase-1-authentication`. Per ADR-027's
   Architecture Refinement §8, this merge requires its own separate,
   explicit approval immediately before execution — it was deliberately
   not performed as a side effect of this sprint.

Everything ADR-027 §7 named as "explicitly deferred beyond Phase 9" —
ICU locale-data, chargeback precedence/reconciliation, dedicated
age-verification infrastructure, physical handoff safety content,
value/AML transaction limits, low-confidence-auction blocking policy,
in-app chat, seller payout execution, Fraud & Risk — remains genuinely
absent from the codebase, confirmed by direct search during this
sprint's own verification, not merely assumed.

## 8. Phase 10 readiness assessment

Phase 9's own domain/backend/documentation scope is complete and
internally consistent, verified against ADR-027's every named
commitment (see `docs/releases/project-consistency-review.md`). **No
blocker exists at the engineering level for any future phase.** However,
"launch-ready" and "ready for the next phase" are not the same claim:
the three external blockers in §7 above must be resolved — by you,
supplying credentials, legal determinations, and the merge decision —
before this project can honestly describe itself as launch-ready. Per
this project's established phase-gating discipline, any further phase
requires its own separate authorization, and — per ADR-027's own
Architecture Refinement §8 — the `main` branch merge specifically
requires a separate, explicit approval of its own, distinct from
approving this report.
