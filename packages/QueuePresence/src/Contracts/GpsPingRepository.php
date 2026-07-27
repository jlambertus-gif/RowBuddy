<?php

declare(strict_types=1);

namespace RowBuddy\QueuePresence\Contracts;

use RowBuddy\QueuePresence\ValueObjects\GpsPingRecord;

/**
 * Domain-facing persistence port for recorded GPS pings. Write-only for
 * now — Sprint 4 only needs to durably record each ping; the read side
 * (e.g. "best accuracy among within-geofence pings for a session") is
 * added once the confidence-scoring engine is actually wired to real
 * signal history, not spec'd speculatively here.
 */
interface GpsPingRepository
{
    public function record(GpsPingRecord $ping): void;
}
