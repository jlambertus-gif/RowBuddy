<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RowBuddy\Administration\Application\DisputeCorrectionService;
use RowBuddy\Administration\Exceptions\AdministrativeActionReasonRequired;
use RowBuddy\Administration\Exceptions\AdministrativeTargetNotFound;

final class RecordDisputeCorrectionController extends Controller
{
    public function __invoke(string $disputeId, Request $request, DisputeCorrectionService $service): JsonResponse
    {
        $validated = $request->validate([
            'note' => ['required', 'string'],
        ]);

        try {
            $service->recordCorrection($disputeId, (string) auth()->id(), $validated['note']);
        } catch (AdministrativeTargetNotFound) {
            return response()->json(['message' => __('disputes.review.not_found')], 404);
        } catch (AdministrativeActionReasonRequired) {
            return response()->json(['message' => __('disputes.review.note_required')], 422);
        }

        return response()->json(['message' => __('disputes.review.correction_recorded')]);
    }
}
