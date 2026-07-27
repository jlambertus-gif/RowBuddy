<?php

declare(strict_types=1);

namespace RowBuddy\Bids;

use DateTimeImmutable;
use RowBuddy\Bids\Contracts\BidRepository;
use RowBuddy\Bids\Events\BidPlaced;
use RowBuddy\SharedKernel\Contracts\ClockInterface;
use RowBuddy\SharedKernel\Contracts\DomainEvent;
use RowBuddy\SharedKernel\ValueObjects\Money;

/**
 * A single, immutable bid placed against an auction (Phase 3, Sprint 5).
 * Deliberately has no state machine and no update/delete path anywhere —
 * unlike Auctions' `Auction` aggregate, a bid is placed once and never
 * transitions. "All accepted bids are immutable" (CLAUDE.md) is enforced
 * structurally: {@see BidRepository} exposes only `record()`, never an
 * update. Which bid is currently winning is derived by comparing amounts
 * (see `BidRepository::highestAmountFor()`), never by mutating a stored
 * row.
 *
 * Validation against the auction's current state and current highest bid
 * is entirely the concern of `Application\BidService` — this aggregate
 * only records an already-decided, already-valid placement, the same way
 * Auctions' own `Auction::selectWinningBid()` takes an already-decided
 * outcome as a primitive. This aggregate has no dependency, direct or
 * otherwise, on the Auctions package — the comparisons above are
 * stylistic, not code references.
 */
final class Bid
{
    /** @var list<DomainEvent> */
    private array $recordedEvents = [];

    private function __construct(
        public readonly string $id,
        public readonly string $auctionId,
        public readonly string $bidderId,
        public readonly Money $amount,
        public readonly DateTimeImmutable $placedAt,
    ) {}

    public static function place(
        string $id,
        string $auctionId,
        string $bidderId,
        Money $amount,
        ClockInterface $clock,
    ): self {
        $bid = new self(
            id: $id,
            auctionId: $auctionId,
            bidderId: $bidderId,
            amount: $amount,
            placedAt: $clock->now(),
        );

        $bid->recordedEvents[] = new BidPlaced($clock, $id, $auctionId, $bidderId, $amount);

        return $bid;
    }

    /**
     * Reconstitutes a Bid from previously persisted state. Unlike place()
     * above, this never raises domain events — loading a bid back out of
     * storage is not a business event in itself.
     */
    public static function fromPersistence(
        string $id,
        string $auctionId,
        string $bidderId,
        Money $amount,
        DateTimeImmutable $placedAt,
    ): self {
        return new self(
            id: $id,
            auctionId: $auctionId,
            bidderId: $bidderId,
            amount: $amount,
            placedAt: $placedAt,
        );
    }

    /**
     * @return list<DomainEvent>
     */
    public function releaseEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        return $events;
    }
}
