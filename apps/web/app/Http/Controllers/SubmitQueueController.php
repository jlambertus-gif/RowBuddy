<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SubmitQueueRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use RowBuddy\Queues\Application\QueueSubmissionService;
use RowBuddy\Queues\Exceptions\QueueSubmissionBlocked;
use RowBuddy\Queues\Exceptions\SubmitterAccountSuspended;
use RowBuddy\SharedKernel\ValueObjects\Geofence;
use RowBuddy\SharedKernel\ValueObjects\GeoPoint;

/**
 * Mobile Sprint 5 (ADR-028 §3). The JSON variant of the existing
 * `POST /queues` route — reuses `QueueSubmissionService::submitForApproval()`
 * and `SubmitQueueRequest` verbatim; a native client needs a parseable
 * JSON response, not the Inertia-form redirect pattern the web route
 * uses. submittedByUserId is derived exclusively from the authenticated
 * user, never accepted from request input.
 */
final class SubmitQueueController extends Controller
{
    public function __invoke(SubmitQueueRequest $request, QueueSubmissionService $service): JsonResponse
    {
        try {
            $queue = $service->submitForApproval(
                id: (string) Str::uuid(),
                category: $request->string('category')->toString(),
                jurisdictionCountry: strtoupper($request->string('jurisdiction_country')->toString()),
                geofence: new Geofence(
                    new GeoPoint($request->float('latitude'), $request->float('longitude')),
                    $request->float('radius_meters'),
                ),
                submittedByUserId: (string) $request->user()->id,
            );
        } catch (SubmitterAccountSuspended) {
            return response()->json(['message' => __('queues.errors.account_suspended')], 403);
        } catch (QueueSubmissionBlocked $exception) {
            return response()->json([
                'message' => __("queues.blocked.{$exception->reason}"),
            ], 422);
        }

        return response()->json(['data' => [
            'id' => $queue->id,
            'category' => $queue->category,
            'jurisdiction_country' => $queue->jurisdictionCountry,
            'status' => $queue->status()->value,
        ]], 201);
    }
}
