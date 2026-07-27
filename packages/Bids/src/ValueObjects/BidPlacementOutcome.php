<?php

declare(strict_types=1);

namespace RowBuddy\Bids\ValueObjects;

use RowBuddy\Bids\Application\BidService;
use RowBuddy\Bids\Bid;
use RowBuddy\SharedKernel\Contracts\DomainEvent;

/**
 * The result of a full bid-placement transaction (ADR-012 §1a) — always
 * returned normally by {@see BidService}'s
 * transactional closure, never thrown, so that an expected business
 * rejection never rolls back a legitimate state change (e.g. a
 * LiveProximityChecker transition) that happened earlier in the same
 * attempt. Exactly one of `bid`/`rejectionReason` is non-null.
 * `events` is populated regardless of acceptance or rejection — it is
 * published by the caller only after the transaction has committed
 * (ADR-012 §2).
 */
final class BidPlacementOutcome
{
    /**
     * @param  list<DomainEvent>  $events
     */
    public function __construct(
        public readonly ?Bid $bid,
        public readonly ?BidRejectionReason $rejectionReason,
        public readonly array $events,
    ) {}
}
