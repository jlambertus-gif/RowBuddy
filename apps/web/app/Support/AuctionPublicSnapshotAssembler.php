<?php

declare(strict_types=1);

namespace App\Support;

use RowBuddy\Auctions\Auction;
use RowBuddy\Auctions\Contracts\AuctionRepository;
use RowBuddy\Auctions\ValueObjects\AuctionStatus;
use RowBuddy\Bids\Contracts\BidRepository;
use RowBuddy\SharedKernel\ValueObjects\Money;

/**
 * The single source of truth for the public, allowlisted Auction read
 * shape (Phase 9, ADR-027 Architecture Refinements §1/§3) — used by both
 * ShowAuctionController's HTTP response and BroadcastAuctionSnapshot's
 * Reverb payload (both consume this class, never the reverse — the
 * Laravel arch preset forbids anything depending on a Controller), so
 * the two can never drift out of sync with each other or with the
 * allowlist: neither ever touches Auction/Bid internals or a raw
 * domain-event payload directly.
 *
 * Deliberately lives in apps/web, not in either package: it is allowed to
 * depend on both Auctions' AuctionRepository and Bids' BidRepository at
 * once because apps/web is the composition root, the same reasoning
 * EloquentAuctionGateway documents for itself.
 */
final class AuctionPublicSnapshotAssembler
{
    /**
     * Auctions that have already resolved (Won/Expired/Cancelled) are not
     * publicly discoverable via a fresh lookup — only Open/Closing, the
     * live-bidding window this endpoint and channel exist for. A client
     * already subscribed to the Reverb channel still receives the final
     * status transition; only *new* discovery of a resolved auction is
     * blocked here.
     */
    private const DISCOVERABLE_STATUSES = [AuctionStatus::Open, AuctionStatus::Closing];

    public function __construct(
        private readonly AuctionRepository $auctions,
        private readonly BidRepository $bids,
    ) {}

    /**
     * @return array<string, mixed>|null null if the auction does not
     *                                   exist or is not publicly discoverable
     */
    public function assembleForDiscovery(string $auctionId): ?array
    {
        $auction = $this->auctions->findById($auctionId);

        if ($auction === null || ! in_array($auction->status(), self::DISCOVERABLE_STATUSES, true)) {
            return null;
        }

        return $this->assemble($auction);
    }

    /**
     * Used by the broadcast listener, which must still publish a final
     * status transition (Won/Expired/Cancelled) to already-connected
     * clients even though the auction is no longer discoverable via a
     * fresh lookup — returns null only if the auction id genuinely does
     * not exist.
     *
     * @return array<string, mixed>|null
     */
    public function assembleRegardlessOfDiscoverability(string $auctionId): ?array
    {
        $auction = $this->auctions->findById($auctionId);

        return $auction === null ? null : $this->assemble($auction);
    }

    /**
     * @return array<string, mixed>
     */
    private function assemble(Auction $auction): array
    {
        $currentPrice = $this->bids->highestAmountFor($auction->id) ?? $auction->startingPrice;
        $bidCount = $this->bids->countFor($auction->id);
        $minimumNextAmount = $auction->status() === AuctionStatus::Open
            ? new Money($currentPrice->minorUnits + 1, $currentPrice->currency)
            : null;

        return [
            'id' => $auction->id,
            'status' => $auction->status()->value,
            'current_price' => $this->money($currentPrice),
            'minimum_next_amount' => $minimumNextAmount === null ? null : $this->money($minimumNextAmount),
            'closes_at' => $auction->closesAt()->format(DATE_ATOM),
            'bid_count' => $bidCount,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function money(Money $money): array
    {
        return [
            'amount_minor_units' => $money->minorUnits,
            'currency' => (string) $money->currency,
        ];
    }
}
