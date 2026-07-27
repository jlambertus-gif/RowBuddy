# ADR 009: Auctions–Presence Verification Contract

## Status

Accepted — 2026-09-02.

## Context

Phase 3 (Auctions & Bids) needs to gate auction creation on a seller's
presence/confidence state, which lives entirely inside `packages/QueuePresence`
(Phase 2). Per `CLAUDE.md`'s module-boundary rule, Auctions must never depend
on `packages/QueuePresence`'s Eloquent models, its `PresenceSessionService`
concrete class, or any other QueuePresence internals — cross-module reads must
go through an explicit application-service interface, not direct model access.

This ADR defines that interface: how Auctions asks "is this seller
sufficiently verified for this queue, and are they still nearby?" without the
two packages depending on each other in either direction. It also resolves
what happens, structurally, when an auction is created against a specific
`PresenceSession` — deliberately as an MVP-scoped restriction, since the
domain has no `Position` concept yet (see §4).

Three shapes were considered: a synchronous read port, a read-model/
projection owned by Auctions, and a domain-event-driven projection. A
synchronous port was chosen — see the architecture review that preceded this
ADR for the full comparison. In short: this is a single-Postgres-instance
modular monolith, not a distributed system, so the cost a projection would pay
to buy read-time decoupling (a new table, listeners to keep it in sync, a
rebuild/backfill story) is not justified yet, and the gate this port
protects is a real business action (publishing a paid listing) where a stale
read is worse than a synchronous one.

## Decision

### 1. Synchronous read port, owned by Auctions

The contract is defined *by the consumer*, in the consumer's own vocabulary —
the same pattern already established by `QueueGeofenceLookup`
(`packages/QueuePresence/src/Contracts/QueueGeofenceLookup.php`, owned by
QueuePresence to query Queues). Symmetrically:

```php
namespace RowBuddy\Auctions\Contracts;

interface SellerPresenceVerification
{
    public function verificationFor(string $sellerId, string $queueId): ?PresenceVerificationSnapshot;
}
```

- Lives in `packages/Auctions/src/Contracts/`.
- Implemented by an adapter in `apps/web` (the composition root, the only
  place allowed to depend on both packages' internals) — e.g.
  `App\Infrastructure\QueuePresenceSellerVerification` — which calls
  QueuePresence's own public application service (never its Eloquent models,
  never its repositories directly) and translates the result into the DTO
  below.
- `packages/QueuePresence` has no knowledge of this interface or of Auctions
  at all. The dependency runs one way only: Auctions → port → adapter →
  QueuePresence's public service.

### 2. Data exposed — `PresenceVerificationSnapshot`

A value object owned by Auctions (`packages/Auctions/src/ValueObjects/`), not
a reuse of any QueuePresence type:

```php
final class PresenceVerificationSnapshot
{
    public function __construct(
        public readonly ConfidenceTier $tier,       // Auctions' own enum, mapped from QueuePresence's
        public readonly int $points,
        public readonly DateTimeImmutable $computedAt,
        public readonly bool $sessionActive,
        public readonly ?DateTimeImmutable $lastWithinGeofencePingAt,
    ) {}
}
```

No GPS coordinates, no evidence-photo paths, no audit history — only what a
gating or live-proximity decision needs. `lastWithinGeofencePingAt` exists
specifically to support the live-presence policy defined in ADR-010; it is
not consumed by anything else in this ADR.

### 3. Consistency and failure behavior — fail closed

- The read is always current as of the call (no caching layer) — cheap here
  because it is one query against one Postgres instance, not a network hop.
- No `PresenceSession` found for `(sellerId, queueId)` → the port returns
  `null`. Auctions treats `null` exactly like `ConfidenceTier::Unverified`:
  the auction-creation gate fails.
- Any unexpected error from the adapter (e.g. the underlying QueuePresence
  call throws) must propagate and block auction creation — it must never be
  swallowed into an implicit "treat as verified." This mirrors the existing
  fail-closed precedent for jurisdiction/restricted-category gating
  (`docs/legal/jurisdiction-requirements.md` — no rule for a country blocks,
  it never defaults to allow).

### 4. PresenceSession-to-auction linkage — MVP restriction only

**One `PresenceSession` may back at most one auction in the MVP. Future
phases may revisit this once `Position` becomes a first-class domain
concept.**

This is intentionally *not* stated as a permanent domain invariant. The
domain has no modeled `Position` entity yet — only `Queue` (a place) and
`PresenceSession` (a presence claim against that place) exist. Baking a
permanent one-to-one cardinality rule between sessions and auctions into the
model now would encode a guess about what a "position" is before product
defines it. Instead:

- The exclusivity is enforced by **Auctions**, not QueuePresence. When an
  auction is created referencing a `PresenceSession`, Auctions stores that
  session's ID as a plain string reference on its own aggregate/table — no
  cross-module foreign key, consistent with the existing rule that
  persistence never leaks across module boundaries.
- Enforcement is a unique constraint on that reference column in Auctions'
  own schema — the same partial-unique-index technique already used twice in
  this codebase (Queues' restricted-categories uniqueness, QueuePresence's
  one-active-session-per-seller-per-queue).
- QueuePresence remains completely unaware that an auction was ever created
  from one of its sessions. The dependency direction established in §1 is
  preserved.
- This constraint is documented here as an **MVP restriction**, not a
  business rule with product rationale of its own — it exists to keep the
  system simple and unambiguous until `Position` is modeled, at which point
  this ADR (or a successor) should be revisited.

### 5. Testability

Auctions' own tests fake `SellerPresenceVerification` in-memory — no
QueuePresence code, no Eloquent, no framework boot required, matching the
existing `InMemory*` fake pattern already used throughout this codebase
(e.g. `InMemoryEvidenceStorage`). The `apps/web` adapter gets its own thin
integration test that boots the real QueuePresence service and confirms the
translation to `PresenceVerificationSnapshot` is correct.

### 6. Future evolution

If Auctions is ever extracted into its own service (per ADR-001's preserved
option) or read load eventually demands it, the port boundary already
isolates that change: swap the `apps/web` adapter for an HTTP client, or
introduce a local projection inside Auctions fed by QueuePresence's domain
events — neither requires touching Auctions' domain or application code,
since both already depend only on the `SellerPresenceVerification` interface.

## Consequences

- `packages/Auctions` gains a `Contracts/SellerPresenceVerification` port and
  a `ValueObjects/PresenceVerificationSnapshot`, defined before any auction
  persistence or HTTP layer exists — the same "decide the contract before the
  sprint that needs it" discipline ADR-008 used for the scoring model.
- `apps/web` gains one new composition-root adapter bridging Auctions and
  QueuePresence, alongside the existing `EloquentQueueGeofenceLookup` and
  `LocalPrivateEvidenceStorage`.
- No change to `packages/QueuePresence` is required by this ADR — it stays
  fully unaware of Auctions.
- The one-session-per-auction constraint is explicitly provisional; anyone
  implementing `Position` in a later phase must re-read this ADR's §4 before
  assuming it is permanent.
