<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RendersPresenceSessionResponses;
use App\Http\Requests\RecordGpsPingRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use RowBuddy\QueuePresence\Application\PresenceSessionService;
use RowBuddy\QueuePresence\Exceptions\PresenceQueueUnavailable;
use RowBuddy\QueuePresence\Exceptions\PresenceSessionAccessDenied;
use RowBuddy\QueuePresence\Exceptions\PresenceSessionNotActive;
use RowBuddy\SharedKernel\Exceptions\NotFoundException;

final class RecordGpsPingController extends Controller
{
    use RendersPresenceSessionResponses;

    public function __invoke(RecordGpsPingRequest $request, PresenceSessionService $service, string $sessionId): JsonResponse
    {
        try {
            $session = $service->recordGpsPing(
                pingId: (string) Str::uuid(),
                sessionId: $sessionId,
                requestingUserId: (string) $request->user()->id,
                latitude: $request->float('latitude'),
                longitude: $request->float('longitude'),
                accuracyInMeters: $request->float('accuracy_meters'),
            );
        } catch (NotFoundException) {
            return $this->notFound();
        } catch (PresenceSessionAccessDenied) {
            return $this->accessDenied();
        } catch (PresenceQueueUnavailable) {
            return $this->queueUnavailable();
        } catch (PresenceSessionNotActive) {
            return $this->notActive();
        }

        return response()->json(['data' => $this->toResponse($session)]);
    }
}
