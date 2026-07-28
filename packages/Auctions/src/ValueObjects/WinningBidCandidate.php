<?php

declare(strict_types=1);

namespace RowBuddy\Auctions\ValueObjects;

use DateTimeImmutable;
use RowBuddy\Auctions\Contracts\WinningBidLookup;
use RowBuddy\SharedKernel\ValueObjects\Money;

/**
 * The read result of {@see WinningBidLookup}
 * (ADR-013 §5) — everything `AuctionClosingEvaluator` needs to call
 * `Auction::selectWinningBid()`, in Auctions' own vocabulary.
 */
final class WinningBidCandidate
{
    public function __construct(
        public readonly string $bidId,
        public readonly string $bidderId,
        public readonly Money $amount,
        public readonly DateTimeImmutable $placedAt,
    ) {}
}
