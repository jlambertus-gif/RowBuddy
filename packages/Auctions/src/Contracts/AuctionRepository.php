<?php

declare(strict_types=1);

namespace RowBuddy\Auctions\Contracts;

use RowBuddy\Auctions\Auction;
use RowBuddy\Auctions\Exceptions\PresenceSessionAlreadyConsumed;

/**
 * Domain-facing persistence port. Deliberately expresses no ORM/storage
 * concept — implementations (e.g. an Eloquent adapter) translate between
 * whatever storage technology backs them and the {@see Auction} aggregate,
 * never the reverse.
 */
interface AuctionRepository
{
    /**
     * @throws PresenceSessionAlreadyConsumed if another auction already
     *                                        references this presence session (ADR-009 §4)
     */
    public function save(Auction $auction): void;

    public function findById(string $id): ?Auction;

    /**
     * Locks the auction row for the duration of the caller's transaction
     * (`SELECT ... FOR UPDATE`) — the serialization anchor Bids' bid
     * placement relies on (ADR-012 §1). Callers must already be inside a
     * transaction; this method does not open one itself.
     */
    public function findByIdForUpdate(string $id): ?Auction;
}
