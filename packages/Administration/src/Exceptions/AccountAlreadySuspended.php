<?php

declare(strict_types=1);

namespace RowBuddy\Administration\Exceptions;

use RowBuddy\SharedKernel\Exceptions\DomainException;

/**
 * Raised when suspending an account that is already suspended (ADR-026
 * Sprint 2 invariants) — suspension is not idempotent-replay; a second
 * attempt is a hard rejection, the same discipline `DisputeFilingService`
 * already uses for a second filing attempt.
 */
final class AccountAlreadySuspended extends DomainException
{
    public static function forUser(string $userId): self
    {
        return new self("Account [{$userId}] is already suspended.");
    }
}
