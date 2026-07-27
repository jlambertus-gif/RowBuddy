<?php

declare(strict_types=1);

namespace RowBuddy\Auctions\ValueObjects;

/**
 * The Phase 3 MVP slice of the full auction lifecycle described in
 * docs/product/claude-mvp-analysis.md §7.1 (which also covers
 * awaiting_payment/awaiting_transfer/transferred/completed/disputed —
 * all later-phase states owned by Payments/Transfers/Disputes, not this
 * aggregate). Sprint 1 only needed the slice the roadmap's Phase 3 exit
 * criteria requires: "an auction can run end-to-end (open → closing →
 * winning bid selected)".
 *
 * `Cancelled` was added in Sprint 4 (ADR-011) solely for live-proximity
 * loss — a seller who was verified at creation but is later found to no
 * longer be near the queue. Seller-initiated *voluntary* withdrawal is a
 * distinct concept that is still not built; it happens to reuse this same
 * status value for now, but should not be assumed permanently identical
 * to it — revisit once voluntary withdrawal is actually implemented.
 */
enum AuctionStatus: string
{
    case Open = 'open';
    case Closing = 'closing';
    case Won = 'won';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
}
