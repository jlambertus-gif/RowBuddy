<?php

declare(strict_types=1);

namespace RowBuddy\Queues\Application\Discovery;

use RowBuddy\Queues\Queue;

/**
 * A read-model result of a discovery search: a matched {@see Queue}
 * plus its computed distance from the search point. Not a domain value
 * object — it carries no invariants of its own, just a query result
 * shape — so it lives in the application layer, not ValueObjects/.
 */
final class DiscoveredQueue
{
    public function __construct(
        public readonly Queue $queue,
        public readonly float $distanceInMeters,
    ) {}
}
