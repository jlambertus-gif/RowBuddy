<?php

declare(strict_types=1);

namespace RowBuddy\Auctions\Application;

use DateTimeImmutable;
use RowBuddy\Auctions\Auction;
use RowBuddy\Auctions\Contracts\AntiSnipingPolicy;
use RowBuddy\Auctions\Contracts\AuctionRepository;
use RowBuddy\Auctions\Contracts\DomainEventPublisher;
use RowBuddy\Auctions\ValueObjects\AuctionStatus;
use RowBuddy\SharedKernel\Contracts\ClockInterface;

/**
 * Applies ADR-013 §2's anti-sniping extension: only ever reachable after
 * a bid has already been validated and recorded — there is no code path
 * from a rejected or invalid bid attempt into this class at all. Each
 * extension is calculated from the auction's *current* closesAt, never
 * from the bid's own timestamp, so it always pushes the deadline
 * forward, never resets it.
 */
final class SoftCloseExtender
{
    public function __construct(
        private readonly AuctionRepository $auctions,
        private readonly AntiSnipingPolicy $antiSniping,
        private readonly DomainEventPublisher $events,
        private readonly ClockInterface $clock,
    ) {}

    public function applyIfWithinWindow(Auction $auction, DateTimeImmutable $acceptedAt): Auction
    {
        if ($auction->status() !== AuctionStatus::Open) {
            return $auction;
        }

        $windowStart = $auction->closesAt()->modify('-'.$this->antiSniping->softCloseWindowInSeconds().' seconds');

        if ($acceptedAt < $windowStart || $acceptedAt >= $auction->closesAt()) {
            return $auction;
        }

        $newClosesAt = $auction->closesAt()->modify('+'.$this->antiSniping->extensionInSeconds().' seconds');
        $auction->extendClosingDeadline($newClosesAt, $this->clock);

        $this->auctions->save($auction);

        foreach ($auction->releaseEvents() as $event) {
            $this->events->publish($event);
        }

        return $auction;
    }
}
