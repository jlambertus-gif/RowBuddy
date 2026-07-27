<?php

declare(strict_types=1);

namespace RowBuddy\QueuePresence\ValueObjects;

use RowBuddy\QueuePresence\Scoring\ConfidenceScorer;

/**
 * The result of {@see ConfidenceScorer}:
 * a numeric score (ADR-008's point scheme) and the tier derived from it.
 * Never presented as an absolute guarantee, per
 * docs/product/verification-model.md.
 */
final class ConfidenceScore
{
    public function __construct(
        public readonly int $points,
        public readonly ConfidenceTier $tier,
    ) {}
}
