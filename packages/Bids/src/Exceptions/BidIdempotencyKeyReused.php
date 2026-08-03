<?php

declare(strict_types=1);

namespace RowBuddy\Bids\Exceptions;

use RowBuddy\SharedKernel\Exceptions\DomainException;

/**
 * Raised when a caller reuses an Idempotency-Key that was already claimed
 * for a different auction or a different amount (Phase 9, ADR-027
 * Architecture Refinements §2) — a client error, not a legitimate network
 * retry. A genuine retry always resends the exact same request.
 */
final class BidIdempotencyKeyReused extends DomainException
{
    public static function forKey(string $idempotencyKey): self
    {
        return new self("Idempotency key [{$idempotencyKey}] was already used for a different bid request.");
    }
}
