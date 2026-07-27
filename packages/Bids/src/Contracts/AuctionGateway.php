<?php

declare(strict_types=1);

namespace RowBuddy\Bids\Contracts;

use RowBuddy\Bids\ValueObjects\AuctionLockResult;

/**
 * Domain-facing read+lock port (ADR-012 §1, §5): lets Bids check and lock
 * an auction for the duration of a bid-placement transaction without
 * depending on Auctions' Eloquent models, repositories, services, or
 * enums. Owned by Bids, in Bids' own vocabulary — implemented by an
 * apps/web composition-root adapter that internally runs Auctions'
 * LiveProximityChecker as part of the same lock (ADR-011 §5's standing
 * contract: this is that checker's first real caller).
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
}
