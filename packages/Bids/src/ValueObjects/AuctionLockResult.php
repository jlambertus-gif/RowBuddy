<?php

declare(strict_types=1);

namespace RowBuddy\Bids\ValueObjects;

use RowBuddy\Bids\Contracts\AuctionGateway;
use RowBuddy\SharedKernel\Contracts\DomainEvent;

/**
 * The gateway-level result of {@see AuctionGateway::lockAndCheckForBidding()}
 * (ADR-012 §1, §2) — one layer below {@see BidPlacementOutcome}.
 * `proximityEvents` carries whatever LiveProximityChecker raised while
 * evaluating the locked auction, collected rather than published
 * immediately (ADR-012 §2); Bids never inspects their concrete class,
 * only forwards them.
 */
final class AuctionLockResult
{
    /**
     * @param  list<DomainEvent>  $proximityEvents
     */
    public function __construct(
        public readonly ?AuctionSnapshot $snapshot,
        public readonly array $proximityEvents,
    ) {}
}
