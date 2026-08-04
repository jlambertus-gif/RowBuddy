# Phase 9 Sprint 4 — Horizon Capacity-Tuning Report

ADR-027 Decision 6 deliverable: "final Horizon configuration; load-test
report; capacity-tuning report; recorded rationale for every
configuration change."

## Process followed (Decision 6 §1–5)

1. Executed the approved Decision 3 load-test scenario (250 concurrent
   users, 100 concurrent auctions, 25 concurrent bidders/auction, 10
   bid-submissions/sec sustained 60s) — see
   `docs/operations/load-test-report.md`.
2. Observed Horizon, `failed_jobs`, structured logs, and application
   behavior during the test.
3. Identified actual contention points.
4. Tuned iteratively where contention was demonstrated.
5. Repeated until the achieved workload was sustained with stable queue
   behavior.

## Observations

- No queue backlog was observed at any point during the burst.
- `failed_jobs` recorded zero entries attributable to Horizon/queue
  processing (all observed failures were fixture/environment issues, see
  `docs/operations/synchronous-bottlenecks.md`, not queue-processing
  failures).
- The accepted-bid broadcast job and other queued listeners processed
  without unbounded queue growth for the entire achieved workload.
- No measurable contention was observed between the Reverb-broadcast
  workload and the existing notification-email / transfer-expiry-sweep
  workloads sharing the default queue.

## Decision: no configuration change

Per Decision 6 ("queue separation is not introduced by default...only
when load-test evidence demonstrates measurable contention"), and per
this sprint's own scope instruction ("do not introduce queue separation
unless contention is demonstrated"): **no Horizon configuration change
was made.** The evidence gathered does not demonstrate contention of any
kind between workloads, so no supervisor tuning, process-count change, or
queue separation is justified.

## Final Horizon configuration

Unchanged from Phase 0/7 defaults — a single supervisor, default queue,
no dedicated queues or balancing-strategy changes. Recorded here per
Decision 6's requirement to document the final configuration explicitly,
even when that configuration is "no change."

## Rationale record (per configuration change)

No configuration change was made this sprint; therefore no per-change
rationale entry is required beyond the observation above that no
contention was demonstrated. This absence-of-change is itself the
Decision-6-mandated outcome for a workload showing no contention
evidence, and is recorded as such rather than left silent.

## Scope note

This report covers Horizon/queue-processing tuning only. The PHP-FPM
worker-pool tuning performed this sprint (`pm.max_children=5` → `20`) is
a synchronous-request-handling concern, entirely outside Horizon, and is
documented separately in `docs/operations/synchronous-bottlenecks.md`
per Decision 6's own instruction that synchronous bottlenecks be kept
separate from Horizon tuning.

## Environment caveat

As with the load-test report: the absence of demonstrated contention was
observed in a Windows/Docker Desktop development environment, not a
production-like Linux host. This conclusion — no queue separation
justified — is only as strong as that environment allows; it should be
re-verified once real production telemetry or a production-like
environment load test exists, per Decision 6's "provisional MVP tuning
constant" posture.

## References

- ADR-027 §6 (Decision 6).
- `docs/operations/load-test-report.md`.
- `docs/operations/synchronous-bottlenecks.md`.
