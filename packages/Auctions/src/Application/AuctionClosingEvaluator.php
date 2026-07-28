<?php

declare(strict_types=1);

namespace RowBuddy\Auctions\Application;

use RowBuddy\Auctions\Auction;
use RowBuddy\Auctions\Contracts\AuctionRepository;
use RowBuddy\Auctions\Contracts\DomainEventPublisher;
use RowBuddy\Auctions\Contracts\WinningBidLookup;
use RowBuddy\Auctions\ValueObjects\AuctionStatus;
use RowBuddy\SharedKernel\Contracts\ClockInterface;

/**
 * Lazily evaluates whether an Open auction is due to close (ADR-013 §4),
 * exactly the same pattern as LiveProximityChecker: invoked only by
 * commands that act on an already-existing active auction, never by a
 * plain read, under the auction's own row lock — no scheduler.
 *
 * When due, startClosing() and the winner-selection/expiry transition
 * are applied in memory together, then persisted and released as one
 * write with both resulting events — not two separate saves.
 */
final class AuctionClosingEvaluator
{
    public function __construct(
        private readonly AuctionRepository $auctions,
        private readonly WinningBidLookup $winningBids,
        private readonly DomainEventPublisher $events,
        private readonly ClockInterface $clock,
    ) {}

    public function evaluate(Auction $auction): Auction
    {
        if ($auction->status() !== AuctionStatus::Open) {
            return $auction;
        }

        if ($this->clock->now() < $auction->closesAt()) {
            return $auction;
        }

        $auction->startClosing($this->clock);

        $winningBid = $this->winningBids->highestBidFor($auction->id);

        if ($winningBid === null) {
            $auction->expireWithoutWinningBid($this->clock);
        } else {
            $auction->selectWinningBid($winningBid->bidId, $winningBid->amount, $this->clock);
        }

        $this->auctions->save($auction);

        foreach ($auction->releaseEvents() as $event) {
            $this->events->publish($event);
        }

        return $auction;
    }
}
