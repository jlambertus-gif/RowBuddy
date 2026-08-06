<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RendersDisputeFilingResponses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RowBuddy\Disputes\Contracts\DisputeRepository;

/**
 * Mobile Sprint 4 (ADR-028 §3) — "a status view for the filing buyer,"
 * literally: only the buyer who filed this dispute may view it here.
 * The seller's own visibility into an open dispute against them remains
 * exclusively the existing admin-mediated surface (Phase 8) — this
 * endpoint does not extend seller self-service access, matching this
 * sprint's explicit no-administration/no-moderation boundary.
 */
final class ShowDisputeController extends Controller
{
    use RendersDisputeFilingResponses;

    public function __invoke(Request $request, DisputeRepository $disputes, string $disputeId): JsonResponse
    {
        $dispute = $disputes->findById($disputeId);

        if ($dispute === null) {
            return $this->notFound();
        }

        if ((string) $request->user()->id !== $dispute->buyerId) {
            return $this->accessDenied();
        }

        return response()->json(['data' => $this->toResponse($dispute)]);
    }
}
