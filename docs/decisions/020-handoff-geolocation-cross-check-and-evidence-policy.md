# ADR 020: Handoff Geolocation Cross-Check and Evidence Policy

## Status

Accepted — 2026-10-23.

## Context

`claude-mvp-analysis.md` §3.1 requires "independent geolocation from both
parties" at handoff confirmation — the same "GPS alone is not sufficient"
principle QueuePresence already applies to presence verification (ADR-008),
now recurring at the highest-stakes moment in the system: the trigger for
real money capture. `shared-kernel`'s `Geofence` value object already
names "Transfers' handoff cross-check" as an anticipated consumer in its
own docblock, so the geometry itself needs no new code — only the port
that supplies a queue's geofence to `packages/Transfers`, and the policy
for how strictly it's enforced, remain to decide. Optional photo evidence
at handoff is also named (§3.1) as a friendly-fraud mitigation.

An earlier draft of this ADR modeled that evidence as purely an
`apps/web`-level storage concern — a photo the controller could save
with no representation in the `Transfer` aggregate at all. That was
rejected: Phase 6 (Disputes) explicitly depends on evidence "aggregated
from other contexts (read-only)" (`claude-mvp-analysis.md` §4 item 10),
and evidence that was never modeled as a domain concept in Phase 5 would
force Phase 6 to either read raw infrastructure state directly (breaking
the module-boundary discipline every other cross-context read in this
project respects) or force a redesign of `Transfer` itself once Phase 6
begins. This revision makes evidence a first-class, queryable part of
the aggregate from the start, even though the MVP does not require it on
every transfer.

## Decision

### 1. A new, Transfers-owned geofence lookup port

```php
namespace RowBuddy\Transfers\Contracts;

interface TransferGeofenceLookup
{
    public function geofenceForAuction(string $auctionId): Geofence;
}
```

mirroring `QueueGeofenceLookup`'s exact shape (QueuePresence-owned,
Phase 2) and `SellerPresenceVerification`'s (Auctions-owned, Phase 3) —
each consuming module gets its own copy of this kind of port rather than
sharing one, per this project's established discipline. Implemented by a
new `apps/web` adapter bridging to `packages/Queues`' existing geofence
data — `packages/Transfers` gains no dependency on `packages/Queues`'
internals.

### 2. Confirmation-time enforcement, not continuous tracking

Unlike QueuePresence's *continuous* proximity monitoring while an auction
is active (ADR-011), the handoff cross-check is a **point-in-time**
validation: at the moment either party calls their confirmation endpoint,
that party's submitted `GeoPoint` is checked against
`TransferGeofenceLookup::geofenceForAuction()`'s result via
`Geofence::contains()` — reusing the existing method directly, no new
geometry. If the point falls outside the geofence, the confirmation
attempt is rejected by the application service (`TransferConfirmationService`,
introduced by ADR-017) **before** `Transfer::confirmBySeller()`/
`confirmByBuyer()` is ever called — the aggregate never sees a
rejected attempt, mirroring `BidService`'s rejection shape (ADR-017 §4).

### 3. No device-integrity/anti-spoofing checks beyond the geofence

Mock-location detection, device attestation, and IP-consistency checks
(named as open items in `claude-mvp-analysis.md` §2 item 15 and §3.1's
fraud table) are **not** built in this phase — the geofence check alone
is the MVP's handoff safeguard, the same posture QueuePresence's own v1
confidence scoring (ADR-008) took toward GPS spoofing generally. This is
an accepted limitation, not an oversight, and should be revisited if real
abuse is observed, the same posture this project has taken toward every
other provisional MVP threshold so far.

### 4. Evidence is a first-class, extensible concept on `Transfer` — not a side-channel

`Transfer` gains its own repeatable evidence concept from Sprint 1, even
though the MVP does not require populating it on every transfer:

```php
enum TransferEvidenceType: string
{
    case Photo = 'photo';
}
```

Deliberately an extensible enum — Phase 6 is the anticipated reason more
cases get added later (e.g. a dispute-submitted photo, a written
statement), without redesigning anything that exists today.

```php
final class TransferEvidenceRecord
{
    public function __construct(
        public readonly TransferEvidenceType $type,
        public readonly string $storageReference,
        public readonly string $submittedBy,
        public readonly DateTimeImmutable $submittedAt,
    ) {}
}
```

```php
final class Transfer
{
    // ...existing fields (ADR-017 §2)...

    /** @var list<TransferEvidenceRecord> */
    private array $evidenceRecords = [];

    public function attachEvidence(
        TransferEvidenceType $type,
        string $storageReference,
        string $submittedBy,
        ClockInterface $clock,
    ): void {
        $record = new TransferEvidenceRecord($type, $storageReference, $submittedBy, $clock->now());
        $this->evidenceRecords[] = $record;
        $this->recordedEvents[] = new TransferEvidenceAttached(
            $clock, $this->id, $type, $storageReference, $submittedBy,
        );
    }

    /** @return list<TransferEvidenceRecord> */
    public function evidenceRecords(): array
    {
        return $this->evidenceRecords;
    }
}
```

