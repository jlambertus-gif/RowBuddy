<?php

declare(strict_types=1);

namespace RowBuddy\Bids\Exceptions;

use RowBuddy\SharedKernel\Exceptions\DomainException;

/**
 * Raised when a concurrent request already claimed this Idempotency-Key
 * and has not yet resolved to an accepted or rejected outcome (Phase 9,
 * ADR-027 Architecture Refinements §2) — two requests carrying the same
 * key arrived at (nearly) the same instant. The caller should treat this
 * as "try again shortly," not as a permanent rejection.
 */
final class BidPlacementInProgress extends DomainException
{
    public static function forKey(string $idempotencyKey): self
    {
        return new self("A bid request with idempotency key [{$idempotencyKey}] is already being processed.");
    }
}
