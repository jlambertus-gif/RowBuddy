<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\JsonResponse;
use RowBuddy\Queues\Exceptions\QueueSubmissionBlocked;

/**
 * Shared response-shaping for the moderation controllers — no business
 * logic, just translating a known domain exception into a plain JSON
 * shape without leaking internals (exception classes, raw
 * status-transition messages) into the response. The Queue -> JSON
 * mapping itself lives in RendersQueueResponses, shared with discovery.
 */
trait RendersQueueModerationResponses
{
    use RendersQueueResponses;

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
