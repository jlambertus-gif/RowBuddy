<?php

declare(strict_types=1);

namespace RowBuddy\QueuePresence\Contracts;

use RowBuddy\QueuePresence\Application\ConfidenceRecomputer;
use RowBuddy\QueuePresence\ValueObjects\GpsPingRecord;

/**
 * Domain-facing persistence port for recorded GPS pings.
 */
interface GpsPingRepository
{
    public function record(GpsPingRecord $ping): void;

    /**
     * The smallest (best) accuracy, in meters, among pings recorded
     * within the queue's geofence for this session — or null if none
     * exist. Feeds ADR-008's ConfidenceScorer via
     * {@see ConfidenceRecomputer}.
     */
    public function bestAccuracyWithinGeofence(string $presenceSessionId): ?float;
}
