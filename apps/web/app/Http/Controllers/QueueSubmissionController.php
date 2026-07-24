<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SubmitQueueRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use RowBuddy\Queues\Application\QueueSubmissionService;
use RowBuddy\Queues\Exceptions\QueueSubmissionBlocked;
use RowBuddy\SharedKernel\ValueObjects\Geofence;
use RowBuddy\SharedKernel\ValueObjects\GeoPoint;

final class QueueSubmissionController extends Controller
{
    public function __invoke(SubmitQueueRequest $request, QueueSubmissionService $service): RedirectResponse
    {
        try {
            $service->submitForApproval(
                id: (string) Str::uuid(),
                category: $request->string('category')->toString(),
                jurisdictionCountry: strtoupper($request->string('jurisdiction_country')->toString()),
                geofence: new Geofence(
                    new GeoPoint($request->float('latitude'), $request->float('longitude')),
                    $request->float('radius_meters'),
                ),
                submittedByUserId: (string) $request->user()->id,
            );
        } catch (QueueSubmissionBlocked $exception) {
            return back()
                ->withInput()
                ->withErrors(['category' => __("queues.blocked.{$exception->reason}")]);
        }

        return back()->with('status', __('queues.submission_succeeded'));
    }
}
