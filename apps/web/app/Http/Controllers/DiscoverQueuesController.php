<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RendersQueueResponses;
use App\Http\Requests\DiscoverQueuesRequest;
use Illuminate\Http\JsonResponse;
use RowBuddy\Queues\Application\Discovery\DiscoveredQueue;
use RowBuddy\Queues\Application\Discovery\QueueDiscoveryService;
use RowBuddy\SharedKernel\ValueObjects\GeoPoint;

final class DiscoverQueuesController extends Controller
{
    use RendersQueueResponses;

    public function __invoke(DiscoverQueuesRequest $request, QueueDiscoveryService $service): JsonResponse
    {
        $point = new GeoPoint($request->float('latitude'), $request->float('longitude'));
        $page = $request->integer('page', 1);
        $perPage = $request->integer('per_page', 20);

        $result = $service->discover($point, $page, $perPage);

        return response()->json([
            'data' => array_map($this->toDiscoveredResponse(...), $result->items),
            'meta' => [
                'page' => $result->page,
                'per_page' => $result->perPage,
                'total' => $result->total,
                'has_more' => $result->hasMore,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function toDiscoveredResponse(DiscoveredQueue $discovered): array
    {
        return [
            ...$this->toResponse($discovered->queue),
            'distance_meters' => $discovered->distanceInMeters,
        ];
    }
}
