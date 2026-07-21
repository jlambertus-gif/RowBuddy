# ADR 006: Fee Model — Percentage of Winning Bid, Buyer Pays On Top

## Status

Approved — 2026-07-21.

## Decision

The platform fee is a percentage of the winning bid, charged to the buyer
in addition to the bid amount. The seller receives the full winning bid
amount, minus standard payment-processing costs only.

## Rationale

This is the simplest model to explain to sellers: their payout is exactly
the winning bid, with no platform deduction to reason about. It also
matches common marketplace convention where the platform fee is presented
as a distinct, transparent buyer-side line item at checkout.

## Consequences

- Stripe Connect charge type should be a destination charge with
  `application_fee_amount` (or an equivalent separate-charges-and-transfers
  model).
- The fee amount must be computed server-side only; it must never be
  accepted from client input.
- The exact fee percentage, and whether it varies by country, remains open
  — see `docs/product/claude-mvp-analysis.md` §10.2.
