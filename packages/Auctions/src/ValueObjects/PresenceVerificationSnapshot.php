<?php

declare(strict_types=1);

namespace RowBuddy\Auctions\ValueObjects;

use DateTimeImmutable;
use RowBuddy\Auctions\Contracts\SellerPresenceVerification;

/**
 * The read result of {@see SellerPresenceVerification}
 * (ADR-009 §2) — deliberately minimal: no GPS coordinates, no evidence-photo
 * paths, no audit history, only what an auction-creation gate (ADR-010 §1)
 * or a future live-proximity check (ADR-010 §3, not implemented before
 * Sprint 4) needs.
 */
final class PresenceVerificationSnapshot
{
    public function __construct(
        public readonly ConfidenceTier $tier,
        public readonly int $points,
        public readonly DateTimeImmutable $computedAt,
        public readonly bool $sessionActive,
        public readonly ?DateTimeImmutable $lastWithinGeofencePingAt,
    ) {}
}
