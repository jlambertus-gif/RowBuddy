# Observability

ADR-027 Decision 5 deliverable. Phase 9 observability extends existing
platform capabilities only — no third-party APM, metrics platform,
distributed tracing, or proactive alerting infrastructure was
introduced, per that decision's explicit scope. This document describes
what is monitored, where, the expected signals, failure indicators, and
how these pieces are used together during normal operation.

## What is monitored, and where

### Structured application logs

The `single` log channel (`apps/web/config/logging.php`) writes
structured JSON via Monolog's own `JsonFormatter` — no new logging
dependency. Each line is independently `json_decode`-able and carries
Monolog's stock shape: `message`, `context`, `level`, `level_name`,
`channel`, `datetime`, `extra`. `apps/web/tests/Feature/StructuredLoggingTest.php`
proves this round-trips correctly and is the regression guard against
this silently reverting to plain-text log lines. Location: the
container's `storage/logs/laravel.log`; no external log shipper is
configured — reading it is a direct file/`docker compose logs` concern.

No request-scoped context processor was added — a log line's `context`
array contains only what the call site explicitly passes to
`Log::info()`/`Log::error()`/etc., not an automatic per-request
correlation id. If you need to trace a single request across several
log lines, correlate by timestamp and any identifiers (auction id, user
id, transfer id) the call site already logs.

### Laravel Horizon

Runs as its own Docker Compose service (`horizon: command: php artisan
horizon`), a single supervisor (`config/horizon.php`, `connection:
redis`, `queue: ['default']`, `balance: auto`) — Laravel's own stock
scaffold values, deliberately unchanged (`docs/operations/capacity-tuning-report.md`:
no queue contention was demonstrated, so no supervisor tuning or queue
separation was introduced). Horizon's own dashboard (`/horizon`) shows
queue throughput, wait times, and recent/failed jobs live.

Access is gated by the `viewHorizon` Gate
(`app/Providers/HorizonServiceProvider.php`), which delegates to the
`AdminCapability::HorizonView` capability — Administrator-only per
`AdminRoleCapabilityMap` (Moderators do not have it), the same
capability-Gate pattern every other Administration surface uses. There
is no separate Inertia page around it; Horizon's own dashboard is the
surface.

### `failed_jobs`

A queued job that throws is retried once (Horizon's supervisor default,
`tries: 1`) before landing in `failed_jobs`
(`database/migrations/0001_01_01_000002_create_jobs_table.php`, `failed.driver
= database-uuids` in `config/queue.php`). There is no dead-letter queue
and no bespoke failure-tracking beyond this table plus Horizon's own
dashboard — the same "accepted MVP limitation" posture Notifications
already established for delivery-failure alerting (ADR-025 §11), now
extended to queue-processing failures generally, per ADR-027 Decision 5.

### `/up` health endpoint

Registered via `bootstrap/app.php`'s `health: '/up'`, Laravel's own
default health-check controller — no custom check logic layered on top.
It reports the application booted and can serve a request; it does not
independently verify Postgres/Redis/Horizon/Reverb connectivity. Treat a
200 from `/up` as "the web tier is alive," not "every dependency is
healthy."

### Reverb

Runs as its own Docker Compose service (`reverb: command: php artisan
reverb:start --host=0.0.0.0 --port=8080`). Broadcasts exactly six
Auctions/Bids events through `BroadcastAuctionSnapshot`
(`BidPlaced`, `AuctionClosingStarted`, `AuctionClosingDeadlineExtended`,
`AuctionWon`, `AuctionExpired`, `AuctionCancelled`) onto each auction's
own public channel. There is no Reverb-specific dashboard; connectivity
is observed indirectly, through whether connected clients keep receiving
these broadcasts.

### Redis / PostgreSQL connection separation

Cache, queue, and session traffic are logically separated by Redis
logical database number (`REDIS_CACHE_DB=1`, `REDIS_QUEUE_DB=2`,
`REDIS_SESSION_DB=3` in `.env.example`, wired through the named `cache`/
`queue` connections in `config/database.php`), not by separate Redis
instances — a single `redis` container serves all three. PostgreSQL is a
single instance/database per environment; there is no read-replica or
connection-pooling layer to separately monitor.

## Expected signals during normal operation

- Horizon's dashboard shows jobs processing continuously with a wait
  time near zero and no persistent backlog on the `default` queue.
- `failed_jobs` stays empty or near-empty; a steady trickle of new rows
  indicates a systemic problem (a bad deploy, an external dependency
  outage), not routine behavior.
- `/up` returns 200.
- Reverb-connected clients (an open auction's live page) keep receiving
  bid/status-change events without needing a manual page refresh.
- Structured logs contain no repeated `level: error`/`critical` lines
  for the same call site across multiple requests.

## Failure indicators and where they show up

| Symptom | Where it shows | Likely cause |
|---|---|---|
| Growing `failed_jobs` count | Horizon dashboard, `failed_jobs` table | A listener/job throwing consistently — check the job's own exception in `failed_jobs.exception` |
| Rising Horizon queue wait time | Horizon dashboard | Worker starvation (compare against `docs/operations/synchronous-bottlenecks.md`'s PHP-FPM findings) or a burst exceeding sustained throughput |
| `/up` non-200 or timeout | Direct health check / uptime monitor | App container down, boot failure, or a fatal error in the request lifecycle |
| Reverb clients stop receiving events but the page stays open | Client-side (no live update), Reverb container logs | Reverb process crashed/restarted, or a Redis connectivity issue between `app` and `reverb` |
| Repeated `error`/`critical` JSON log lines for the same message | `storage/logs/laravel.log` | An application-level defect — the structured `context` field usually names the failing operation |
| Stripe webhook processing errors | `storage/logs/laravel.log`, `webhook_events` table absence for a known Stripe event id | Signature verification failure (expected for a genuinely forged request) or a processing exception (unexpected — investigate) |

## How these are used together

There is no single "observability dashboard" — this is a deliberate
Phase 9 scope decision (no third-party APM/metrics platform introduced).
During normal operation:

1. Horizon's dashboard is the first place to look for anything
   queue-related (broadcasts, transfer-expiry sweeps, notification
   delivery).
2. `failed_jobs` is the concrete artifact behind any "did this actually
   happen" question about a queued side effect.
3. Structured JSON logs are where you look for the *why* once Horizon or
   `failed_jobs` tells you *what* failed.
4. `/up` is the cheapest first check for "is the app even running,"
   before looking at anything else.

See `docs/operations/runbook.md` for what to actually do when one of the
failure indicators above appears.
