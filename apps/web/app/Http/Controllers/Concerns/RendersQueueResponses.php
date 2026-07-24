<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use RowBuddy\Queues\Queue;

/**
 * Shared Queue -> JSON shape, used by any controller that returns a
 * queue (moderation, discovery) — no business logic, just serialization.
 */
trait RendersQueueResponses
{
    /**
     * @return array<string, mixed>
     */
    private function toResponse(Queue $queue): array
    {
        return [
            'id' => $queue->id,
            'category' => $queue->category,
            'jurisdiction_country' => $queue->jurisdictionCountry,
            'authorship' => $queue->authorship->value,
            'organizer_reference' => $queue->organizerReference,
            'status' => $queue->status()->value,
            'geofence' => [
                'latitude' => $queue->geofence->center->latitude,
                'longitude' => $queue->geofence->center->longitude,
                'radius_meters' => $queue->geofence->radiusInMeters,
            ],
        ];
    }
}
