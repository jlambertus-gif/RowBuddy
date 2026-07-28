# ADR 014: Payments–Auctions Boundary — Independent Lifecycle, No `AuctionStatus` Extension

## Status

Accepted — 2026-10-06.

## Context

The Phase 4 architecture review identified that `Auction`'s implemented
state machine (`AuctionStatus`: Open/Closing/Won/Expired/Cancelled,
`packages/Auctions/src/Auction.php`) has no transitions past `Won`. The
original sketch in `docs/product/claude-mvp-analysis.md` §7.1 anticipated
a longer chain — `awaiting_payment → awaiting_transfer → transferred →
completed`, with side branches to `disputed`/`cancelled`/`refunded` —
but none of that was built, and Phase 3 closed with `Won` as a true
terminal state (`guardStatus`/`guardActiveLifecycle` permit transitions
only out of `Open`/`Closing`).

Two ways to give Payments what it needs were possible: extend
`AuctionStatus` with payment/transfer/dispute/settlement states, or keep
`Auction` ignorant of everything downstream of winner selection and let
Payments track its own lifecycle, correlated by `auctionId` and
`winningBidId`. José resolved this explicitly: Payments owns the
complete financial lifecycle; `Auction` remains in its terminal `Won`
state; `AuctionStatus` is not extended.

## Decision

1. **`AuctionStatus` is closed to extension for payment, transfer,
   dispute, or settlement concerns.** It remains exactly `{Open, Closing,
   Won, Expired, Cancelled}`. `Won` is `Auction`'s genuine terminal state
   for the auction's own lifecycle — it never transitions further,
   regardless of what happens to payment, transfer, or dispute
   afterward. `packages/Auctions` receives zero new states, fields, or
   events as a result of Phase 4.

2. **Payments owns and persists its own lifecycle independently**, keyed
   by `(auctionId, winningBidId)` — never by writing to, or extending,
   `Auction`. Payments' own aggregate (`PaymentIntent`, per ADR-004/§7.3)
   tracks authorized/captured/held/released/refunded/cancelled entirely
   within `packages/Payments`, with no corresponding field on `Auction`.

3. **This extends, one hop further downstream, the "consumer owns the
   port, the upstream aggregate stays ignorant" pattern already
   established twice**: Bids never made `Auction` aware of bid-level
   concepts (`AuctionGateway` is Bids-owned); Auctions never made
   `PresenceSession` aware of auction-level concepts
   (`SellerPresenceVerification` is Auctions-owned). Payments continues
   that discipline — it is the consumer of `Auction`'s outcome, not a
   collaborator `Auction` needs to know about.

4. **`AuctionWon` is Payments' sole trigger.** The event already carries
   `winningBidId` and `winningAmount` (`packages/Auctions/src/Events/AuctionWon.php`).
   Payments creates its `PaymentIntent` from this event and never queries
   or mutates `Auction`'s status afterward.

5. **The only facts Payments reads from Auctions are `winningBidId` and
   `winningAmount`**, obtained through a new, Payments-owned read port —
   mirroring the shape of `WinningBidLookup` (Auctions-owned,
   ADR-013 §5) and `SellerPresenceVerification` (Auctions-owned,
   ADR-009) — never through direct access to the `Auction` Eloquent
   model.

6. **A composed, cross-context "overall status" (e.g. for an admin
   dashboard showing "won → payment authorized → awaiting transfer") is
   a read-side concern**, answered by querying `Auction`, `PaymentIntent`,
   and (once it exists) `Transfer` independently by `auctionId` and
   composing the result at the point of display — never by a shared
   status field written by more than one context. Designing that
   read-model is explicitly deferred until a real need for it exists
   (likely Administration, Phase 8).

7. **Explicitly rejected alternative**: extending `AuctionStatus` with
   `awaiting_payment`/`awaiting_transfer`/`transferred`/`completed`/
   `disputed`/`refunded`, as `claude-mvp-analysis.md` §7.1 originally
   sketched. Rejected because it would make `Auction` depend on knowledge
   of Payments' and Transfers' internal lifecycles to decide when those
   states apply — precisely the coupling the project's cross-module port
   pattern exists to prevent, and precedent this codebase has never
   actually adopted even where the original analysis suggested it.

## Consequences

- `packages/Auctions` requires no code changes for Phase 4 at all — no
  new states, events, fields, or methods on `Auction`.
- `packages/Payments`' own aggregate must independently express *why* a
  `PaymentIntent` reached a terminal state (e.g. "cancelled because the
  auction was cancelled/expired before capture" vs. "cancelled because
  authorization failed") — these are reasons Payments tracks itself,
  never borrowed from `Auction`.
- Any future need to answer "what is the combined status of this
  auction" requires a dedicated cross-context read/query, not a shared
  field — that query is out of scope until a concrete consumer
  (dashboard, notification, etc.) needs it.
- Frontend copy describing payment/transfer state (e.g. "payment
  authorized, awaiting transfer") is driven entirely by Payments' own
  state once a frontend exists — never by any `Auction` field.
- Consistent with, and extends one hop further than, the precedent set
  by `AuctionGateway` (Bids → Auctions, ADR-012) and
  `SellerPresenceVerification`/`WinningBidLookup` (Auctions →
  QueuePresence/Bids, ADR-009/ADR-013).

## References

- `docs/product/claude-mvp-analysis.md` §7.1 (original, superseded Auction
  state-machine sketch), §4 item 8 (Payments bounded context)
- ADR-004 (escrow authorize-then-capture)
- ADR-009, ADR-012, ADR-013 (precedent for consumer-owned cross-module
  ports)
- Phase 4 architecture review (this conversation, §4 and §8 Risk #2)
