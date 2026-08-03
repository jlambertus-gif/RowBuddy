<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RendersTransferResponses;
use App\Listeners\TriggerTransferInitiation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use RowBuddy\Transfers\Contracts\TransferRepository;

/**
 * Buyer-only, one-time-delivery reveal of the plaintext QR/confirmation
 * token (Phase 9, ADR-027 Sprint 3) — the token itself is never persisted
 * by the Transfers package (ADR-017 §2); this reads the short-lived cache
 * entry {@see TriggerTransferInitiation} writes, TTL-bound
 * by the transfer's own expiry, so it naturally stops being retrievable
 * once the handoff window closes. requestingUserId is derived exclusively
 * from the authenticated user — never accepted from request input, and
 * only the buyer (who must display the token to the seller) may retrieve
 * it. Not a second confirmation protocol: this only delivers the secret
 * value the existing hash-based confirmation already expects the seller
 * to submit.
 */
final class ShowTransferQrTokenController extends Controller
{
    use RendersTransferResponses;

    public function __invoke(Request $request, TransferRepository $transfers, string $transferId): JsonResponse
    {
        $transfer = $transfers->findById($transferId);

        if ($transfer === null) {
            return $this->notFound();
        }

        if ((string) $request->user()->id !== $transfer->buyerId) {
            return $this->accessDenied();
        }

        $qrToken = Cache::get("transfers.{$transferId}.qr_token");

        if ($qrToken === null) {
            return $this->qrTokenUnavailable();
        }

        return response()->json(['data' => ['qr_token' => $qrToken]]);
    }
}
