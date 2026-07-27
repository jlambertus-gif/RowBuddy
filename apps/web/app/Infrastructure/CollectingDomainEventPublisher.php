<?php

declare(strict_types=1);

namespace App\Infrastructure;

use RowBuddy\Auctions\Contracts\DomainEventPublisher;
use RowBuddy\SharedKernel\Contracts\DomainEvent;

/**
 * Collects events instead of dispatching them — used only by
 * {@see EloquentAuctionGateway} to construct a local, per-call
 * `LiveProximityChecker` whose resulting events must NOT reach listeners
 * until the enclosing bid-placement transaction actually commits
 * (ADR-012 §2). The caller retrieves `$events` and forwards them itself,
 * after commit.
 */
final class CollectingDomainEventPublisher implements DomainEventPublisher
{
    /** @var list<DomainEvent> */
    public array $events = [];

    public function publish(DomainEvent $event): void
    {
        $this->events[] = $event;
    }
}
