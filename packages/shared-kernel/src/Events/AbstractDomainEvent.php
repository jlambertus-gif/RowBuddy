<?php

declare(strict_types=1);

namespace RowBuddy\SharedKernel\Events;

use DateTimeImmutable;
use RowBuddy\SharedKernel\Contracts\ClockInterface;
use RowBuddy\SharedKernel\Contracts\DomainEvent;

/**
 * Base class every module's domain events extend, so occurrence time is
 * captured consistently (via the injected clock, not `new DateTimeImmutable()`
 * scattered across modules) and every event is guaranteed to satisfy the
 * shared {@see DomainEvent} contract the Audit context depends on.
 */
abstract class AbstractDomainEvent implements DomainEvent
{
    private readonly DateTimeImmutable $occurredAt;

    public function __construct(ClockInterface $clock)
    {
        $this->occurredAt = $clock->now();
    }

    public function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    abstract public function eventName(): string;

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [];
    }
}
