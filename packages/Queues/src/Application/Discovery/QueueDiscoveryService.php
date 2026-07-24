<?php

declare(strict_types=1);

namespace RowBuddy\Queues\Application\Discovery;

use RowBuddy\Queues\Contracts\QueueDiscoveryRepository;
use RowBuddy\Queues\Queue;
use RowBuddy\SharedKernel\ValueObjects\GeoPoint;

/**
 * Orchestrates a location-based queue search (ADR-007 §8): fetch
 * candidates via {@see QueueDiscoveryRepository::discoverByLocation()}
 * exactly as Sprint 6a defined it, rank them by distance to each
 * queue's `Geofence` center (pure PHP, no PostGIS), then paginate
 * in-memory. Depends only on the domain-facing port — no Eloquent, no
 * Laravel container — so this is testable with a plain in-memory fake.
 */
final class QueueDiscoveryService
{
    public function __construct(private readonly QueueDiscoveryRepository $repository) {}

    public function discover(GeoPoint $point, int $page, int $perPage): QueueDiscoveryPage
    {
        $matches = $this->repository->discoverByLocation($point);

        $ranked = array_map(
            static fn (Queue $queue): DiscoveredQueue => new DiscoveredQueue(
                $queue,
                $point->distanceInMetersTo($queue->geofence->center),
            ),
            $matches,
        );

        usort(
            $ranked,
            static fn (DiscoveredQueue $a, DiscoveredQueue $b): int => $a->distanceInMeters <=> $b->distanceInMeters,
        );

        $total = count($ranked);
        $offset = ($page - 1) * $perPage;
        $items = array_values(array_slice($ranked, $offset, $perPage));

        return new QueueDiscoveryPage(
            items: $items,
            page: $page,
            perPage: $perPage,
            total: $total,
            hasMore: ($offset + count($items)) < $total,
        );
    }
}
