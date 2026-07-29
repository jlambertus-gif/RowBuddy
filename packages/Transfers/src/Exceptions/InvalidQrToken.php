<?php

declare(strict_types=1);

namespace RowBuddy\Transfers\Exceptions;

use RowBuddy\SharedKernel\Exceptions\DomainException;

/**
 * Raised when a seller's submitted QR token does not match the transfer's
 * stored hash (ADR-017 §5) — the confirmation attempt is rejected before
 * `Transfer::confirmBySeller()` is ever called; the aggregate never sees
 * it (ADR-017 §4).
 */
final class InvalidQrToken extends DomainException
{
    public static function forTransfer(string $transferId): self
    {
        return new self("Transfer [{$transferId}]: submitted QR token does not match.");
    }
}
