<?php

declare(strict_types=1);

namespace App\Infrastructure;

use RowBuddy\QueuePresence\Contracts\QueueGeofenceLookup;
use RowBuddy\Queues\Contracts\QueueRepository;
use RowBuddy\Queues\ValueObjects\QueueStatus;
use RowBuddy\SharedKernel\ValueObjects\Geofence;

/**
 * Bridges QueuePresence's read-only {@see QueueGeofenceLookup} port to
 * Queues' own domain-facing {@see QueueRepository} — this class is the one
 * place in the codebase allowed to depend on both packages at once,
 * because apps/web is the composition root, not either module itself
 * (see tests/Architecture/ModuleBoundaryTest.php). Neither package
 * depends on the other's internals.
 */
final class EloquentQueueGeofenceLookup implements QueueGeofenceLookup
{
    public function __construct(private readonly QueueRepository $queues) {}

    public function publishedGeofenceFor(string $queueId): ?Geofence
    {
        $queue = $this->queues->findById($queueId);

        if ($queue === null || $queue->status() !== QueueStatus::Published) {
            return null;
        }

        return $queue->geofence;
    }
}
