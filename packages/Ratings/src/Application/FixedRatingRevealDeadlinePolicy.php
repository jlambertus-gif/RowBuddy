<?php

declare(strict_types=1);

namespace RowBuddy\Ratings\Application;

use RowBuddy\Ratings\Contracts\RatingRevealDeadlinePolicy;

/**
 * MVP default: a fixed duration from the rating's own `submittedAt`
 * (ADR-024 §5) — a provisional configuration value, not a permanent
 * domain invariant, the same posture `FixedDisputeFilingDeadlinePolicy`/
 * `FixedDisputeResponseDeadlinePolicy` already take.
 */
final class FixedRatingRevealDeadlinePolicy implements RatingRevealDeadlinePolicy
{
    public function __construct(private readonly int $durationInSeconds) {}

    public function durationInSecondsFor(string $transferId): int
    {
        return $this->durationInSeconds;
    }
}
