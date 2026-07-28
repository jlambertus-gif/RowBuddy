<?php

declare(strict_types=1);

namespace RowBuddy\Payments\Infrastructure\Events;

use Illuminate\Contracts\Events\Dispatcher;
use RowBuddy\Payments\Contracts\DomainEventPublisher;
use RowBuddy\SharedKernel\Contracts\DomainEvent;

final class LaravelDomainEventPublisher implements DomainEventPublisher
{
    public function __construct(private readonly Dispatcher $dispatcher) {}

    public function publish(DomainEvent $event): void
    {
        $this->dispatcher->dispatch($event);
    }
}
