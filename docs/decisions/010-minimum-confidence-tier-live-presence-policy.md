# ADR 010: Minimum Confidence Tier and Live-Presence Policy for Auctions

## Status

Accepted — 2026-09-02.

## Context

`CLAUDE.md` states two related, but distinct, core business rules:

- "A seller must be physically present before publishing a position. GPS
  alone is not sufficient verification."
- "A seller must remain near the queue while an auction is active."

The first is a **precondition** for creating/publishing an auction. The
second is a **continuous** requirement for the lifetime of an already-created
auction. ADR-008 (Phase 2) defined three reachable confidence tiers for v1 —
Unverified, Location Verified, Evidence Verified — and the
`SellerPresenceVerification` port defined in ADR-009 gives Auctions a way to
read a seller's current tier and their most recent within-geofence GPS
signal. This ADR decides how Auctions uses that port to satisfy both rules.

## Decision

### 1. Minimum tier to create/publish an auction: Evidence Verified

Location Verified is reachable through GPS signals alone: ADR-008's own
scoring table caps a GPS-only session at 80 points, strictly below the
140-point Evidence Verified threshold. Requiring only Location Verified would
therefore let a seller publish an auction on GPS alone — a direct
contradiction of "GPS alone is not sufficient verification." Evidence
Verified is the only tier that structurally requires an evidence photo tied
to a within-geofence ping (ADR-008 §2–§3), so it is the only tier consistent
with the existing rule.

**Workflow implication**: a seller must capture the evidence photo during
their presence session (already built in Phase 2) before they can create an
auction. The UI must present this as a clear, localized gate — e.g. "Take a
photo to unlock auction creation" — not a generic validation error, since it
is a deliberate product requirement, not an incidental one.

### 2. The tier gate is checked once, at creation — not re-checked as a tier

ADR-008 deliberately has no time decay: a signal recorded once counts for the
rest of the session. Re-reading the confidence *tier* later would therefore
be meaningless — it cannot regress on its own. The Evidence Verified check is
a one-time precondition evaluated when the auction is created (and, if a
separate publish step is introduced later, at publish time too, using the
same port call). It is not part of the continuous "remain near the queue"
requirement, which is a different check entirely (§3).

### 3. Live proximity is a separate, continuous check owned by Auctions

"Remain near the queue while an auction is active" is satisfied using the
`lastWithinGeofencePingAt` field on `PresenceVerificationSnapshot` (ADR-009),
not by re-reading the confidence tier or score. Auctions defines and owns its
own staleness policy on top of that timestamp — QueuePresence has no opinion
on what "stale" means, matching ADR-008's own framing of this as "a live
requirement enforced by Auctions against an active session, not a
retroactive rewrite of this session's own confidence history."

This requires the seller's existing Phase 2 client to keep sending periodic
GPS pings while an auction they created remains open — an operational/UX
dependency on already-built functionality, not new signal plumbing. The exact
staleness window (e.g. "no within-geofence ping in the last N minutes") is
left to be set as an implementation parameter in the sprint that builds this
check (proposed Sprint 4 — see the Phase 3 sprint breakdown), not fixed by
this ADR, since it is a tuning decision with no fraud/usage data yet to
calibrate against — the same posture ADR-008 already took toward its own
thresholds.

### 4. Consequence of proximity loss and session-ending — explicitly deferred

This ADR does **not** decide what happens when live proximity is lost while
an auction is active (auto-cancel, freeze-new-bids-but-honor-the-current-one,
or a grace period before cancellation). That is a product decision with
direct consequences for buyer trust and the escrow flow (ADR-004), and is
called out as an open question for the sprint that implements §3, not
resolved speculatively here.

A second, related scenario is equally undecided and is called out here
explicitly so it is not conflated with, or silently resolved alongside,
proximity loss: **the seller deliberately ends the `PresenceSession` (via
`PresenceSessionService::end()`) while an auction backed by that session is
still active.** Unlike proximity loss, this is an intentional user action,
not a lapsed signal — it may warrant different handling (e.g. blocking the
`end()` call outright while an active auction references the session, versus
allowing it and cancelling/pausing the auction). This ADR takes no position
on which. Both open questions — proximity loss and deliberate session-ending
during an active auction — are deferred together to Phase 3 Sprint 4, to be
resolved as explicit product decisions before that sprint's code is written.
Neither must be implemented ad hoc during development in the interim.

## Consequences

- Auction creation now has a hard precondition — Evidence Verified — enforced
  through the ADR-009 port at the moment `AuctionService::create()` (or
  equivalent) runs. No exceptions or bypass paths are introduced.
- The confidence-tier check and the live-proximity check are two separate
  code paths with two separate lifecycles (once at creation vs. continuously
  while active) — they must not be merged into a single "is verified" check,
  or the no-decay guarantee from ADR-008 would be silently violated.
- The exact live-proximity staleness threshold, the consequence of losing
  it, and the consequence of the seller deliberately ending their
  `PresenceSession` while an active auction still references it all remain
  open and must be resolved as part of Phase 3 Sprint 4 before that sprint's
  code is written — not invented ad hoc during implementation.
