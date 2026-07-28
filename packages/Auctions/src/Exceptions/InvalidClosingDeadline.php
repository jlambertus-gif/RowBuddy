<?php

declare(strict_types=1);

namespace RowBuddy\Auctions\Exceptions;

use RowBuddy\SharedKernel\Exceptions\DomainException;

/**
 * Raised when an auction's closesAt is not strictly after its openedAt —
 * an invariant the aggregate itself enforces (ADR-013 §1) regardless of
 * which caller or policy computed the value.
 */
final class InvalidClosingDeadline extends DomainException
{
    public static function forAuction(string $auctionId): self
    {
        return new self("Auction [{$auctionId}]'s closesAt must be after its openedAt.");
    }
}
