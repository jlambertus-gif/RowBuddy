<?php

declare(strict_types=1);

namespace RowBuddy\QueuePresence\Tests\Fakes;

use RowBuddy\QueuePresence\Contracts\QueueGeofenceLookup;
use RowBuddy\SharedKernel\ValueObjects\Geofence;

final class InMemoryQueueGeofenceLookup implements QueueGeofenceLookup
{
    /** @var array<string, Geofence> */
    private array $published = [];

    public function publish(string $queueId, Geofence $geofence): void
    {
        $this->published[$queueId] = $geofence;
    }

    public function publishedGeofenceFor(string $queueId): ?Geofence
    {
        return $this->published[$queueId] ?? null;
    }
}
