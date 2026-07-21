<?php

declare(strict_types=1);

namespace RowBuddy\SharedKernel\ValueObjects;

use RowBuddy\SharedKernel\Exceptions\ValidationException;

/**
 * A latitude/longitude coordinate. This is a general-purpose geo type only
 * — it deliberately knows nothing about GPS accuracy, spoofing detection,
 * or presence verification, since "GPS alone cannot validate queue
 * position" is a QueuePresence business rule, not a shared-kernel concern.
 */
final class GeoPoint
{
    public readonly float $latitude;

    public readonly float $longitude;

    public function __construct(float $latitude, float $longitude)
    {
        if ($latitude < -90.0 || $latitude > 90.0) {
            throw new ValidationException("Invalid latitude [{$latitude}]: must be between -90 and 90.");
        }

        if ($longitude < -180.0 || $longitude > 180.0) {
            throw new ValidationException("Invalid longitude [{$longitude}]: must be between -180 and 180.");
        }

        $this->latitude = $latitude;
        $this->longitude = $longitude;
    }

    /**
     * Great-circle distance to another point, in meters (haversine formula).
     */
    public function distanceInMetersTo(GeoPoint $other): float
    {
        $earthRadiusMeters = 6_371_000.0;

        $latFrom = deg2rad($this->latitude);
        $latTo = deg2rad($other->latitude);
        $deltaLat = deg2rad($other->latitude - $this->latitude);
        $deltaLon = deg2rad($other->longitude - $this->longitude);

        $a = sin($deltaLat / 2) ** 2
            + cos($latFrom) * cos($latTo) * sin($deltaLon / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadiusMeters * $c;
    }

    public function equals(GeoPoint $other): bool
    {
        return $this->latitude === $other->latitude
            && $this->longitude === $other->longitude;
    }
}
