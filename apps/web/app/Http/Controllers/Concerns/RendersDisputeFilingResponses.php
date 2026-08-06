<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\JsonResponse;
use RowBuddy\Disputes\Dispute;

/**
 * Shared response-shaping for the buyer-facing dispute-filing/status
 * controllers — distinct from the existing admin-only
 * RendersDisputeCaseResponses (Phase 8), which serves a different
 * capability-gated surface entirely. Never exposes resolutionNotes
 * (unrestricted admin-authored free text) or evidenceFoundFraudulent
 * (an internal, inert observation) — mirrors DisputeResolvedMail's own
 * field-restriction discipline (ADR-025 §9).
 */
trait RendersDisputeFilingResponses
{
    /**
     * @return array<string, mixed>
     */
    private function toResponse(Dispute $dispute): array
    {
        return [
            'id' => $dispute->id,
            'transfer_id' => $dispute->transferId,
            'reason' => $dispute->reason,
            'status' => $dispute->status()->value,
            'opened_at' => $dispute->openedAt->format(DATE_ATOM),
            'resolution_outcome' => $dispute->resolutionOutcome()?->value,
            'refund_amount' => $dispute->refundAmount() !== null ? [
                'amount_minor_units' => $dispute->refundAmount()->minorUnits,
                'currency' => (string) $dispute->refundAmount()->currency,
            ] : null,
            'resolved_at' => $dispute->resolvedAt()?->format(DATE_ATOM),
        ];
    }

    private function notFound(): JsonResponse
    {
        return response()->json(['message' => __('disputes.filing.errors.not_found')], 404);
    }

    private function accessDenied(): JsonResponse
    {
        return response()->json(['message' => __('disputes.filing.errors.access_denied')], 403);
    }

    private function notAuthorized(): JsonResponse
    {
        return response()->json(['message' => __('disputes.filing.errors.not_authorized')], 403);
    }

    private function alreadyExists(): JsonResponse
    {
        return response()->json(['message' => __('disputes.filing.errors.already_exists')], 409);
    }

    private function windowElapsed(): JsonResponse
    {
        return response()->json(['message' => __('disputes.filing.errors.window_elapsed')], 422);
    }
}
