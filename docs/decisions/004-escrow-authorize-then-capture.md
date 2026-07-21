# ADR 004: Escrow via Authorize-Now, Capture-at-Transfer

## Status

Approved — 2026-07-21.

## Decision

Payments authorizes funds on the buyer's payment method at the moment a bid
wins, and captures those funds only once the transfer is confirmed. Funds
are never captured into, and never sit in, a RowBuddy- or Stripe-controlled
intermediate balance while awaiting transfer.

## Rationale

Holding captured customer funds in a platform-controlled balance pending a
future event is the escrow pattern with the highest money-transmission
licensing exposure. Keeping funds on the buyer's card/issuer until the
handoff is confirmed avoids that exposure almost entirely, at the cost of
some flexibility in timing.

## Consequences

- Card authorization holds generally expire around 5–7 days depending on
  card network/issuer. This is a hard ceiling on **auction duration +
  transfer window combined** — the full span from "bid wins" to "transfer
  confirmed" must fit inside the authorization's validity window, with
  margin for retry.
- The Auction state machine must reject or flag any seller-configured
  transfer window that would risk exceeding the authorization validity
  window.
- Payments must implement a defined re-authorization flow (re-auth and
  notify both parties) for transfers approaching authorization expiry,
  rather than allowing a silent capture failure.
- Stripe Connect charge type: destination charge (or separate charges/
  transfers) with manual capture, fee computed server-side only.
