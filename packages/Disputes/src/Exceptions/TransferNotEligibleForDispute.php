<?php

declare(strict_types=1);

namespace RowBuddy\Disputes\Exceptions;

use RowBuddy\SharedKernel\Exceptions\DomainException;

/**
 * Raised when a filing attempt targets a `Transfer` that either does not
 * exist or is not `Confirmed` (ADR-021 §2) — `Issued`/`Expired`/
 * `Cancelled` transfers remain exclusively governed by ADR-018's
 * automatic no-fault flow and must never enter the dispute lifecycle.
 */
final class TransferNotEligibleForDispute extends DomainException
{
    public static function forTransferId(string $transferId): self
    {
        return new self("Transfer [{$transferId}] is not eligible for a dispute — it must be Confirmed.");
    }
}
