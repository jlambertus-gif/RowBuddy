<?php

declare(strict_types=1);

namespace RowBuddy\QueuePresence\Contracts;

use RowBuddy\SharedKernel\Contracts\DomainEvent;

/**
 * Domain-facing port for publishing the events a {@see
 * \RowBuddy\QueuePresence\PresenceSession} aggregate releases after a use
 * case completes. Kept separate from any specific event-bus implementation
 * (e.g. Laravel's dispatcher) so the application layer stays
 * framework-agnostic.
 */
interface DomainEventPublisher
{
    public function publish(DomainEvent $event): void;
}
