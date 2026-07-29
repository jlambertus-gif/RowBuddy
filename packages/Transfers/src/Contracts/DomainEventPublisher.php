<?php

declare(strict_types=1);

namespace RowBuddy\Transfers\Contracts;

use RowBuddy\SharedKernel\Contracts\DomainEvent;

/**
 * Domain-facing port for publishing the events Transfers' aggregates
 * raise. Mirrors Auctions', Bids', Payments', and QueuePresence's own
 * copies of this same port — each module owns its own rather than
 * sharing one.
 */
interface DomainEventPublisher
{
    public function publish(DomainEvent $event): void;
}
