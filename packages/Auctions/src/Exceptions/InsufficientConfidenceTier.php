<?php

declare(strict_types=1);

namespace RowBuddy\Auctions\Exceptions;

use RowBuddy\SharedKernel\Exceptions\DomainException;

/**
 * Raised when a seller's presence/confidence state does not meet ADR-010
 * §1's Evidence Verified minimum required to open an auction — including
 * when no verification record exists at all (fail closed, per ADR-009
 * §3: never treat "unknown" as "verified").
 */
final class InsufficientConfidenceTier extends DomainException
{
    public static function forSellerAndQueue(string $sellerId, string $queueId): self
    {
        return new self(
            "Seller [{$sellerId}] does not meet the minimum confidence tier to open an auction for queue [{$queueId}]."
        );
    }
}
