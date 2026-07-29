<?php

declare(strict_types=1);

namespace RowBuddy\Disputes\Exceptions;

use RowBuddy\SharedKernel\Exceptions\DomainException;

/**
 * Raised when the filing party does not match the `Transfer`'s own
 * `buyerId` (ADR-021 §1) — only the buyer may open a dispute; the
 * seller never independently opens one.
 */
final class DisputeFilingNotAuthorized extends DomainException
{
    public static function forTransferId(string $transferId): self
    {
        return new self("Only the buyer on transfer [{$transferId}] may open a dispute against it.");
    }
}
