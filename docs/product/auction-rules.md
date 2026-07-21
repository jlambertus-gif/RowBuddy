# Auction Rules

## Seller configuration

- Starting price
- Minimum increment
- Auction duration
- Estimated queue position
- Transfer window
- Queue notes
- Required verification level

Auction duration and transfer window combined are bounded by the payment
authorization validity window (~5–7 days) — see
`docs/decisions/004-escrow-authorize-then-capture.md`.

## Auction states

- draft
- pending_verification
- scheduled
- open
- closing
- awaiting_payment
- awaiting_transfer
- transferred
- completed
- cancelled
- disputed
- refunded

## Bid rules

- Bids are server-timestamped.
- Accepted bids are immutable.
- A bid must be greater than or equal to the current required minimum.
- A user may not bid on their own auction.
- Bids submitted after closing are rejected.
- Concurrency must be resolved with database transactions and row-level
  locking.
- A soft-close extension applies when a bid lands in the final window of
  an auction, to prevent last-second sniping (see
  `docs/product/claude-mvp-analysis.md` §2, item 9).
