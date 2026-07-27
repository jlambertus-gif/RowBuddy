<?php

declare(strict_types=1);

namespace RowBuddy\Auctions\Contracts;

use RowBuddy\Auctions\Auction;
use RowBuddy\SharedKernel\Contracts\DomainEvent;

/**
 * Domain-facing port for publishing the events an {@see Auction}
 * aggregate releases after a use case completes. Kept separate from any
 * specific event-bus implementation so the application layer stays
 * framework-agnostic — mirrors QueuePresence's own DomainEventPublisher;
 * each module owns its own copy rather than sharing one.
 */
interface DomainEventPublisher
{
    public function publish(DomainEvent $event): void;
}
