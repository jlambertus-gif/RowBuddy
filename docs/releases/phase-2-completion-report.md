# Phase 2 (Presence & Trust) — Completion Report

Tag: `v0.3.0-presence`
Status: **Complete and formally accepted**, 2026-08-31.

## 1. Executive summary

Phase 2 delivers the QueuePresence bounded context end-to-end: a seller
can claim physical presence at a published queue, record GPS pings and
an evidence photo against that claim, and see a live, server-computed
confidence score/tier update after each action — all through a working
Inertia/React frontend, localized in English and Spanish, with every
meaningful verification action recorded in a new platform-wide audit
sink. This satisfies the Phase 2 exit criteria defined in
`docs/roadmap.md`.

The phase was delivered as 7 sprints, each scoped, implemented, tested,
and reviewed independently, mirroring Phase 1's cadence exactly. One new
ADR (ADR-008) was produced. The full backend test suite (220 tests
across three packages/apps) and the frontend production build pass,
static analysis (Larastan) is clean everywhere, and the complete
Discover → claim presence → start session → GPS → evidence photo → tier
progression → end session flow was manually verified in a real browser
and formally accepted by the product owner. Two real defects were found
and fixed during that manual verification (§7) — neither was
detectable by the automated test suite as it stood, and both are now
covered by regression tests.

No auctions, bidding, or payments exist yet — out of scope for this
phase by design.

## 2. Sprint-by-sprint deliverables

