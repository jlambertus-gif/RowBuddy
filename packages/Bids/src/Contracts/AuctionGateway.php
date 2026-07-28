<?php

declare(strict_types=1);

namespace RowBuddy\Bids\Contracts;

use DateTimeImmutable;
use RowBuddy\Bids\ValueObjects\AuctionLockResult;

/**
 * Domain-facing read+lock port (ADR-012 §1, §5; ADR-013 §3): lets Bids
 * check and lock an auction for the duration of a bid-placement
 * transaction without depending on Auctions' Eloquent models,
 * repositories, services, or enums. Owned by Bids, in Bids' own
 * vocabulary — implemented by an apps/web composition-root adapter that
 * internally runs Auctions' LiveProximityChecker and
 * AuctionClosingEvaluator as part of the same lock (ADR-011 §5's
 * standing contract: this is that checker's first real caller).
 */
interface AuctionGateway
{
    /**
     * Locks the auction row for the remainder of the caller's
     * transaction and returns its current bidding-relevant state. Must
     * only be called from within an active transaction (see
     * TransactionManager). Returns a null snapshot if no auction exists
     * with this id.
     */
    public function lockAndCheckForBidding(string $auctionId): AuctionLockResult;

    /**
     * Applies whatever legitimate effects an already-accepted bid has on
     * the auction — currently, ADR-013 §2's soft-close extension, applied
     * only if `$acceptedAt` falls within the anti-sniping window. Must
     * only be called after BidRepository::record() has already succeeded,
     * within the same transaction and lock as the call that accepted it —
     * there is no path from a rejected bid attempt into this method.
     */
    public function applyAcceptedBidEffects(string $auctionId, DateTimeImmutable $acceptedAt): AuctionLockResult;
}
