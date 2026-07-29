<?php

declare(strict_types=1);

namespace RowBuddy\Disputes\Application;

use RowBuddy\Disputes\Contracts\DisputeFilingDeadlinePolicy;

/**
 * MVP default: 7 days (168 hours) from `Transfer.confirmedAt` (ADR-021
 * §3) — a provisional configuration value, not a permanent domain
 * invariant, the same posture `FixedTransferWindowPolicy`/
 * `FixedAuctionDurationPolicy` already take.
 */
final class FixedDisputeFilingDeadlinePolicy implements DisputeFilingDeadlinePolicy
{
    public function __construct(private readonly int $durationInSeconds) {}

    public function durationInSecondsFor(string $transferId): int
    {
        return $this->durationInSeconds;
    }
}
