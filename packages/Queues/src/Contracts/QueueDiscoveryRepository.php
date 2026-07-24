<?php

declare(strict_types=1);

namespace RowBuddy\Queues\Contracts;

use RowBuddy\Queues\Queue;
use RowBuddy\Queues\ValueObjects\CoverageArea;
use RowBuddy\SharedKernel\ValueObjects\GeoPoint;

/**
 * Domain-facing port for queue discoverability (ADR-007). Named for
 * business intent, not SQL semantics — an adapter may satisfy
 * `discoverByLocation` with `ST_Contains`, `ST_Intersects`, or any other
 * strategy; that choice is strictly an Infrastructure implementation
 * detail this port's vocabulary must never hint at.
 *
 * Deliberately separate from {@see QueueRepository}: coverage is a
 * discovery-side concern associated with a queue by ID, not a field the
 * Queue aggregate itself owns (see ADR-007 §4).
 */
interface QueueDiscoveryRepository
{
    public function defineCoverageArea(string $queueId, CoverageArea $coverageArea): void;

    /**
     * @return list<Queue>
     */
    public function discoverByLocation(GeoPoint $point): array;
}
