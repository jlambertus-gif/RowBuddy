<?php

declare(strict_types=1);

namespace RowBuddy\QueuePresence\Exceptions;

use RowBuddy\SharedKernel\Exceptions\DomainException;

final class PresenceSessionNotActive extends DomainException
{
    public static function forSessionId(string $presenceSessionId): self
    {
        return new self(
            "Presence session [{$presenceSessionId}] is not active."
        );
    }
}
