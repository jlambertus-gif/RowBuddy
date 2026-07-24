<?php

declare(strict_types=1);

namespace RowBuddy\Queues\ValueObjects;

/**
 * An exterior boundary plus zero or more interior holes — matches the
 * OGC `Polygon` definition exactly. Pure geometry data (ADR-007): no
 * PostGIS, no WKT, no SRID awareness.
 */
final class Polygon
{
    /**
     * @param  list<LinearRing>  $interiorRings
     */
    public function __construct(
        public readonly LinearRing $exteriorRing,
        public readonly array $interiorRings = [],
    ) {}
}
