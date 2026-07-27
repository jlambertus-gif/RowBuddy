<?php

declare(strict_types=1);

namespace RowBuddy\QueuePresence\Contracts;

use RowBuddy\QueuePresence\ValueObjects\ConfidenceScoreRecord;

/**
 * Domain-facing persistence port for confidence-score computations.
 * Append-only: there is no update method — {@see record()} always
 * inserts a new row, per ADR-008 ("recomputed, not mutated in place").
 */
interface ConfidenceScoreRepository
{
    public function record(ConfidenceScoreRecord $score): void;

    public function latestFor(string $presenceSessionId): ?ConfidenceScoreRecord;
}
