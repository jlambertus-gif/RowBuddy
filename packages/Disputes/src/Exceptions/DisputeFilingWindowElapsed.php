<?php

declare(strict_types=1);

namespace RowBuddy\Disputes\Exceptions;

use RowBuddy\SharedKernel\Exceptions\DomainException;

/**
 * Raised when a filing attempt arrives after
 * `DisputeFilingDeadlinePolicy`'s window has elapsed since
 * `Transfer.confirmedAt` (ADR-021 §3).
 */
final class DisputeFilingWindowElapsed extends DomainException
{
    public static function forTransferId(string $transferId): self
    {
        return new self("The filing window for transfer [{$transferId}] has elapsed.");
    }
}
