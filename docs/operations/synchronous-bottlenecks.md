# Phase 9 Sprint 4 — Synchronous Bottleneck Findings

Documented separately from Horizon/queue-processing tuning, per ADR-027
Decision 6 ("this same empirical approach also applies to any
synchronous bottleneck discovered during testing...although those
concerns remain outside Horizon itself") and per this sprint's own scope
instruction to document synchronous bottlenecks separately.

## 1. PHP-FPM worker starvation (fixed, committed)

**Evidence.** Alpine's stock `pm.max_children=5` — never tuned since
Phase 0 — caused a single, otherwise-ordinary bid-placement request to
take 8.6 seconds under only ~7 concurrent HTTP requests during Sprint
4's own load-test dry run.

**Fix.** `infrastructure/docker/php/www.conf` (new): `pm=dynamic,
pm.max_children=20, pm.start_servers=6, pm.min_spare_servers=4,
pm.max_spare_servers=12`, copied into the image via a new `Dockerfile`
`COPY` line. Sized against this host's available resources (16 cores,
~16.6GB RAM) as a first evidence-informed pass, not a permanent
production capacity plan — revisit once real production telemetry
exists, following Decision 3's own "provisional engineering constant"
precedent.

**Verification.** During the final diagnostic burst, FPM status
(`active`, `idle`, `total`, `listen_queue`, `max_listen_queue`,
`max_children_reached`, `slow_requests`) was polled at 0.3s intervals for
the full duration and stayed constant at
`active=1, idle=6, total=7, listen_queue=0, max_listen_queue=0,
max_children_reached=0, slow_requests=0` — the pool never grew beyond
its `start_servers=6` baseline and never approached `max_children=20`.
No FPM queueing or worker-spawning occurred during that burst.

## 2. Evidence-photo storage permissions (fixed, not a committed file change)

**Evidence.** `storage/app/private/presence-evidence/` was root-owned
`700`, blocking the `www-data` process user and causing 15–30s hangs
before a `League\Flysystem\UnableToCreateDirectory` failure on every
evidence-photo upload during fixture seeding.

**Fix.** Corrected to the minimum required ownership/permissions —
explicitly **not** `chmod 777`: `chown -R www-data:www-data`, directories
`755`, files `644`. This is a runtime container filesystem correction,
not a tracked source file; there is no repository change to commit for
it. It must be re-applied (or built into the image/entrypoint) for any
fresh container, since it is not currently persisted anywhere in version
control.

## 3. PDO persistent connections — rejected (not retained)

**Evidence.** A controlled A/B experiment (`DB_PERSISTENT_CONNECTIONS`
environment flag, default off, `config/database.php` reading
`PDO::ATTR_PERSISTENT` only when explicitly enabled) compared baseline
vs. persistent connections on first-query latency, total p50/p95/p99,
web-group latency, event-publication latency, Postgres connection count,
FPM worker count, failed requests, deadlocks/timeouts, and bid-ordering
correctness.

**Result.** Persistent connections reduced first-query cost by roughly
71% but produced no meaningful improvement in total request latency
(p50 1403.65ms → 1402.30ms). Connection growth remained bounded (2→8,
tracking FPM worker count 1:1) with no leaked transaction state
(`pg_stat_activity` showed no `idle in transaction` rows) under either
configuration.

**Decision.** Per this sprint's own stated criterion ("if persistent
connections materially reduce only the first-query cost but do not
materially improve total request latency, reject them as insufficient"),
persistent connections were rejected. The `DB_PERSISTENT_CONNECTIONS`
flag has been fully removed from `config/database.php`, `.env.example`,
and the local `.env` — it is not retained even as inert scaffolding.

## 4. Docker Desktop bind-mount opcache overhead — environment artifact, not fixed

**Evidence.** See `docs/operations/load-test-report.md` §2: `/up` measured
2.8–3.6s with `opcache.validate_timestamps=On` vs. ~0.35–0.4s with it
`Off`, isolating a Windows↔Linux-VM filesystem-boundary `stat()` cost.

**Disposition.** Classified as a Docker-Desktop-bind-mount artifact, not
a production-relevant finding — production would not bind-mount source
code this way — and therefore not fixed. The temporary opcache change
made to measure this was reverted; `validate_timestamps` is confirmed
back to `On`.

## 5. Final diagnostic pass — PHP-FPM scheduling and event-dispatch latency

A last bounded diagnostic pass instrumented (temporarily, fully reverted
before this report) the web middleware group, the bid-placement request
lifecycle, and domain-event publication, to separate remaining latency
into named segments.

**Per-middleware timings: inconclusive.** The `Middleware::web(replace:
...)`-based timing wrapper did not intercept the six vendor middlewares
as intended; `middleware_ms` came back empty for every request in the
burst. This is a genuine tooling gap in this diagnostic pass, disclosed
as such rather than papered over — root cause undetermined, no fix
attempted per this sprint's "no speculative fixes" instruction.

**Event-dispatch timings.** `event_dispatch_start_to_job_queued_ms`
measured 140–392ms across the burst, vastly exceeding the raw Redis
push cost measured separately (~1ms). This isolates the event-publication
cost to listener resolution / job construction / serialization, not the
Redis network call itself. Time from `JobQueued` to loop end was
consistently low-single-digit ms — nearly all the cost sits before the
enqueue marker.

**FPM status during the burst.** No queueing or worker-spawning occurred
(see finding 1's verification above — same burst).

**Dominant remaining latency source.** `web_group_ms`, `auth_ms`,
`throttle_ms`, and `bid_service_tx_ms` were found to scale
proportionally together across requests, rather than any one segment
ballooning independently. Combined with FPM, Redis, and Postgres each
showing no contention, this pattern is consistent with transient
Docker-Desktop VM/host-level CPU scheduling contention, not a specific
application code path.

**Smallest proposed correction.** None at the application level — no
evidence points to a specific line of code, query, or middleware to
change. The one concrete unresolved item is the middleware-timing
tooling gap above, not application behavior.

## Overall conclusion

No application-level bottleneck requiring a code change was demonstrated
by this sprint's diagnostics. The remaining latency variance is
reasonably classified as a Windows/Docker Desktop environment artifact
based on the available evidence, not a production-relevant finding. No
production capacity claim can be made from this environment; a
production-like Linux validation remains an external launch-readiness
requirement before production release.

## References

- ADR-027 §6 (Decision 6).
- `docs/operations/load-test-report.md`.
- `docs/operations/capacity-tuning-report.md`.
- `infrastructure/docker/php/www.conf` (Sprint 4 PHP-FPM baseline).
