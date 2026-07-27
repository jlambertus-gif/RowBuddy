<?php

declare(strict_types=1);

namespace RowBuddy\QueuePresence\Exceptions;

use RowBuddy\SharedKernel\Exceptions\DomainException;

/**
 * Raised when a user attempts to record a signal on, or end, a presence
 * session that belongs to a different seller. Enforced in the application
 * layer (not left to the HTTP layer or a database constraint) since
 * ownership is a domain-level rule, not an infrastructure concern.
 */
final class PresenceSessionAccessDenied extends DomainException
{
    public static function forSessionId(string $presenceSessionId): self
    {
        return new self("You do not have access to presence session [{$presenceSessionId}].");
    }
}
