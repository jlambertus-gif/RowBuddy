<?php

declare(strict_types=1);

namespace RowBuddy\SharedKernel\Contracts;

use DateTimeImmutable;

/**
 * Contract every module's domain events must implement, so the platform-wide
 * audit listener and event-bus tooling can treat events from any module
 * uniformly without depending on module-specific classes.
 */
interface DomainEvent
{
    public function eventName(): string;

    public function occurredAt(): DateTimeImmutable;

    /**
     * @return array<string, mixed>
     */
    public function payload(): array;
}
