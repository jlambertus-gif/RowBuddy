<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The seller/buyer pair resolved for an already-decided auction outcome
 * (Phase 9, ADR-027 Sprint 3) — never persisted, purely a composition-root
 * resolution result.
 */
final class AuctionParticipants
{
    public function __construct(
        public readonly string $sellerId,
        public readonly string $buyerId,
    ) {}
}
