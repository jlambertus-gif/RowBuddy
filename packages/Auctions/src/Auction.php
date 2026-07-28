<?php

declare(strict_types=1);

namespace RowBuddy\Auctions;

use DateTimeImmutable;
use RowBuddy\Auctions\Application\LiveProximityChecker;
use RowBuddy\Auctions\Events\AuctionCancelled;
use RowBuddy\Auctions\Events\AuctionClosingDeadlineExtended;
use RowBuddy\Auctions\Events\AuctionClosingStarted;
use RowBuddy\Auctions\Events\AuctionExpired;
use RowBuddy\Auctions\Events\AuctionOpened;
use RowBuddy\Auctions\Events\AuctionProximityAtRisk;
use RowBuddy\Auctions\Events\AuctionProximityRestored;
use RowBuddy\Auctions\Events\AuctionWon;
use RowBuddy\Auctions\Exceptions\IllegalStateTransition;
use RowBuddy\Auctions\Exceptions\InvalidClosingDeadline;
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
        private DateTimeImmutable $closesAt,
        private AuctionStatus $status,
        private ?string $winningBidId = null,
        private ?Money $winningAmount = null,
        private ?DateTimeImmutable $proximityAtRiskSince = null,
    ) {}

    /**
     * `closesAt` arrives here as an already-computed, explicit value —
     * this aggregate never knows or derives a default duration (ADR-013
     * §1). It only enforces the one invariant it actually owns: the
     * deadline must be after the moment the auction opened.
     *
     * @throws InvalidClosingDeadline
     */
    public static function open(
        string $id,
        string $queueId,
        string $sellerId,
        string $presenceSessionId,
        Money $startingPrice,
        DateTimeImmutable $closesAt,
        ClockInterface $clock,
    ): self {
        $openedAt = $clock->now();

        if ($closesAt <= $openedAt) {
            throw InvalidClosingDeadline::forAuction($id);
        }

        $auction = new self(
            id: $id,
            queueId: $queueId,
            sellerId: $sellerId,
            presenceSessionId: $presenceSessionId,
            startingPrice: $startingPrice,
            openedAt: $openedAt,
            closesAt: $closesAt,
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
        DateTimeImmutable $closesAt,
        AuctionStatus $status,
        ?string $winningBidId,
        ?Money $winningAmount,
        ?DateTimeImmutable $proximityAtRiskSince = null,
    ): self {
        return new self(
            id: $id,
            queueId: $queueId,
            sellerId: $sellerId,
            presenceSessionId: $presenceSessionId,
            startingPrice: $startingPrice,
            openedAt: $openedAt,
            closesAt: $closesAt,
            status: $status,
            winningBidId: $winningBidId,
            winningAmount: $winningAmount,
            proximityAtRiskSince: $proximityAtRiskSince,
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

    public function closesAt(): DateTimeImmutable
    {
        return $this->closesAt;
    }

    /**
     * Applies an already-decided new deadline (ADR-013 §2) — the caller
     * (a SoftCloseExtender collaborator, using AntiSnipingPolicy) decides
     * *whether* and *by how much* to extend; this aggregate only accepts
     * that outcome as a fact, the same way selectWinningBid() accepts an
     * already-decided winning bid.
     *
     * @throws IllegalStateTransition
     */
    public function extendClosingDeadline(DateTimeImmutable $newClosesAt, ClockInterface $clock): void
    {
        $this->guardStatus(AuctionStatus::Open, 'extend the closing deadline');

        $this->closesAt = $newClosesAt;
        $this->recordedEvents[] = new AuctionClosingDeadlineExtended($clock, $this->id, $newClosesAt);
    }

    /**
     * The moment a live-proximity check (ADR-011) first detected this
     * auction as stale — null while not at risk. Set once; not refreshed
     * by subsequent stale detections, so a grace period measured from it
     * is not extended indefinitely.
     */
    public function proximityAtRiskSince(): ?DateTimeImmutable
    {
        return $this->proximityAtRiskSince;
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
     * Live-proximity enforcement (ADR-011, Sprint 4). Invoked only by
     * domain commands that act on an already-existing active auction —
     * never by a plain read — via {@see LiveProximityChecker}.
     *
     * @throws IllegalStateTransition
     */
    public function flagProximityAtRisk(ClockInterface $clock): void
    {
        $this->guardActiveLifecycle('flag proximity at risk');

        if ($this->proximityAtRiskSince !== null) {
            throw IllegalStateTransition::forAuctionAlreadyAtRisk($this->id);
        }

        $this->proximityAtRiskSince = $clock->now();
        $this->recordedEvents[] = new AuctionProximityAtRisk($clock, $this->id);
    }

    /**
     * @throws IllegalStateTransition
     */
    public function restoreProximity(ClockInterface $clock): void
    {
        $this->guardActiveLifecycle('restore proximity');

        if ($this->proximityAtRiskSince === null) {
            throw IllegalStateTransition::forAuctionNotAtRisk($this->id);
        }

        $this->proximityAtRiskSince = null;
        $this->recordedEvents[] = new AuctionProximityRestored($clock, $this->id);
    }

    /**
     * Covers both ADR-011 §2 cases: the grace period after staleness
     * elapsing, and an immediate cancellation when the backing
     * PresenceSession is no longer active (§3) — allowed regardless of
     * whether the auction was ever flagged at risk first.
     *
     * @throws IllegalStateTransition
     */
    public function cancelForProximityLoss(ClockInterface $clock): void
    {
        $this->guardActiveLifecycle('cancel for proximity loss');

        $this->status = AuctionStatus::Cancelled;
        $this->recordedEvents[] = new AuctionCancelled($clock, $this->id);
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
     * @throws IllegalStateTransition
     */
    private function guardActiveLifecycle(string $attemptedTransition): void
    {
        if ($this->status !== AuctionStatus::Open && $this->status !== AuctionStatus::Closing) {
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
