<?php

declare(strict_types=1);

namespace RowBuddy\Queues\ValueObjects;

use RowBuddy\SharedKernel\Exceptions\ValidationException;
use RowBuddy\SharedKernel\ValueObjects\GeoPoint;

/**
 * A closed ring of points — the exterior boundary or an interior hole of
 * a {@see Polygon}. Pure geometry data: no PostGIS, no WKT, no SRID
 * awareness (ADR-007) — translating this to/from a spatial-database
 * format is strictly an Infrastructure concern.
 */
final class LinearRing
{
    /** @var list<GeoPoint> */
    public readonly array $points;

    /**
     * @param  list<GeoPoint>  $points
     */
    public function __construct(array $points)
    {
        if (count($points) < 4) {
            throw new ValidationException(
                'A linear ring requires at least 4 points (3 distinct vertices plus the closing point).'
            );
        }

        if (! $points[0]->equals($points[count($points) - 1])) {
            throw new ValidationException('A linear ring must be closed: its first and last points must be equal.');
        }

        $this->points = $points;
    }
}
