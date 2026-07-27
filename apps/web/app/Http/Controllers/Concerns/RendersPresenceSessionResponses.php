<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\JsonResponse;
use RowBuddy\QueuePresence\PresenceSession;

/**
 * Shared response-shaping for the presence-session controllers — no
 * business logic, just translating a known domain exception into a plain
 * JSON shape without leaking internals (exception classes, raw messages)
 * into the response.
 */
trait RendersPresenceSessionResponses
{
    /**
     * @return array<string, mixed>
     */
    private function toResponse(PresenceSession $session): array
    {
        return [
            'id' => $session->id,
            'queue_id' => $session->queueId,
            'seller_id' => $session->sellerId,
            'started_at' => $session->startedAt->format(DATE_ATOM),
            'status' => $session->status()->value,
        ];
    }

    private function notFound(): JsonResponse
    {
        return response()->json(['message' => __('presence.errors.not_found')], 404);
    }

    private function accessDenied(): JsonResponse
    {
        return response()->json(['message' => __('presence.errors.access_denied')], 403);
    }

    private function queueUnavailable(): JsonResponse
    {
        return response()->json(['message' => __('presence.errors.queue_unavailable')], 422);
    }

    private function duplicateActiveSession(): JsonResponse
    {
        return response()->json(['message' => __('presence.errors.duplicate_active_session')], 422);
    }

    private function notActive(): JsonResponse
    {
        return response()->json(['message' => __('presence.errors.not_active')], 422);
    }

    private function invalidPhoto(): JsonResponse
    {
        return response()->json(['message' => __('presence.errors.invalid_photo')], 422);
    }
}
