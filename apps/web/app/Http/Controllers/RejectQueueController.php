<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RendersQueueModerationResponses;
use App\Http\Requests\RejectQueueRequest;
use Illuminate\Http\JsonResponse;
use RowBuddy\Queues\Application\QueueModerationService;
use RowBuddy\Queues\Exceptions\InvalidQueueStatusTransition;
use RowBuddy\SharedKernel\Exceptions\NotFoundException;

final class RejectQueueController extends Controller
{
    use RendersQueueModerationResponses;

    public function __invoke(RejectQueueRequest $request, string $queueId, QueueModerationService $service): JsonResponse
    {
        try {
            $queue = $service->reject($queueId, (string) auth()->id(), $request->string('reason')->toString());
        } catch (NotFoundException) {
            return $this->notFound();
        } catch (InvalidQueueStatusTransition) {
            return $this->invalidTransition();
        }

        return response()->json(['data' => $this->toResponse($queue), 'message' => __('queues.moderation.rejected')]);
    }
}
