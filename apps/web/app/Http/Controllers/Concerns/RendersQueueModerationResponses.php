<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\JsonResponse;
use RowBuddy\Queues\Exceptions\QueueSubmissionBlocked;
use RowBuddy\Queues\Queue;

/**
 * Shared response-shaping for the moderation controllers — no business
 * logic, just translating a Queue aggregate or a known domain exception
 * into a plain JSON shape without leaking internals (exception classes,
 * raw status-transition messages) into the response.
 */
trait RendersQueueModerationResponses
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

    private function notFound(): JsonResponse
    {
        return response()->json(['message' => __('queues.moderation.not_found')], 404);
    }

    private function invalidTransition(): JsonResponse
    {
        return response()->json(['message' => __('queues.moderation.invalid_transition')], 422);
    }

    private function blocked(QueueSubmissionBlocked $exception): JsonResponse
    {
        return response()->json(['message' => __("queues.blocked.{$exception->reason}")], 422);
    }
}
