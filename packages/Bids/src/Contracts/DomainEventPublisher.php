<?php

declare(strict_types=1);

namespace RowBuddy\Bids\Contracts;

use RowBuddy\Bids\Bid;
use RowBuddy\SharedKernel\Contracts\DomainEvent;

/**
 * Domain-facing port for publishing the events a {@see Bid}
 * raises. Mirrors Auctions' and QueuePresence's own copies of this same
 * port — each module owns its own rather than sharing one.
 */
interface DomainEventPublisher
{
    public function publish(DomainEvent $event): void;
}
