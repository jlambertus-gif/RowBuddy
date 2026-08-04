# Launch-Readiness Report

Phase 9 (Hardening & Launch Readiness), Sprint 6. This report answers
one question directly: **is RowBuddy ready to launch in its approved
initial market (United States, ADR-027 Decision 1)?**

## Answer: not yet.

Every engineering responsibility ADR-027 assigned to Phase 9 is
complete, tested, and documented. Three items remain, and every one of
them requires your own action — supplying credentials, a legal
determination, or a merge decision — not further implementation. None
is invented or assumed complete here.

## Blocker 1 — US legal-review determinations (0 of 11 resolved)

**Status: Awaiting determination on every item.**

`docs/legal/us-launch-review.md` is a structurally complete, correctly
disciplined skeleton — it walks through all ten categories
`docs/legal/jurisdiction-requirements.md` requires (transfer lawfulness,
venue/organizer permission, marketplace licensing, payment/money-
transmission implications, tax, consumer protection, refund obligations,
privacy/evidence-retention, age restrictions, dispute resolution) plus
confirming ADR-003's line-standing framing holds under US law. **Every
one of the eleven items still reads "Awaiting determination."** Per
ADR-027's own Architecture Refinement §7, this implementation has never
invented a legal conclusion, and this report does not either.

**What's needed from you**: each item requires input from the
appropriate legal authority — not from this codebase. Once a
determination is recorded, enforcement requires no new engineering:
Administration's existing `RestrictedCategoryActivationService`/
`JurisdictionRuleActivationService` (Phase 8) activate or deactivate the
corresponding `jurisdiction_rules`/`restricted_categories` row, citing
the legal-review document as the mandatory reason, with the change
already recorded in `admin_actions`.

## Blocker 2 — Real Stripe test-mode credentials

**Status: Not configured.** `.env` has no `STRIPE_*` values at all —
not even placeholders. Five tests in `packages/Payments`
(`StripeBuyerPaymentMethodGatewayTest.php` and related) continue to
self-skip by design whenever no real `sk_test_`-prefixed secret is
present.

**What's needed from you**: real Stripe test-mode API keys
(`STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET`), supplied
externally — this codebase has never fabricated a credential and will
not. Once supplied, the currently-skipping tests will run for real
against Stripe's own test-mode API, closing the last gap in Payments'
own test coverage. This has been a known, explicitly acknowledged gap
since Phase 4 and remains the same gap today — Phase 9 did not change
its status.

## Blocker 3 — The `main` branch/release merge

**Status: Not performed — `main` is still at Phase 0.** `git log main
-1` resolves to `704ea49` ("chore: complete Phase 0 foundation"). All of
Phases 1 through 9 exist only on `feat/phase-1-authentication`, 76+
commits ahead of `main`. Per ADR-027's own Architecture Refinement §8:
*"The branch/release resolution requires its own separate, explicit
approval immediately before execution — merging `feat/phase-1-authentication`
into `main`, rewriting branch history, or changing the production
release branch does not happen as a side effect of Sprint 6 planning
alone."* This report does not perform that merge and does not create a
release tag — both remain deliberately unexecuted pending your explicit,
separate go-ahead.

**What's needed from you**: an explicit decision to merge
`feat/phase-1-authentication` into `main` (fast-forward or otherwise —
your call), and whether to tag the result (e.g. `v0.10.0-hardening` or
a `v1.0.0` launch tag, also your call). Once approved, this is a
mechanical git operation, not new engineering work.

## What is genuinely ready

- **Correctness and domain-invariant behavior**: proven under real
  concurrent load (Sprint 4's full-scale test), not merely unit-tested.
  Bid ordering, idempotency, proximity-based cancellation, transfer
  confirmation, dispute resolution, and payment capture all held under
  concurrency during that test with zero domain-invariant violations.
- **Security**: a structured whole-system review against every risk
  named in `claude-mvp-analysis.md` §3.2 found zero confirmed
  vulnerabilities. Mandatory email verification is implemented and
  tested. Dependency scanning (`composer audit`, `npm audit`,
  Dependabot) is wired into CI and was clean at every check this phase
  ran.
- **Observability**: Horizon, `failed_jobs`, `/up`, and structured JSON
  logs together provide the operational visibility ADR-027 Decision 5
  scoped — documented in `docs/operations/observability.md` and
  `runbook.md`.
- **Test coverage**: 884 passing tests (+5 skipped, pending Blocker 2)
  across every package and `apps/web`; PHPStan/Larastan and Pint clean.

## What is explicitly NOT ready, separate from the three blockers above

- **Production capacity is not proven.** Sprint 4's load test ran
  against Decision 3's exact targets (250 concurrent users, 100
  concurrent auctions, 25 bidders/auction, 10 bids/sec sustained 60s)
  but only in a Windows/Docker Desktop development environment. Latency
  variance measured there was attributed to that environment, not the
  application — but this is a documented environment limitation, not
  proof the targets hold in production. A production-like Linux
  environment run remains an external requirement before any production
  capacity claim, per `docs/operations/load-test-report.md`'s own
  caveat, unchanged since Sprint 4.
- **No penetration test.** ADR-027 Decision 4 explicitly excludes a
  third-party penetration-test engagement from Phase 9's scope — the
  internal security review is not a substitute for one, and this report
  does not claim it is.

## Recommendation

Do not launch until all three blockers above are resolved. None of them
requires more engineering from this project as it currently stands —
each requires a decision or an input only you can provide. Once
resolved, the `main` merge (Blocker 3) is the natural final step,
performed only after your explicit approval of that specific action.
