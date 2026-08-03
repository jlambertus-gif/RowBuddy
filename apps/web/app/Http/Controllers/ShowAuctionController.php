<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\AuctionPublicSnapshotAssembler;
use Illuminate\Http\JsonResponse;

/**
 * The public Auction read endpoint (Phase 9, ADR-027 Architecture
 * Refinements §1) — no authentication required, and the response is
 * built exclusively through {@see AuctionPublicSnapshotAssembler}'s
 * allowlist. Never serializes the Auction aggregate or Eloquent model
 * directly.
 */
final class ShowAuctionController extends Controller
{
    public function __invoke(string $auctionId, AuctionPublicSnapshotAssembler $assembler): JsonResponse
    {
        $snapshot = $assembler->assembleForDiscovery($auctionId);

        if ($snapshot === null) {
            return response()->json(['message' => __('auctions.errors.not_found')], 404);
        }

        return response()->json(['data' => $snapshot]);
    }
}
