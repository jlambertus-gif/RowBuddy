<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RendersQueueModerationResponses;
use Illuminate\Http\JsonResponse;
use RowBuddy\Queues\Application\QueueModerationService;
use RowBuddy\Queues\Exceptions\InvalidQueueStatusTransition;
use RowBuddy\Queues\Exceptions\QueueSubmissionBlocked;
use RowBuddy\SharedKernel\Exceptions\NotFoundException;

final class ApproveQueueController extends Controller
{
    use RendersQueueModerationResponses;

    public function __invoke(string $queueId, QueueModerationService $service): JsonResponse
    {
        try {
            $queue = $service->approve($queueId, (string) auth()->id());
        } catch (NotFoundException) {
            return $this->notFound();
        } catch (QueueSubmissionBlocked $exception) {
            return $this->blocked($exception);
        } catch (InvalidQueueStatusTransition) {
            return $this->invalidTransition();
        }

        return response()->json(['data' => $this->toResponse($queue), 'message' => __('queues.moderation.approved')]);
    }
}
