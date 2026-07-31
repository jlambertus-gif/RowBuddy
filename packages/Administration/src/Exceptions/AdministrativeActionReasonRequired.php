<?php

declare(strict_types=1);

namespace RowBuddy\Administration\Exceptions;

use RowBuddy\SharedKernel\Exceptions\DomainException;

/**
 * Raised when a blank or whitespace-only reason is supplied for a
 * consequential administrative action — every suspension/reinstatement
 * requires an explicit reason (ADR-026 §4/Sprint 2 invariants).
 */
final class AdministrativeActionReasonRequired extends DomainException
{
    public static function create(): self
    {
        return new self('An explicit reason is required for this administrative action.');
    }
}
