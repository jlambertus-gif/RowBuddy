<?php

declare(strict_types=1);

namespace RowBuddy\Queues\Application\Discovery;

/**
 * One page of ranked discovery results.
 */
final class QueueDiscoveryPage
{
    /**
     * @param  list<DiscoveredQueue>  $items
     */
    public function __construct(
        public readonly array $items,
        public readonly int $page,
        public readonly int $perPage,
        public readonly int $total,
        public readonly bool $hasMore,
    ) {}
}
