<?php

declare(strict_types=1);

namespace RowBuddy\QueuePresence\Contracts;

use RowBuddy\SharedKernel\ValueObjects\Geofence;

/**
 * QueuePresence needs to check a GPS ping against the queue's geofence
 * ({@see Geofence::contains()}) without depending on the Queues package's
 * internals (its Eloquent models, repository, or Queue aggregate) —
 * QueuePresence owns this narrow, read-only port; apps/web's composition
 * root supplies the adapter that bridges to Queues' own application layer.
 * Only a published queue's geofence is ever returned: presence capture
 * against a pending/rejected/nonexistent queue is never meaningful.
 */
interface QueueGeofenceLookup
{
    public function publishedGeofenceFor(string $queueId): ?Geofence;
}
