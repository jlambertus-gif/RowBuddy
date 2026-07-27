<?php

declare(strict_types=1);

namespace RowBuddy\QueuePresence\ValueObjects;

use DateTimeImmutable;

/**
 * A single, immutable confidence-score computation for a session — one of
 * potentially many over that session's lifetime (ADR-008: "recomputed,
 * not mutated in place").
 */
final class ConfidenceScoreRecord
{
    public function __construct(
        public readonly string $id,
        public readonly string $presenceSessionId,
        public readonly int $points,
        public readonly ConfidenceTier $tier,
        public readonly DateTimeImmutable $computedAt,
    ) {}
}
