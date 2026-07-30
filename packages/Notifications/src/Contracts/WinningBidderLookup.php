<?php

declare(strict_types=1);

namespace RowBuddy\Notifications\Contracts;

/**
 * Notifications-owned read port into Bids — `AuctionWon` (ADR-025 §6)
 * carries `winningBidId`, not the winning bidder's own id, so resolving
 * the actual recipient of the `AuctionWon` notification requires this
 * one extra hop. Mirrors the "consumer owns the port" pattern used
 * everywhere else in this codebase for a cross-module read; implemented
 * by an `apps/web` adapter bridging to `BidRepository`.
 */
interface WinningBidderLookup
{
    public function findBidderIdByBidId(string $bidId): ?string;
}
