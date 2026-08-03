<?php

declare(strict_types=1);

namespace App\Support;

use RowBuddy\Auctions\Contracts\AuctionRepository;
use RowBuddy\Bids\Contracts\BidRepository;
use RuntimeException;

/**
 * Resolves an already-decided auction's seller/buyer pair from `AuctionWon`'s
 * or `PaymentAuthorized`'s own payload (Phase 9, ADR-027 Sprint 3) — neither
 * event carries seller/buyer ids directly (ADR-014), so this is the one
 * extra hop every reaction to either event needs. Deliberately lives in
 * apps/web, not in either package: it is allowed to depend on both
 * Auctions' AuctionRepository and Bids' BidRepository at once because
 * apps/web is the composition root, the same reasoning
 * AuctionPublicSnapshotAssembler documents for itself.
 */
final class AuctionParticipantResolver
{
    public function __construct(
        private readonly AuctionRepository $auctions,
        private readonly BidRepository $bids,
    ) {}

    public function resolve(string $auctionId, string $winningBidId): AuctionParticipants
    {
        $auction = $this->auctions->findById($auctionId);
        $bid = $this->bids->findById($winningBidId);

        if ($auction === null || $bid === null) {
            throw new RuntimeException(
                "Cannot resolve participants for auction [{$auctionId}] / winning bid [{$winningBidId}]: a required aggregate is missing."
            );
        }

        return new AuctionParticipants($auction->sellerId, $bid->bidderId);
    }
}
