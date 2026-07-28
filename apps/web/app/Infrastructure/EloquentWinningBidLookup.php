<?php

declare(strict_types=1);

namespace App\Infrastructure;

use RowBuddy\Auctions\Contracts\WinningBidLookup;
use RowBuddy\Auctions\ValueObjects\WinningBidCandidate;
use RowBuddy\Bids\Contracts\BidRepository;

/**
 * Bridges Auctions' {@see WinningBidLookup} port (ADR-013 §5) to Bids'
 * own {@see BidRepository} — this class is the one place in the codebase
 * allowed to depend on both packages at once, because apps/web is the
 * composition root, not either module itself. packages/Auctions never
 * imports anything from packages/Bids.
 */
final class EloquentWinningBidLookup implements WinningBidLookup
{
    public function __construct(private readonly BidRepository $bids) {}

    public function highestBidFor(string $auctionId): ?WinningBidCandidate
    {
        $bid = $this->bids->findHighestBidFor($auctionId);

        if ($bid === null) {
            return null;
        }

        return new WinningBidCandidate($bid->id, $bid->bidderId, $bid->amount, $bid->placedAt);
    }
}
