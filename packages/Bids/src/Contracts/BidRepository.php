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
     * repository has no locking concept of its own. A thin wrapper around
     * {@see findHighestBidFor()} — the ordering logic lives in exactly
     * one place.
     */
    public function highestAmountFor(string $auctionId): ?Money;

    /**
     * The full highest bid for this auction, or null if none exist yet —
     * everything Auctions' WinningBidLookup adapter needs (ADR-013 §5).
     * Ordered deterministically: amount DESC, placedAt ASC, id ASC. A tie
     * on amount is structurally impossible today (a bid must strictly
     * exceed the current highest), but the query does not rely on that
     * holding forever.
     */
    public function findHighestBidFor(string $auctionId): ?Bid;

    public function findById(string $id): ?Bid;

    /**
     * The total number of bids recorded for this auction — used only for
     * public read presentation (Phase 9, ADR-027 Architecture Refinements
     * §1); never consulted by bid-placement validation itself.
     */
    public function countFor(string $auctionId): int;
}
