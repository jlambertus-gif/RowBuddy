# Operations Runbook

ADR-027 Decision 5 deliverable. Recovery procedures and operational
commands for the signals named in `docs/operations/observability.md`.
Scoped to what this project's current infrastructure actually supports —
no dead-letter queue, no automated remediation, no third-party
alerting exists, by deliberate Phase 9 decision. This is a manual-first
runbook, not an automation script.

## Service topology

Base `docker-compose.yml` (production-shaped): `app` (php-fpm, built
from `infrastructure/docker/php/Dockerfile`), `nginx` (port 8000→80),
`postgres` (`postgis/postgis:16-3.4`), `redis`, `horizon`, `reverb`
(port 8080). `docker-compose.override.yml`, auto-merged locally, adds
per-package bind mounts, host-exposed `postgres`/`redis` ports, and a
`node` dev server — override-only, not part of a production shape.

## Everyday operational commands

```
# Horizon
php artisan horizon                # start (normally the container's own command)
php artisan horizon:status         # check supervisor status
php artisan horizon:pause          # pause processing without stopping the process
php artisan horizon:continue       # resume after a pause
php artisan horizon:terminate      # graceful shutdown (finishes in-flight jobs)
php artisan horizon:clear          # clear all queued jobs from a queue
php artisan horizon:forget <id>    # remove a single failed job by id

# Queue (non-Horizon fallback / direct inspection)
php artisan queue:failed           # list failed jobs
php artisan queue:retry <id>       # retry a specific failed job
php artisan queue:retry all        # retry every failed job
php artisan queue:flush            # delete all failed jobs (irreversible — see below)
php artisan queue:restart          # signal all workers to restart after their current job

# Reverb
php artisan reverb:start --host=0.0.0.0 --port=8080   # normally the container's own command
php artisan reverb:restart

# Scheduler
php artisan schedule:list          # confirm EvaluateTransferExpiry (every 5 minutes) is registered
php artisan schedule:run           # manually trigger a due-task check
```

## Incident procedures

### Growing `failed_jobs` / rising Horizon wait time

1. Open Horizon's dashboard (`/horizon`, Administrator-only) and check
   which job class is failing and its exception message.
2. Cross-reference `storage/logs/laravel.log` (structured JSON) for the
   same timeframe — the `context` field usually names the failing
   operation.
3. If the cause is a transient external dependency (Stripe, a
   momentary Postgres/Redis blip): once the dependency recovers,
   `php artisan queue:retry <id>` (or `all`) the affected jobs. Do not
   `queue:flush` — that deletes the failure record permanently, losing
   the ability to retry or audit what happened.
4. If the cause is a genuine application defect: this requires a code
   fix and a new deploy, not an operational retry. Do not retry a job
   whose failure is deterministic (it will fail again identically) —
   fix first, then retry.
5. If wait time (not failure count) is what's rising with no matching
   error: this is a throughput/capacity signal, not a correctness
   defect — see `docs/operations/synchronous-bottlenecks.md` and
   `docs/operations/capacity-tuning-report.md` for the known
   PHP-FPM-starvation and Horizon-contention findings from Phase 9's
   own load testing, and whether the current symptom matches either.

### `/up` returning non-200 or timing out

1. Check the `app` container is running: `docker compose ps`.
2. Check `docker compose logs app` for a boot-time fatal error (a bad
   migration, a missing `.env` value, a Composer autoload issue).
3. If the container is up but `/up` still fails, check nginx→php-fpm
   connectivity (`docker compose logs nginx`) before assuming the
   application itself is broken.
4. `/up` does not check Postgres/Redis/Horizon/Reverb — a 200 here does
   not clear those as suspects for a different symptom.

### Reverb clients stop receiving live updates

1. Check the `reverb` container is running and not repeatedly
   restarting: `docker compose ps`, `docker compose logs reverb`.
2. Confirm Redis connectivity between `app` and `reverb` — broadcasts
   are dispatched through the queue/Redis, not a direct process
   connection, so a Redis outage silently stops delivery without
   crashing either process.
3. Restart Reverb (`php artisan reverb:restart` inside the container, or
   `docker compose restart reverb`) if it's running but not delivering —
   this drops currently-connected WebSocket clients, who must reconnect
   (Inertia/React clients reconnect automatically on the next relevant
   page action, per this project's existing frontend wiring).

### Stripe webhook processing errors

1. A signature-verification failure (`invalid_signature`, HTTP 400) for
   a request that is genuinely from Stripe usually means
   `STRIPE_WEBHOOK_SECRET` is misconfigured for the current environment
   — check it matches the webhook endpoint's secret in the Stripe
   Dashboard, not a different endpoint's secret.
2. A processing exception *after* signature verification succeeds is an
   application-level defect — check `storage/logs/laravel.log` and
   `webhook_events` (the idempotency ledger,
   `packages/Payments/src/Infrastructure/Eloquent/EloquentWebhookEventRepository.php`)
   for whether the event was recorded before failing.
3. Stripe itself retries failed webhook deliveries on its own schedule —
   confirming the underlying cause is fixed is usually sufficient; you
   do not need to manually replay a Stripe webhook in the common case.

### Transfer-expiry sweep not running

1. `php artisan schedule:list` — confirm `EvaluateTransferExpiry` is
   registered on the `everyFiveMinutes()` cadence.
2. Confirm the container actually running the scheduler
   (`schedule:run` on a cron/timer, or `schedule:work` as a long-lived
   process — check which this environment uses) is up.
3. `TransferConfirmationService` itself also evaluates expiry lazily on
   any confirmation attempt (ADR-018 §3), so a transfer someone actively
   tries to confirm will still expire correctly even if the scheduled
   sweep is temporarily down — only *silent* (nobody-touches-it)
   transfers depend on the sweep actually running.

## Deployment / rollback

No automated deployment pipeline or blue-green/canary mechanism exists
in this repository — deploying is whatever process you use to build and
run the Docker images described above against a target environment.
Rolling back means redeploying the previous known-good commit/image;
there is no in-app feature-flag or maintenance-mode mechanism beyond
what Laravel provides natively (`php artisan down` / `php artisan up`).

Before any deploy, per this project's own CI (`.github/workflows/ci.yml`):
`composer audit`, Pint, PHPStan/Larastan, and the full Pest suite (every
package individually, plus `apps/web`) must be green, and the frontend
build must succeed.

## Known limitations (deliberate, not oversights)

- No dead-letter queue or automated failure alerting — `failed_jobs` and
  Horizon's dashboard are the extent of failure visibility (ADR-025 §11
  precedent, extended by ADR-027 Decision 5).
- No distributed tracing or request-correlation id in structured logs —
  correlate manually by timestamp and domain identifiers.
- No production-like Linux load-test evidence yet — see
  `docs/operations/load-test-report.md`'s own environment caveat; the
  procedures above are written for genuine failure modes but their
  *likelihood/frequency* under real production load is not yet known.
