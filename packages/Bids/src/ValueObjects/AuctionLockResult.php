<?php

declare(strict_types=1);

namespace RowBuddy\Bids\ValueObjects;

use RowBuddy\Bids\Contracts\AuctionGateway;
use RowBuddy\SharedKernel\Contracts\DomainEvent;

/**
 * The gateway-level result of either {@see AuctionGateway} method
 * (ADR-012 §1/§2, ADR-013 §3) — one layer below {@see BidPlacementOutcome}.
 * `events` carries whatever LiveProximityChecker, AuctionClosingEvaluator,
 * or SoftCloseExtender raised while evaluating the locked auction,
 * collected rather than published immediately (ADR-012 §2); Bids never
 * inspects their concrete class, only forwards them.
 */
final class AuctionLockResult
{
    /**
     * @param  list<DomainEvent>  $events
     */
    public function __construct(
        public readonly ?AuctionSnapshot $snapshot,
        public readonly array $events,
    ) {}
}
