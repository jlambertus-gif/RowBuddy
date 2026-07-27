<?php

declare(strict_types=1);

namespace RowBuddy\Auctions\Infrastructure\Events;

use Illuminate\Contracts\Events\Dispatcher;
use RowBuddy\Auctions\Contracts\DomainEventPublisher;
use RowBuddy\SharedKernel\Contracts\DomainEvent;

final class LaravelDomainEventPublisher implements DomainEventPublisher
{
    public function __construct(private readonly Dispatcher $dispatcher) {}

    public function publish(DomainEvent $event): void
    {
        $this->dispatcher->dispatch($event);
    }
}
