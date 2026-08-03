<?php

declare(strict_types=1);

namespace RowBuddy\Bids\ValueObjects;

use RowBuddy\Bids\Contracts\BidPlacementLedger;

/**
 * The read result of {@see BidPlacementLedger}'s
 * claim() (Phase 9, ADR-027 Architecture Refinements §2): either a fresh
 * claim the caller must now resolve by actually calling BidService, or an
 * already-resolved outcome from an earlier identical request that the
 * caller should replay verbatim instead of placing a second bid.
 */
final class BidClaimResult
{
    private function __construct(
        public readonly bool $isFresh,
        public readonly ?string $bidId,
        public readonly ?BidRejectionReason $rejectionReason,
    ) {}

    public static function fresh(): self
    {
        return new self(true, null, null);
    }

    public static function resolvedAccepted(string $bidId): self
    {
        return new self(false, $bidId, null);
    }

    public static function resolvedRejected(BidRejectionReason $reason): self
    {
        return new self(false, null, $reason);
    }

    public function isResolved(): bool
    {
        return ! $this->isFresh;
    }
}
