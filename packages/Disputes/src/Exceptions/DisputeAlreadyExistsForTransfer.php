<?php

declare(strict_types=1);

namespace RowBuddy\Disputes\Exceptions;

use RowBuddy\SharedKernel\Exceptions\DomainException;

/**
 * Defense-in-depth translation of the `disputes.transfer_id` unique
 * constraint (ADR-021 Consequences) — mirrors
 * `TransferAlreadyIssuedForAuction`'s identical shape and purpose.
 */
final class DisputeAlreadyExistsForTransfer extends DomainException
{
    public static function forTransferId(string $transferId): self
    {
        return new self("A dispute already exists for transfer [{$transferId}].");
    }
}
