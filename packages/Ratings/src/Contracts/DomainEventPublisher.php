<?php

declare(strict_types=1);

namespace RowBuddy\Ratings\Contracts;

use RowBuddy\SharedKernel\Contracts\DomainEvent;

/**
 * Domain-facing port for publishing the events Ratings' aggregates
 * raise. Mirrors Auctions', Bids', Payments', Transfers', and Disputes'
 * own copies of this same port — each module owns its own rather than
 * sharing one.
 */
interface DomainEventPublisher
{
    public function publish(DomainEvent $event): void;
}
