<?php

declare(strict_types=1);

namespace RowBuddy\Disputes\Contracts;

/**
 * ADR-021 §3 — mirrors `TransferWindowPolicy`'s exact shape: a
 * swappable policy, not an aggregate constant. Deliberately accepts
 * `$transferId` even though the MVP implementation ignores it, so a
 * future policy deriving the deadline from queue/category metadata can
 * replace the binding without touching the `Dispute` aggregate or the
 * filing service.
 */
interface DisputeFilingDeadlinePolicy
{
    public function durationInSecondsFor(string $transferId): int;
}
