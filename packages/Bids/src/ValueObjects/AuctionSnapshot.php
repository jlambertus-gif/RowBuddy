<?php

declare(strict_types=1);

namespace RowBuddy\Bids\ValueObjects;

use RowBuddy\Bids\Contracts\AuctionGateway;
use RowBuddy\SharedKernel\ValueObjects\Money;

/**
 * The read result of {@see AuctionGateway}
 * (ADR-012 §5) — deliberately minimal: only what validating a bid
 * requires. Bids has no need of Auctions' full status vocabulary, unlike
 * ADR-009's ConfidenceTier, which needed the full tier vocabulary for
 * future tiers; here a single boolean is sufficient.
 */
final class AuctionSnapshot
{
    public function __construct(
        public readonly string $sellerId,
        public readonly Money $startingPrice,
        public readonly bool $isOpenForBidding,
    ) {}
}
