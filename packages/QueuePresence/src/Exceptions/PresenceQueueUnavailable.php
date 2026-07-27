<?php

declare(strict_types=1);

namespace RowBuddy\QueuePresence\Exceptions;

use RowBuddy\SharedKernel\Exceptions\DomainException;

/**
 * Raised when a presence-capture action targets a queue that either does
 * not exist or is not published — presence capture only ever makes sense
 * against a queue a seller could actually have discovered.
 */
final class PresenceQueueUnavailable extends DomainException
{
    public static function forQueueId(string $queueId): self
    {
        return new self("Queue [{$queueId}] is not available for presence capture.");
    }
}
