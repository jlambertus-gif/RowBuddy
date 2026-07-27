<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RendersPresenceSessionResponses;
use App\Http\Requests\StartPresenceSessionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use RowBuddy\QueuePresence\Application\PresenceSessionService;
use RowBuddy\QueuePresence\Exceptions\DuplicateActivePresenceSession;
use RowBuddy\QueuePresence\Exceptions\PresenceQueueUnavailable;

final class StartPresenceSessionController extends Controller
{
    use RendersPresenceSessionResponses;

    public function __invoke(StartPresenceSessionRequest $request, PresenceSessionService $service): JsonResponse
    {
        $sellerId = (string) $request->user()->id;

        try {
            $session = $service->start(
                id: (string) Str::uuid(),
                queueId: $request->string('queue_id')->toString(),
                sellerId: $sellerId,
            );
        } catch (PresenceQueueUnavailable) {
            return $this->queueUnavailable();
        } catch (DuplicateActivePresenceSession) {
            return $this->duplicateActiveSession();
        }

        $confidence = $service->currentConfidenceScore($session->id, $sellerId);

        return response()->json(['data' => $this->toResponse($session, $confidence)], 201);
    }
}
