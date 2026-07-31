<?php

declare(strict_types=1);

namespace RowBuddy\Administration\Exceptions;

use RowBuddy\SharedKernel\Exceptions\DomainException;

/**
 * Raised when an administrative action targets a restricted category or
 * jurisdiction rule id that does not exist.
 */
final class AdministrativeTargetNotFound extends DomainException
{
    public static function forTarget(string $targetType, string $targetId): self
    {
        return new self("No {$targetType} exists with id [{$targetId}].");
    }
}
