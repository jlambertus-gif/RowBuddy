<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RendersPresenceSessionResponses;
use App\Http\Requests\UploadEvidencePhotoRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use RowBuddy\QueuePresence\Application\PresenceSessionService;
use RowBuddy\QueuePresence\Exceptions\InvalidEvidencePhoto;
use RowBuddy\QueuePresence\Exceptions\PresenceSessionAccessDenied;
use RowBuddy\QueuePresence\Exceptions\PresenceSessionNotActive;
use RowBuddy\SharedKernel\Exceptions\NotFoundException;

final class UploadEvidencePhotoController extends Controller
{
    use RendersPresenceSessionResponses;

    public function __invoke(UploadEvidencePhotoRequest $request, PresenceSessionService $service, string $sessionId): JsonResponse
    {
        $photo = $request->file('photo');

        try {
            $session = $service->recordEvidencePhoto(
                photoId: (string) Str::uuid(),
                sessionId: $sessionId,
                requestingUserId: (string) $request->user()->id,
                imageContents: file_get_contents($photo->getRealPath()),
                mimeType: (string) $photo->getMimeType(),
            );
        } catch (NotFoundException) {
            return $this->notFound();
        } catch (PresenceSessionAccessDenied) {
            return $this->accessDenied();
        } catch (PresenceSessionNotActive) {
            return $this->notActive();
        } catch (InvalidEvidencePhoto) {
            return $this->invalidPhoto();
        }

        return response()->json(['data' => $this->toResponse($session)], 201);
    }
}
