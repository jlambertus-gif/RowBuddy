<?php

declare(strict_types=1);

namespace RowBuddy\Queues\ValueObjects;

use RowBuddy\SharedKernel\Exceptions\ValidationException;

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
}
