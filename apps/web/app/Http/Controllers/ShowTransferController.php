<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RendersTransferResponses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RowBuddy\Transfers\Contracts\TransferRepository;

/**
 * Participant-only transfer status read (Phase 9, ADR-027 Sprint 3,
 * ADR-027 Architecture Refinements §5). requestingUserId is derived
 * exclusively from the authenticated user — never accepted from request
 * input. An unknown transfer id 404s; a known transfer whose requester is
 * neither the seller nor the buyer 403s, mirroring
 * ShowEvidencePhotoUrlController's own not-found-vs-forbidden distinction.
 */
final class ShowTransferController extends Controller
{
    use RendersTransferResponses;

    public function __invoke(Request $request, TransferRepository $transfers, string $transferId): JsonResponse
    {
        $transfer = $transfers->findById($transferId);

        if ($transfer === null) {
            return $this->notFound();
        }

        $requestingUserId = (string) $request->user()->id;

        if ($requestingUserId !== $transfer->sellerId && $requestingUserId !== $transfer->buyerId) {
            return $this->accessDenied();
        }

        return response()->json(['data' => $this->toResponse($transfer, $requestingUserId)]);
    }
}
