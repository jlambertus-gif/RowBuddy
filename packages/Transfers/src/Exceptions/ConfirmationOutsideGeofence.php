<?php

declare(strict_types=1);

namespace RowBuddy\Transfers\Exceptions;

use RowBuddy\SharedKernel\Exceptions\DomainException;

/**
 * Raised when a confirming party's submitted location falls outside the
 * queue's geofence (ADR-020 §2) — the confirmation attempt is rejected
 * before `Transfer::confirmBySeller()`/`confirmByBuyer()` is ever called;
 * the aggregate never sees it (ADR-017 §4).
 */
final class ConfirmationOutsideGeofence extends DomainException
{
    public static function forTransfer(string $transferId): self
    {
        return new self("Transfer [{$transferId}]: confirmation location is outside the queue's geofence.");
    }
}