**`attachEvidence()` is deliberately not guarded by `Transfer`'s own
confirmation status.** Evidence is orthogonal to the
`Issued`/`Confirmed`/`Expired`/`Cancelled` state machine (ADR-017 §3) —
a dispute (Phase 6) may need to attach evidence well after a transfer
has already reached a terminal state, and gating `attachEvidence()` to
"only while `Issued`" or similar would be exactly the kind of premature
narrowing this revision exists to avoid.

**Persistence**: a dedicated `transfer_evidence` table (one-to-many from
`transfers`, not a JSON blob column) — evidence is zero-or-more per
transfer by design, and Phase 6 will want to query/join it directly, the
same reason `presence_confidence_scores` is its own append-only table
rather than a column on `presence_sessions` (Phase 2 precedent).

**The actual storage mechanism stays an infrastructure concern**, via a
new `TransferEvidenceStorage` port — Transfers' own copy of the
`EvidenceStorage` shape (module-boundary discipline, mirroring §1's
`TransferGeofenceLookup` rather than reusing QueuePresence's interface
directly), reusing the same private-disk-plus-signed-URL-plus-
EXIF-stripping mechanics `LocalPrivateEvidenceStorage` already
established in Phase 2. `storageReference` is exactly the opaque handle
that port returns — the aggregate never knows about disks, EXIF, or
signed URLs, only that a reference exists.

**Still not mandatory in the MVP** — no confirmation flow *requires*
calling `attachEvidence()`, and a `Transfer` with zero evidence records
remains a completely valid, expected shape for most confirmed transfers.
What changes from the rejected draft is that evidence is now a real,
queryable, first-class part of the aggregate's own model from day one —
so Phase 6 extends an existing concept (new `TransferEvidenceType` cases,
querying `transfer_evidence` directly) rather than retrofitting one into
an aggregate that never anticipated it.

### 5. Confirmation geolocation is a core fact, not "evidence"

To avoid conflating two different things: the `GeoPoint`s captured at
`confirmBySeller()`/`confirmByBuyer()` (ADR-017 §4) remain fields
directly on `Transfer` itself, never `TransferEvidenceRecord` entries —
they are load-bearing facts the confirmation state machine and this
ADR's own geofence gate (§2) depend on, not supplementary proof.
`TransferEvidenceRecord` is reserved for genuinely optional, repeatable,
supplementary material (photos now; whatever Phase 6 adds later) layered
on top of facts that exist regardless of whether any evidence is ever
attached.

### 6. Evidence is captured for Phase 6, not resolved by Phase 5

Every confirmation's `GeoPoint`s, timestamps, and any attached
`TransferEvidenceRecord` are retained precisely so a future Phase 6
dispute can read them — read-only, cross-context, per
`claude-mvp-analysis.md` §4 item 10's design for Disputes. Phase 5 builds
no dispute-resolution logic itself; it only ensures nothing worth
disputing over is modeled as unreachable infrastructure state.

## Consequences

- `packages/Transfers` gains `TransferGeofenceLookup` and
  `TransferEvidenceStorage`; `apps/web` gains two new adapters, following
  exactly the composition-root pattern established since Phase 2.
- `Transfer` gains `TransferEvidenceType`, `TransferEvidenceRecord`,
  `attachEvidence()`, `evidenceRecords()`, and a `TransferEvidenceAttached`
  domain event (implementing `AuditableAction`, captured by the existing
  generic audit sink with zero new wiring) — real additions to the
  aggregate's public surface, not merely to its persistence layer.
- A new `transfer_evidence` table, one-to-many from `transfers`.
- No changes to `packages/Queues`, `packages/QueuePresence`, or
  `shared-kernel` — `Geofence`/`GeoPoint` are consumed exactly as they
  already exist.
- A rejected (outside-geofence) confirmation attempt produces no
  `Transfer` state change and, per ADR-017 §6, is not itself a domain
  event — any fraud-monitoring logging of rejected attempts is an
  application-layer concern for whichever sprint implements confirmation,
  not a domain concept this ADR mandates.
- Anti-spoofing beyond the geofence check is explicitly out of scope and
  named as an accepted MVP limitation in whatever completion report
  eventually closes this phase.
- Phase 6, when it begins, extends `TransferEvidenceType` and queries
  `transfer_evidence` directly rather than redesigning `Transfer` to
  retrofit an evidence concept that didn't previously exist.

## References

- `docs/product/claude-mvp-analysis.md` §2 item 15, §3.1, §3.2, §4 item 10
- ADR-008 (confidence-scoring model v1 — the "no single signal is
  sufficient" precedent this ADR extends to the handoff moment)
- ADR-011 (QueuePresence's continuous proximity check — the point-in-time
  contrast this ADR draws)
- ADR-017 (`Transfer::confirmBySeller()`/`confirmByBuyer()`, gated by this
  ADR's geofence check; the aggregate this ADR's evidence model extends)
- Phase 5 architecture review (this conversation, §6, §9, §10, §18 risk #7)
