# ADR 008: Confidence Scoring Model v1

## Status

Accepted — 2026-08-03.

## Context

Phase 2 (Presence & Trust) needs the multi-signal confidence-scoring
engine described in `docs/product/verification-model.md` and
`docs/product/claude-mvp-analysis.md` §7.2: "modeled as a score with
derived tiers... computed by a pure scoring function, not a mutable
status field set once." The five canonical tiers are Unverified →
Location Verified → Evidence Verified → Community Verified → Transfer
Completed.

Sprint 1 built the `PresenceSession` lifecycle; Sprint 2 added
persistence for the session itself (not yet its individual signals).
This ADR defines the actual scoring function before Sprint 3 implements
it, since the weights/thresholds are a product judgment call that should
be reviewable on its own, not buried inside a code review.

**v1 signal set** (approved for Phase 2, explicitly excluding community
confirmation, device/fraud signals, and video evidence — those are later
phases' work):

- GPS location (was a ping within the queue's geofence)
- GPS accuracy (meters)
- Presence duration (elapsed time since the session started)
- A single evidence photo

Because Community Verified and Transfer Completed depend on other users'
confirmations (not built yet) and Transfers (Phase 5, not built yet),
**v1's scoring function cannot produce those two tiers** — its ceiling is
Evidence Verified. The tier enum still declares all five, so later phases
can extend the scorer without a breaking change to the vocabulary.

## Decision

### 1. A pure, dependency-free scoring function

`ConfidenceScorer::score()` takes plain, already-computed primitives —
not a `PresenceSession`, not a repository, not a `ClockInterface` — and
returns a `ConfidenceScore` value object (points + tier). This is a
deliberate stronger form of "pure function" than most of this module's
other code: no collaborators at all, trivially unit-testable, and
reusable from wherever a caller later assembles those primitives
(an application service in a later sprint, once individual signals are
actually persisted and duration is computed via the seller's `Queue`
geofence and a real `ClockInterface`).

```php
final class ConfidenceScorer
{
    public function score(
        bool $hasWithinGeofencePing,
        ?float $bestGpsAccuracyInMeters,
        int $presenceDurationInSeconds,
        bool $hasEvidencePhoto,
    ): ConfidenceScore;
}
```

Checking whether a given GPS ping falls inside a queue's geofence is
`Geofence::contains()` (shared-kernel) — the scorer is never given raw
coordinates and never repeats that geometry itself; it only receives the
already-decided boolean.

### 2. Points and thresholds

| Signal | Condition | Points |
|---|---|---|
| GPS location | at least one ping recorded inside the geofence | +40 |
| GPS accuracy | best (smallest) accuracy among within-geofence pings ≤ 20m | +20 |
| GPS accuracy | best accuracy ≤ 50m (and not already scored above) | +10 |
| GPS accuracy | best accuracy > 50m, or no within-geofence ping | +0 |
| Presence duration | ≥ 600s (10 min) since session start | +20 |
| Presence duration | ≥ 120s (2 min) (and not already scored above) | +10 |
| Presence duration | < 120s | +0 |
| Evidence photo | a photo was recorded, **and** a within-geofence ping exists | +100 |
| Evidence photo | a photo was recorded but no within-geofence ping exists | +0 |

Maximum score with no evidence: 80 (40 + 20 + 20). Maximum score with
evidence: 180.

Tiers:

- **Unverified**: score < 40 (no within-geofence ping at all).
- **Location Verified**: 40 ≤ score < 140.
- **Evidence Verified**: score ≥ 140.

140 is chosen deliberately above the 80-point GPS-only ceiling: **no
combination of GPS signals alone can ever reach Evidence Verified.** A
photo is mandatory for that tier, matching the tier's name and the
product principle that no single signal is sufficient.

### 3. Evidence without location contributes nothing

An evidence photo recorded on a session with zero within-geofence pings
scores 0 for that photo, not just "less." The tier hierarchy places
Evidence Verified *above* Location Verified — evidence is modeled as
strengthening an already-plausible location claim, not replacing it. A
photo with no supporting GPS signal at all is exactly the "reused/
fabricated evidence" fraud pattern flagged in
`docs/product/claude-mvp-analysis.md` §3.1; scoring it at zero doesn't
detect that fraud (device attestation and forensic checks are later-phase
work) but it does avoid rewarding it.

### 4. Best-of, not average-of, for repeated GPS pings

Accuracy scoring uses the single best (lowest-accuracy-value) among
within-geofence pings, not an average across the whole session. A noisy
environment producing many mediocre readings and one good one should not
be penalized relative to a session with only one, equally good, reading
— v1 has no per-ping weighting scheme (that's the "no
device/fraud-signal" simplicity this phase explicitly scoped out).

### 5. No time decay, no signal expiry

A signal recorded once counts for the rest of the session. v1 does not
model staleness (e.g., a within-geofence ping from ten minutes ago no
longer "counting" if the seller has since left) — Phase 3's "seller must
remain near the queue while an auction is active" rule is a *live*
requirement enforced by Auctions against an *active* session, not a
retroactive rewrite of this session's own confidence history.

## Consequences

- `packages/QueuePresence` gains a `Scoring/ConfidenceScorer` service and
  `ValueObjects/ConfidenceTier`, `ValueObjects/ConfidenceScore`, alongside
  the existing `PresenceSession` lifecycle/persistence surface.
- No `presence_signals` table exists yet — individual GPS pings and
  evidence uploads are not yet durably queryable, only the primitives
  needed for a single `score()` call. Wiring the scorer to real, persisted
  signal history is explicitly a later sprint's job (recording individual
  signals durably, computing `bestGpsAccuracyInMeters` /
  `hasWithinGeofencePing` from that history, and computing
  `presenceDurationInSeconds` via a real `ClockInterface`).
- The weights and thresholds above are a v1 product judgment call, not
  derived from data (there is no fraud/outcome data yet to calibrate
  against). Revisit once real usage or a fraud incident gives a concrete
  reason to move a threshold — don't tune speculatively.
