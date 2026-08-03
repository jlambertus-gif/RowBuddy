<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\JsonResponse;
use RowBuddy\Transfers\Transfer;

/**
 * Shared response-shaping for the transfer controllers — no business
 * logic, just translating a known domain exception or Transfer state into
 * a plain JSON shape. Never exposes the QR token hash, a raw storage
 * reference, or the other participant's own identity beyond the fact that
 * their confirmation has (or has not) happened yet.
 */
trait RendersTransferResponses
{
    /**
     * @return array<string, mixed>
     */
    private function toResponse(Transfer $transfer, string $requestingUserId): array
    {
        return [
            'id' => $transfer->id,
            'auction_id' => $transfer->auctionId,
            'status' => $transfer->status()->value,
            'role' => $requestingUserId === $transfer->sellerId ? 'seller' : 'buyer',
            'seller_confirmed' => $transfer->sellerConfirmedAt() !== null,
            'buyer_confirmed' => $transfer->buyerConfirmedAt() !== null,
            'confirmed_at' => $transfer->confirmedAt()?->format(DATE_ATOM),
            'expires_at' => $transfer->expiresAt->format(DATE_ATOM),
        ];
    }

    private function notFound(): JsonResponse
    {
        return response()->json(['message' => __('transfers.errors.not_found')], 404);
    }

    private function accessDenied(): JsonResponse
    {
        return response()->json(['message' => __('transfers.errors.access_denied')], 403);
    }

    private function invalidQrToken(): JsonResponse
    {
        return response()->json(['message' => __('transfers.errors.invalid_qr_token')], 422);
    }

    private function outsideGeofence(): JsonResponse
    {
        return response()->json(['message' => __('transfers.errors.outside_geofence')], 422);
    }

    private function illegalState(): JsonResponse
    {
        return response()->json(['message' => __('transfers.errors.illegal_state')], 422);
    }

    private function qrTokenUnavailable(): JsonResponse
    {
        return response()->json(['message' => __('transfers.errors.qr_token_unavailable')], 404);
    }
}
