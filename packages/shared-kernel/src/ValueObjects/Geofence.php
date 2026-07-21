<?php

declare(strict_types=1);

namespace RowBuddy\SharedKernel\ValueObjects;

use RowBuddy\SharedKernel\Exceptions\ValidationException;

/**
 * A circular boundary around a center point, used wherever a module needs
 * to ask "is this point within range of that point" (e.g. Queues' venue
 * boundary, QueuePresence's proximity check, Transfers' handoff
 * cross-check) without re-implementing the geometry each time.
 */
final class Geofence
{
    public readonly GeoPoint $center;

    public readonly float $radiusInMeters;

    public function __construct(GeoPoint $center, float $radiusInMeters)
    {
        if ($radiusInMeters <= 0.0) {
            throw new ValidationException("Geofence radius must be positive, got [{$radiusInMeters}].");
        }

        $this->center = $center;
        $this->radiusInMeters = $radiusInMeters;
    }

    public function contains(GeoPoint $point): bool
    {
        return $this->center->distanceInMetersTo($point) <= $this->radiusInMeters;
    }
}
