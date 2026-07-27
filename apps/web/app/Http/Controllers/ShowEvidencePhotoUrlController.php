<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RendersPresenceSessionResponses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RowBuddy\QueuePresence\Application\PresenceSessionService;
use RowBuddy\QueuePresence\Exceptions\PresenceSessionAccessDenied;
use RowBuddy\SharedKernel\Exceptions\NotFoundException;

final class ShowEvidencePhotoUrlController extends Controller
{
    use RendersPresenceSessionResponses;

    public function __invoke(Request $request, PresenceSessionService $service, string $sessionId, string $photoId): JsonResponse
    {
        try {
            $url = $service->evidencePhotoUrl($sessionId, $photoId, (string) $request->user()->id);
        } catch (NotFoundException) {
            return $this->notFound();
        } catch (PresenceSessionAccessDenied) {
            return $this->accessDenied();
        }

        return response()->json([
            'data' => [
                'url' => $url->url,
                'expires_at' => $url->expiresAt->format(DATE_ATOM),
            ],
        ]);
    }
}
