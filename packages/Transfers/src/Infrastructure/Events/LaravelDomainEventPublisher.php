<?php

declare(strict_types=1);

namespace RowBuddy\Transfers\Infrastructure\Events;

use Illuminate\Contracts\Events\Dispatcher;
use RowBuddy\SharedKernel\Contracts\DomainEvent;
use RowBuddy\Transfers\Contracts\DomainEventPublisher;

final class LaravelDomainEventPublisher implements DomainEventPublisher
{
    public function __construct(private readonly Dispatcher $dispatcher) {}

    public function publish(DomainEvent $event): void
    {
        $this->dispatcher->dispatch($event);
    }
}
