<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RendersQueueModerationResponses;
use Illuminate\Http\JsonResponse;
use RowBuddy\Queues\Application\QueueModerationService;

final class PendingQueuesController extends Controller
{
    use RendersQueueModerationResponses;

    public function index(QueueModerationService $service): JsonResponse
    {
        return response()->json(['data' => array_map($this->toResponse(...), $service->listPending())]);
    }
}
