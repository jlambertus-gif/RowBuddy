<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\JsonResponse;
use RowBuddy\Bids\Bid;

/**
 * Shared Bid -> JSON shape and known-rejection -> JSON error shape for
 * PlaceBidController — no business logic, just serialization, the same
 * role RendersQueueResponses/RendersPresenceSessionResponses play for
 * their own controllers. Never exposes another bidder's identity: only
 * the caller's own placed bid is ever rendered through this trait.
 */
trait RendersBidResponses
{
    /**
     * @return array<string, mixed>
     */
    private function toResponse(Bid $bid): array
    {
        return [
            'id' => $bid->id,
            'auction_id' => $bid->auctionId,
            'amount' => [
                'amount_minor_units' => $bid->amount->minorUnits,
                'currency' => (string) $bid->amount->currency,
            ],
            'placed_at' => $bid->placedAt->format(DATE_ATOM),
        ];
    }

    private function notFound(): JsonResponse
    {
        return response()->json(['message' => __('auctions.errors.not_found')], 404);
    }

    private function rejected(string $reasonKey, int $status = 422): JsonResponse
    {
        return response()->json(['message' => __("bids.errors.{$reasonKey}")], $status);
    }

    /**
     * A concurrent request with the same Idempotency-Key is still being
     * resolved. Distinct from rejected(): the client is explicitly told,
     * via both the message and this structured field, that retrying this
     * exact request with the same Idempotency-Key is safe (ADR-027
     * Sprint 2 Decision 3) — never a signal to give up or generate a new
     * key.
     */
    private function placementInProgress(): JsonResponse
    {
        return response()->json([
            'message' => __('bids.errors.placement_in_progress'),
            'retryable' => true,
        ], 409);
    }

    private function idempotencyKeyRequired(): JsonResponse
    {
        return response()->json(['message' => __('bids.errors.idempotency_key_required')], 400);
    }
}
