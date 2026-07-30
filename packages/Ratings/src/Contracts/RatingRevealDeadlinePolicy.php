<?php

declare(strict_types=1);

namespace RowBuddy\Ratings\Contracts;

/**
 * ADR-024 §5 — mirrors `DisputeFilingDeadlinePolicy`'s exact shape: a
 * swappable policy, not an aggregate constant. Deliberately accepts
 * `$transferId` even though the MVP implementation ignores it, so a
 * future policy deriving the deadline from queue/category metadata can
 * replace the binding without touching the `Rating` aggregate or the
 * reveal evaluator.
 *
 * The duration this policy returns is always applied against the
 * rating's own `submittedAt` (ADR-024 §5's amended anchor) — never
 * against any transfer-level timestamp.
 */
interface RatingRevealDeadlinePolicy
{
    public function durationInSecondsFor(string $transferId): int;
}
