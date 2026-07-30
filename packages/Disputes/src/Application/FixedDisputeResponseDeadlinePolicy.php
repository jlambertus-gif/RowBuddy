<?php

declare(strict_types=1);

namespace RowBuddy\Disputes\Application;

use RowBuddy\Disputes\Contracts\DisputeResponseDeadlinePolicy;

/**
 * MVP default: 5 days from `DisputeOpened` (ADR-021 §4) — a provisional
 * configuration value, not a permanent domain invariant, the same
 * posture `FixedDisputeFilingDeadlinePolicy` already takes.
 */
final class FixedDisputeResponseDeadlinePolicy implements DisputeResponseDeadlinePolicy
{
    public function __construct(private readonly int $durationInSeconds) {}

    public function durationInSecondsFor(string $disputeId): int
    {
        return $this->durationInSeconds;
    }
}
