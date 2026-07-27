<?php

declare(strict_types=1);

namespace RowBuddy\Auctions;

use DateTimeImmutable;
use RowBuddy\Auctions\Events\AuctionClosingStarted;
use RowBuddy\Auctions\Events\AuctionExpired;
use RowBuddy\Auctions\Events\AuctionOpened;
use RowBuddy\Auctions\Events\AuctionWon;
use RowBuddy\Auctions\Exceptions\IllegalStateTransition;
use RowBuddy\Auctions\ValueObjects\AuctionStatus;
use RowBuddy\SharedKernel\Contracts\ClockInterface;
use RowBuddy\SharedKernel\Contracts\DomainEvent;
use RowBuddy\SharedKernel\ValueObjects\Money;

/**
 * Aggregate root for an auction of a seller's verified queue position
 * (Phase 3, docs/roadmap.md). Owns only the Open/Closing/Won/Expired state
 * machine and the immutability of an accepted winning bid — it never places
 * or evaluates bids itself. Per docs/product/claude-mvp-analysis.md §6,
 * Auctions "listens to Bids' events rather than owning bid writes
 * directly": {@see selectWinningBid()} takes an already-decided winning bid
 * as a primitive, the same way QueuePresence's `ConfidenceScorer` takes
 * already-decided primitives rather than computing them itself.
 *
 * Confidence-tier verification (ADR-010) and the one-session-per-auction
 * MVP restriction (ADR-009 §4) are preconditions enforced by the
 * application service that calls {@see open()}, not by this aggregate —
 * `presenceSessionId` arrives here as an already-verified fact, the same
 * way QueuePresence's `PresenceSession` never verifies GPS signals itself.
 * This aggregate has no dependency, direct or otherwise, on the
 * QueuePresence package — the comparison above is stylistic, not a code
 * reference.
 */
final class Auction
{
    /** @var list<DomainEvent> */
    private array $recordedEvents = [];

    private function __construct(
        public readonly string $id,
        public readonly string $queueId,
        public readonly string $sellerId,
        public readonly string $presenceSessionId,
        public readonly Money $startingPrice,
        public readonly DateTimeImmutable $openedAt,
        private AuctionStatus $status,
        private ?string $winningBidId = null,
        private ?Money $winningAmount = null,
    ) {}

    public static function open(
        string $id,
        string $queueId,
        string $sellerId,
        string $presenceSessionId,
        Money $startingPrice,
        ClockInterface $clock,
    ): self {
        $auction = new self(
            id: $id,
            queueId: $queueId,
            sellerId: $sellerId,
            presenceSessionId: $presenceSessionId,
            startingPrice: $startingPrice,
            openedAt: $clock->now(),
            status: AuctionStatus::Open,
        );

        $auction->recordedEvents[] = new AuctionOpened(
            $clock,
            $id,
            $queueId,
            $sellerId,
            $presenceSessionId,
            $startingPrice,
        );

        return $auction;
    }

    /**
     * Reconstitutes an Auction from previously persisted state. Unlike
     * open() above, this never raises domain events — loading an auction
     * back out of storage is not a business event in itself.
     */
    public static function fromPersistence(
        string $id,
        string $queueId,
        string $sellerId,
        string $presenceSessionId,
        Money $startingPrice,
        DateTimeImmutable $openedAt,
        AuctionStatus $status,
        ?string $winningBidId,
        ?Money $winningAmount,
    ): self {
        return new self(
            id: $id,
            queueId: $queueId,
            sellerId: $sellerId,
            presenceSessionId: $presenceSessionId,
            startingPrice: $startingPrice,
            openedAt: $openedAt,
            status: $status,
            winningBidId: $winningBidId,
            winningAmount: $winningAmount,
        );
    }

    public function status(): AuctionStatus
    {
        return $this->status;
    }

    public function winningBidId(): ?string
    {
        return $this->winningBidId;
    }

    public function winningAmount(): ?Money
    {
        return $this->winningAmount;
    }

    /**
     * @throws IllegalStateTransition
     */
    public function startClosing(ClockInterface $clock): void
    {
        $this->guardStatus(AuctionStatus::Open, 'start closing');

        $this->status = AuctionStatus::Closing;
        $this->recordedEvents[] = new AuctionClosingStarted($clock, $this->id);
    }

    /**
     * Records an already-decided winning bid. The winning bid itself is
     * placed and evaluated entirely within the Bids bounded context; this
     * method only accepts that outcome as a fact. Once recorded, the
     * winning bid is immutable: the status guard below makes a second call
     * fail rather than silently overwrite it, satisfying "all accepted
     * bids are immutable" (CLAUDE.md).
     *
     * @throws IllegalStateTransition
     */
    public function selectWinningBid(string $winningBidId, Money $winningAmount, ClockInterface $clock): void
    {
        $this->guardStatus(AuctionStatus::Closing, 'select a winning bid');

        $this->status = AuctionStatus::Won;
        $this->winningBidId = $winningBidId;
        $this->winningAmount = $winningAmount;
        $this->recordedEvents[] = new AuctionWon($clock, $this->id, $winningBidId, $winningAmount);
    }

    /**
     * @throws IllegalStateTransition
     */
    public function expireWithoutWinningBid(ClockInterface $clock): void
    {
        $this->guardStatus(AuctionStatus::Closing, 'expire without a winning bid');

        $this->status = AuctionStatus::Expired;
        $this->recordedEvents[] = new AuctionExpired($clock, $this->id);
    }

    /**
     * @throws IllegalStateTransition
     */
    private function guardStatus(AuctionStatus $expected, string $attemptedTransition): void
    {
        if ($this->status !== $expected) {
            throw IllegalStateTransition::forAuction($this->id, $attemptedTransition, $this->status);
        }
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
