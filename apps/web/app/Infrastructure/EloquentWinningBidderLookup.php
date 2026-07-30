<?php

declare(strict_types=1);

namespace App\Infrastructure;

use RowBuddy\Bids\Contracts\BidRepository;
use RowBuddy\Notifications\Contracts\WinningBidderLookup;

/**
 * Bridges Notifications' read-only {@see WinningBidderLookup} port to
 * Bids' {@see BidRepository} — the one place allowed to know both
 * packages' internals, per the composition-root pattern every prior
 * cross-module read port in this codebase already uses.
 */
final class EloquentWinningBidderLookup implements WinningBidderLookup
{
    public function __construct(
        private readonly BidRepository $bids,
    ) {}

    public function findBidderIdByBidId(string $bidId): ?string
    {
        return $this->bids->findById($bidId)?->bidderId;
    }
}
