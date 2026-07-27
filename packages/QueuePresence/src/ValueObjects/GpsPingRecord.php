<?php

declare(strict_types=1);

namespace RowBuddy\QueuePresence\ValueObjects;

use DateTimeImmutable;
use RowBuddy\SharedKernel\ValueObjects\GeoPoint;

/**
 * A single recorded GPS ping, including whether it fell inside the
 * queue's geofence — decided once, at capture time, by whoever
 * constructs this (the application service), never recomputed later.
 */
final class GpsPingRecord
{
    public function __construct(
        public readonly string $id,
        public readonly string $presenceSessionId,
        public readonly GeoPoint $location,
        public readonly float $accuracyInMeters,
        public readonly bool $withinGeofence,
        public readonly DateTimeImmutable $recordedAt,
    ) {}
}
