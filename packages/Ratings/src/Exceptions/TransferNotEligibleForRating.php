<?php

declare(strict_types=1);

namespace RowBuddy\Ratings\Exceptions;

use RowBuddy\SharedKernel\Exceptions\DomainException;

/**
 * Raised when a submission attempt targets a `Transfer` that either does
 * not exist or is not `Confirmed` (ADR-024 §2) — `Issued`/`Expired`/
 * `Cancelled` transfers remain exclusively governed by ADR-018's
 * automatic no-fault flow and must never carry a rating.
 */
final class TransferNotEligibleForRating extends DomainException
{
    public static function forTransferId(string $transferId): self
    {
        return new self("Transfer [{$transferId}] is not eligible for a rating — it must be Confirmed.");
    }
}