| Sprint | Commit | Delivered |
|---|---|---|
| 1 | `05efcb8` | `PresenceSession` aggregate scaffold: Active/Ended lifecycle, GPS-ping and evidence-photo signal recording guarded to the Active state, domain events. `GpsPingRecorded` deliberately not audited (high-frequency signal) — approved decision, unchanged for the rest of the phase. |
| 2 | `c93b45b` | Persistence layer: `presence_sessions` migration + Eloquent adapter behind `PresenceSessionRepository`; "one active session per seller per queue" enforced via a partial unique index (same technique as Queues' restricted-category uniqueness fix), translated to a domain-level `DuplicateActivePresenceSession` exception. Package wired into `apps/web`. |
| 3 | `ddc3072` | ADR-008 (confidence-scoring model v1) plus the pure, dependency-free `ConfidenceScorer` — weights/thresholds set so no combination of GPS signals alone can reach Evidence Verified; a photo is mandatory for that tier. |
| 4 | `1867fa0` | GPS presence capture application service + HTTP layer. New `QueueGeofenceLookup` port (owned by QueuePresence, not Queues) bridged by a composition-root adapter in `apps/web`. Ownership enforced in the application layer (`PresenceSessionAccessDenied`), not the HTTP layer. |
| 5 | `64f98c2` | Evidence-photo capture: GD-based metadata (EXIF) stripping (pure PHP, unit-tested standalone), private local-disk storage behind a swappable `EvidenceStorage` port, signed temporary URLs (5-minute TTL). Camera-first, JPEG-only, 8 MiB max — documented MVP limitation. |
| 6 | `86639ff` | Wired `ConfidenceScorer` to real persisted signal history via `ConfidenceRecomputer`. Append-only `presence_confidence_scores` history. Minimal, platform-wide audit sink (`audit_events` + one generic listener against the shared `AuditableAction` interface) — audits `PresenceSessionStarted`/`Ended`, `EvidencePhotoRecorded`, and tier-transition `PresenceConfidenceComputed` only. |
| 7 | `3ac3151` | Inertia/React frontend: presence-session page (start/GPS/photo/end), confidence badge, "Claim presence" entry point from Discover. Full real-browser acceptance test — which surfaced and led to fixing two real defects (§7). |

## 3. Features delivered

- Presence-session claim lifecycle: start, record GPS ping, record
  evidence photo, end — one active session per seller per queue.
- v1 confidence scoring: GPS-within-geofence + best accuracy, presence
  duration, evidence photo → points + tier (Unverified → Location
  Verified → Evidence Verified reachable in v1; Community Verified /
  Transfer Completed reserved for later phases).
- Private evidence storage with signed, time-limited retrieval; metadata
  stripped before storage.
- Append-only confidence-score history and a minimal audit trail for
  every meaningful verification action, across **any** module.
- Full Inertia/React frontend for all of the above, in English and
  Spanish, with zero hardcoded user-facing strings.

## 4. ADRs created

- **ADR-008 — Confidence Scoring Model v1**
  (`docs/decisions/008-confidence-scoring-model-v1.md`). Documents the
  exact point values, thresholds, and rationale — including why GPS
  alone cannot reach Evidence Verified, why evidence without any
  location signal scores zero, and why v1 has no time decay.

ADRs 001–007 were pre-existing and remain unchanged and binding.

## 5. Architecture changes

- `packages/QueuePresence` mirrors `packages/Queues`' package shape
  exactly: `src/{Application,Contracts,Events,Exceptions,Scoring,
  Infrastructure,ValueObjects}` plus the root `PresenceSession` aggregate.
- A new pattern this phase introduced: **cross-module and
  framework-boot-dependent adapters live in `apps/web`, not inside either
  package**. `EloquentQueueGeofenceLookup` (needs both Queues' and
  QueuePresence's internals) and `LocalPrivateEvidenceStorage` (needs a
  booted Laravel container for `Storage::temporaryUrl()`'s signed route)
  are both apps/web-only, bound in `AppServiceProvider` — neither package
  depends on the other, and QueuePresence's own tests never boot a full
  framework.
- The audit sink (`App\Listeners\RecordAuditEvent`) is registered against
  the **interface** `RowBuddy\SharedKernel\Contracts\AuditableAction`,
  not any concrete event class — Laravel's dispatcher resolves listeners
  for interfaces an event implements, so any current or future module's
  audit-worthy event is captured with zero additional wiring.
- `Tests\Architecture\ModuleBoundaryTest` already reserved `QueuePresence`
  as a forbidden `apps/web/app` folder name since Phase 1 — no test
  changes were needed to keep enforcing the boundary through this phase.

## 6. Database changes

`packages/QueuePresence/database/migrations/`:
- `2026_08_01_000000_create_presence_sessions_table.php`
- `2026_08_10_000000_create_presence_gps_pings_table.php`
- `2026_08_17_000000_create_presence_evidence_photos_table.php`
- `2026_08_24_000000_add_ended_at_to_presence_sessions_table.php`
- `2026_08_24_000001_create_presence_confidence_scores_table.php`
  (append-only; `computed_at` at microsecond precision — see §7.1)

`apps/web/database/migrations/`:
- `2026_08_24_000002_create_audit_events_table.php` (shared, platform-wide;
  no `actor_id` column — whichever actor field is relevant already varies
  per event type and lives in `payload`)

## 7. Issues found and fixed

Both were found only during the mandatory real-browser acceptance test
(Sprint 7) — neither was reachable by Pest's `Storage::fake()`-based
feature tests, which is exactly why that manual step was required before
sign-off.

### 7.1 Confidence-score ordering bug

`EloquentConfidenceScoreRepository`'s "latest score for a session" query
ordered by `computed_at`, but `ConfidenceScoreModel` used Eloquent's
default date format (`Y-m-d H:i:s`), silently truncating microseconds
before the value ever reached Postgres — despite the column being
`timestamp(6)`. Two recomputations within the same real-world second (a
routine occurrence: a GPS ping immediately followed by a photo upload)
tied, and the tie broke toward the wrong row, showing a stale/lower
confidence tier in the browser. **Fixed** by overriding `$dateFormat` to
`'Y-m-d H:i:s.u'` on the model. Regression-tested at the repository level.

### 7.2 Silent evidence-storage failure

`LocalPrivateEvidenceStorage` never checked `Storage::put()`'s return
value. `filesystems.php` configures the local disk with `'throw' =>
false`, so a failed write returns `false` instead of throwing — the code
proceeded as if the write had succeeded, recording an
`EvidencePhotoRecord` (and counting it toward the confidence score) for a
photo that was never actually persisted to disk. **Fixed** by adding
`EvidenceStorageFailed`, thrown when `put()` returns `false`, caught by
`UploadEvidencePhotoController` and mapped to a translated 500 response.
Regression-tested at both the application-service (fakes) and HTTP
(bound failing double) layers.

(The specific failure that surfaced 7.2 during testing was itself caused
by this session's own root-owned debug artifacts left in
`storage/app/private` — a long-running PHP-FPM worker's stale view of a
bind-mounted directory it didn't create, the same class of bug as the
Vite/PHPStan directory-staleness issues from earlier phases, not a
pre-existing defect on its own. The missing return-value check it
exposed was real regardless and is now fixed.)

## 8. Test and validation results

- `packages/QueuePresence`: **73/73 passed** (155 assertions) — aggregate
  lifecycle, application-service orchestration with in-memory fakes,
  Eloquent repository round-trips, the pure scorer's boundary cases, and
  GD-based metadata stripping.
- `packages/Queues`: **81/81 passed** (247 assertions), including its
  real PostGIS/Postgres integration tests — unaffected by this phase,
  confirmed not skipped.
- `apps/web`: **66/66 passed** (270 assertions) — presence capture,
  evidence capture, the audit sink (including proof it also audits an
  existing Queues event with zero Queues-side changes), and translation
  parity.
- All 6 new/changed migrations applied cleanly against real Postgres;
  schema (including the partial unique index and microsecond-precision
  column) confirmed via `psql`.
- Larastan: **no errors** on `packages/QueuePresence`, `packages/Queues`,
  and `apps/web`.
- Pint: clean on both packages; `apps/web` has the same 12 pre-existing
  style issues as Phase 1, unchanged.
- Production frontend build (`npm run build`): succeeded, 672 modules.
- **Manual browser acceptance test**: registration/login → Discover →
  Claim presence → Start session (Unverified/0) → Record GPS (→ Location
  Verified) → Upload evidence photo (→ Evidence Verified) → End session,
  with `audit_events` confirmed to record the real HTTP-driven actions
  and correctly omit every individual GPS ping. Confirmed by the product
  owner as passed and accepted.

## 9. Lessons learned

- **The Windows-host/Linux-container bind-mount staleness pattern
  recurred a fourth time**, now specifically affecting PHP-FPM's
  long-running workers and newly-created directories (not just file
  watchers or caches as in Phases 0–1). The fix is the same as always:
  suspect a long-running process before doubting a code change, and
  `--force-recreate` (or in this case, a plain `restart` to recycle
  workers) rather than assume the code is wrong.
  [[feedback_docker_bind_mount_staleness]]
- **Eloquent's default date format silently discards microseconds**,
  independent of the underlying column's declared precision. Any table
  where insertion order within the same second matters (append-only
  history tables ordered by a timestamp, specifically) needs an explicit
  `$dateFormat` override — the column type alone is not enough.
- **`'throw' => false` on a filesystem disk means callers must check
  return values themselves.** Laravel's own exception-suppression
  config for filesystem operations does not mean failures are
  impossible — it means they become silent unless the calling code
  explicitly checks for them.
- **Browser-based Geolocation permission prompts cannot be resolved by
  this session's browser-automation tooling.** A page-level override of
  `navigator.geolocation` (replacing the whole object via
  `Object.defineProperty`, not just reassigning the method) was needed to
  exercise the real HTTP/backend path in a real browser — a reusable
  technique worth remembering for any future geolocation-dependent
  acceptance test in this project.
- **A real-browser acceptance test is not a formality** — this phase's
  two genuine defects (§7) both involved conditions `Storage::fake()`
  cannot reproduce (real filesystem timing/precision, real write
  failures). The Pest suite passing 100% did not mean the feature worked
  end-to-end; only exercising it through a real browser against real
  infrastructure did.

## 10. Phase 3 readiness assessment

Phase 2 meets its exit criteria and provides what Phase 3 (Auctions &
Bids) needs to build on:

- A `PresenceSession` aggregate and confidence score/tier that Auctions
  can require as a precondition for creating an auction (e.g. minimum
  tier to list).
- A second proven package-per-bounded-context implementation
  (`packages/QueuePresence`), confirming the pattern generalizes cleanly
  beyond Queues — including the new "framework-boot-dependent adapters
  live in apps/web" refinement, which Bids/Auctions can reuse as needed.
- A working, generic audit sink that Bids/Auctions' own domain events can
  plug into for free, provided they implement `AuditableAction` where
  appropriate.
- CI, translation-parity, and module-boundary architecture tests already
  green and enforced — Auctions/Bids inherit these guardrails
  immediately.

**No blockers identified for Phase 3.** Per explicit instruction, Phase 3
implementation will not begin until separately authorized.
