<?php

declare(strict_types=1);

namespace RowBuddy\Bids\Contracts;

use RowBuddy\Bids\Bid;
use RowBuddy\SharedKernel\ValueObjects\Money;

/**
 * Domain-facing persistence port. Deliberately expresses no ORM/storage
 * concept, and deliberately exposes no update or delete path — a Bid is
 * append-only once recorded (CLAUDE.md: "all accepted bids are
 * immutable").
 */
interface BidRepository
{
    public function record(Bid $bid): void;

    /**
     * The highest bid amount recorded for this auction, or null if none
     * exist yet. Safe to treat as authoritative only while the caller
     * holds the corresponding Auction row locked (ADR-012 §1) — this
     * repository has no locking concept of its own.
     */
    public function highestAmountFor(string $auctionId): ?Money;

    public function findById(string $id): ?Bid;
}
