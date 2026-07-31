<?php

declare(strict_types=1);

namespace RowBuddy\Administration\Exceptions;

use RowBuddy\SharedKernel\Exceptions\DomainException;

/**
 * Raised when reinstating an account that is already active (ADR-026
 * Sprint 2 invariants) — mirrors {@see AccountAlreadySuspended}'s
 * identical hard-rejection posture for the opposite transition.
 */
final class AccountAlreadyActive extends DomainException
{
    public static function forUser(string $userId): self
    {
        return new self("Account [{$userId}] is already active.");
    }
}
