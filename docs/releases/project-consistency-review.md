# Project Consistency Review (Phase 9 Sprint 6)

ADR-027 Sprint 6 objectives #8–#11: verify every ADR cross-reference,
verify every Phase 9 deliverable exists, verify every deferred item is
explicitly documented, and perform a final project-wide consistency
review. This document records the methodology and findings; corrections
made as a result are noted inline and reflected in the actual files.

## 1. ADR cross-reference verification

Scope: all 27 ADRs in `docs/decisions/` (001–027). Every `ADR-NNN`
mention (with or without a §-section citation) was extracted, and each
target file/section was checked for existence — roughly 140 individual
mentions, ~35 of them citing a specific section number.

**Finding 1 — corrected.** ADR-014 §2 cited "ADR-004/§7.3" for the
`PaymentIntent` lifecycle's state set. ADR-004 has no numbered sections
at all (four short prose sections: Status/Decision/Rationale/
Consequences), and the cited state names (`held`/`released`) don't
match what was ever actually implemented — `PaymentIntentStatus`
(`packages/Payments/src/ValueObjects/PaymentIntentStatus.php`) is
`Authorized/Failed/Captured/CaptureFailed/Cancelled/Refunded`, and
ADR-019 §2 explicitly declined to model `Held`/`ReleasedToSeller`/
`RefundedToBuyer`. This was ADR-014's own forward-looking sketch at
Phase 4 time, before ADR-019 (Phase 5) and ADR-022 (Phase 6) finalized
the real state set. **Corrected** in ADR-014 itself — the dangling
citation replaced with an explanation of where the state set was
actually finalized, preserving the original decision's intent.

**Finding 2 — observed, not corrected.** ADR-003 through ADR-027's
"accepted" and phase-completion "accepted" dates cluster tightly in
late July 2026 (git tag timestamps confirm: `v0.2.0-catalog` through
`v0.9.0-administration` were all tagged between 2026-07-27 and
2026-07-31), **except** Phase 3's completion report ("accepted
2026-10-01") and Phase 4's completion report ("accepted 2026-10-22"),
and ADR-014 itself ("Accepted — 2026-10-06") — all three several months
later than their own git tag timestamps (`v0.4.0-auctions` tagged
2026-07-28 11:34; `v0.5.0-payments` tagged 2026-07-28 17:26). This is
either a genuine gap between when the code was tagged and when it was
formally reviewed/accepted in a separate conversation (plausible — this
project's phase-gating discipline requires an explicit acceptance step
distinct from the commit itself), or a shared date error across three
independent documents. **Not corrected here** — unlike Finding 1, this
isn't something I can verify against a ground-truth source the way a
section citation can be checked against a file's actual structure; it
would require you to confirm which explanation is true. Flagging it
plainly rather than guessing and silently rewriting three documents'
historical dates.

**Other results.** ~35 section-numbered citations were spot-checked
against each target ADR's actual structure — all resolved correctly
except Finding 1. Two documentation conventions coexist across the ADR
corpus (`###`-numbered headings vs. flat `##` sections containing a
numbered ordered list, both cited the same way) — consistent within
each ADR, not itself a defect, worth standardizing if a future phase
wants one convention. Every ADR 001–027 except ADR-002 and ADR-027 has
at least one confirmed incoming reference from another ADR, the
roadmap, or a completion report — ADR-027 is expected (it's the current
ADR, nothing has been built on top of it yet within this same
document corpus); ADR-002 (multilingual-from-start) is referenced only
from `apps/web/tests/Unit/TranslationParityTest.php`, not from another
ADR/roadmap/report — not a true orphan, just never cross-referenced
within that specific corpus.

## 2. Phase 9 deliverable existence audit

Every concrete artifact ADR-027 commits to (Decisions 0–7, its
"Consequences" list, and its "Explicitly Out of Scope" list) was
checked against the actual codebase.

**EXISTS, verified:**
- Minimum Auctions/Bids HTTP+Reverb surface (`routes/web.php`,
  `AuctionPublicSnapshotAssembler`, `BroadcastAuctionSnapshot`).
- Minimum Payments/Transfers HTTP surface.
- `docs/security/security-review.md` and `security-checklist.md`.
- `composer audit` in CI for every job; Dependabot configured for every
  package + npm + GitHub Actions; every package now runs its own test
  suite in CI, not just indirectly through `apps/web`.
- `docs/operations/load-test-report.md` and
  `capacity-tuning-report.md`, citing Decision 3's exact targets.
- Horizon config left at evidence-justified defaults; no queue
  separation without demonstrated contention.
- `horizon.view` `AdminCapability`, Administrator-only, wired to a real
  Gate; `/up` health endpoint; structured JSON logging.
- Zero scope-creep signatures for any explicitly-deferred item (ICU
  locale-data, chargeback precedence, age-verification infrastructure,
  physical handoff safety content, value/AML limits, low-confidence-
  auction blocking, in-app chat, seller payout execution, Fraud & Risk)
  — confirmed absent via direct search, not merely assumed.

**Were MISSING, now created this sprint:**
- `docs/operations/observability.md` — did not exist before this
  sprint; created.
- `docs/operations/runbook.md` — did not exist before this sprint;
  created.

**Still genuinely unresolved (external inputs, not implementation
gaps — see `docs/releases/launch-readiness-report.md`):**
- US legal-review determinations (0 of 11 items resolved).
- Real Stripe test-mode credentials.
- The `main` branch/release merge.

## 3. Deferred-item documentation check

Every item in ADR-027 §7's "Explicitly deferred beyond Phase 9" list and
"Explicitly Out of Scope" list was independently re-verified against the
current codebase (not just re-read from the ADR) and confirmed genuinely
absent: ICU locale-data improvements, chargeback precedence/
reconciliation, dedicated age-verification infrastructure, physical
handoff safety guidance/content, value/AML transaction limits,
low-confidence-auction blocking policy, in-app chat, seller payout
execution, and any automated/rules-based Fraud & Risk capability. None
of these were found implemented anywhere in `packages/` or `apps/web/app`.
Every one remains documented with its own rationale in ADR-027 — none
is silently dropped from the record.

## 4. Other consistency fix made this sprint

`docker-compose.yml`'s header comment claimed the stack "has not been
executed in this environment (no Docker installation was available to
verify it end-to-end)" — stale as of Phase 9 Sprint 4, which extensively
built and ran this exact stack (`docker compose build --no-cache`,
`up`, `exec`) against real containers, repeatedly, across load testing
and the security review. Corrected to reflect that verification
accurately, while preserving the file's own caveat that this remains a
Windows/Docker Desktop development environment, not a production-like
Linux host.

## 5. Summary

One real ADR cross-reference bug found and corrected (ADR-014). One
date-consistency anomaly observed and reported, not corrected (requires
your confirmation, not a code-verifiable fact). Two named Decision-5
deliverables were missing and have been created this sprint. Every other
ADR-027 commitment checked resolves correctly against the actual
codebase. Every deferred/out-of-scope item remains genuinely absent and
documented. One stale operational comment corrected. No scope
expansion, no new product features, no mobile work — consistent with
this sprint's own stated boundaries.
