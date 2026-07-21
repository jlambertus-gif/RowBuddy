# ADR 003: Line-Standing Service Legal Framing

## Status

Approved — 2026-07-21.

## Decision

RowBuddy positions its product, Terms of Service, and marketing copy around
a **paid line-standing / time-brokering service**: the fee compensates a
seller for time and effort already spent waiting, plus a verified physical
handoff. RowBuddy does not describe the transaction as the sale of a queue
position, and does not claim the seller owns or transfers any property
right in that position.

## Rationale

A place in a line is not property; nobody can legally transfer ownership of
it, and the platform already discloses that organizer acceptance is never
guaranteed. "Professional line-sitting" is an established, generally legal
service category in many jurisdictions. "Selling positions/tickets/seats
you don't own" is a much more legally contested category (anti-scalping,
anti-touting statutes). Consistent framing as the former is RowBuddy's
primary legal defense and should not be diluted by inconsistent internal or
external language.

## Consequences

- ToS, privacy policy, and all consumer-facing copy must consistently use
  "compensation for time/effort waited" and "verified handoff" language,
  never "buy/sell a position."
- Internal/technical naming (the `Auctions` module, "winning bid") is
  unaffected — this decision governs external, legal, and product-facing
  language only.
- Per-jurisdiction legal review (see `docs/legal/jurisdiction-requirements.md`)
  should confirm this framing holds before enabling a new market, since
  local line-sitting precedent varies.
