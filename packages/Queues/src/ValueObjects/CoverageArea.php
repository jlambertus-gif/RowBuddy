<?php

declare(strict_types=1);

namespace RowBuddy\Queues\ValueObjects;

use RowBuddy\SharedKernel\Exceptions\ValidationException;
use RowBuddy\SharedKernel\ValueObjects\GeoPoint;

/**
 * The area within which a queue is discoverable — a `MULTIPOLYGON` in
 * OGC terms. Deliberately a pure domain value object (ADR-007): no
 * PostGIS dependency, no Laravel dependency, no WKT/SRID/geometry-type
 * awareness of its own. Infrastructure alone translates this to and
 * from `MULTIPOLYGON`/SRID 4326 — this class never serializes itself
 * into any spatial-database format.
 */
final class CoverageArea
{
    /**
     * @param  list<Polygon>  $polygons
     */
    public function __construct(public readonly array $polygons)
    {
        if ($polygons === []) {
            throw new ValidationException('A coverage area requires at least one polygon.');
        }
    }

    /**
     * Approximates a circle (center + radius) as a closed polygon —
     * the default coverage area assigned automatically when a queue is
     * published (ADR-007 §8), derived from the queue's existing
     * `Geofence`. Pure spherical trigonometry (the same great-circle
     * math `GeoPoint::distanceInMetersTo()` already uses, run in
     * reverse: destination point given a bearing and distance) — no
     * PostGIS, no Laravel.
     */
    public static function approximatingCircle(GeoPoint $center, float $radiusInMeters, int $segments = 32): self
    {
        if ($segments < 3) {
            throw new ValidationException('Approximating a circle requires at least 3 segments.');
        }

        $earthRadiusMeters = 6_371_000.0;
        $angularDistance = $radiusInMeters / $earthRadiusMeters;
        $latFrom = deg2rad($center->latitude);
        $lonFrom = deg2rad($center->longitude);

        $points = [];

        for ($i = 0; $i < $segments; $i++) {
            $bearing = (2 * M_PI * $i) / $segments;

            $latTo = asin(
                sin($latFrom) * cos($angularDistance)
                + cos($latFrom) * sin($angularDistance) * cos($bearing)
            );

            $lonTo = $lonFrom + atan2(
                sin($bearing) * sin($angularDistance) * cos($latFrom),
                cos($angularDistance) - sin($latFrom) * sin($latTo)
            );

            $normalizedLongitude = fmod(rad2deg($lonTo) + 540.0, 360.0) - 180.0;

            $points[] = new GeoPoint(rad2deg($latTo), $normalizedLongitude);
        }

        $points[] = $points[0];

        return new self([new Polygon(new LinearRing($points))]);
    }
}
