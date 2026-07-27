<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RendersPresenceSessionResponses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RowBuddy\QueuePresence\Application\PresenceSessionService;
use RowBuddy\QueuePresence\Exceptions\PresenceSessionAccessDenied;
use RowBuddy\QueuePresence\Exceptions\PresenceSessionNotActive;
use RowBuddy\SharedKernel\Exceptions\NotFoundException;

final class EndPresenceSessionController extends Controller
{
    use RendersPresenceSessionResponses;

    public function __invoke(Request $request, PresenceSessionService $service, string $sessionId): JsonResponse
    {
        $requestingUserId = (string) $request->user()->id;

        try {
            $session = $service->end($sessionId, $requestingUserId);
        } catch (NotFoundException) {
            return $this->notFound();
        } catch (PresenceSessionAccessDenied) {
            return $this->accessDenied();
        } catch (PresenceSessionNotActive) {
            return $this->notActive();
        }

        $confidence = $service->currentConfidenceScore($session->id, $requestingUserId);

        return response()->json(['data' => $this->toResponse($session, $confidence)]);
    }
}
