<?php

declare(strict_types=1);

namespace App\Infrastructure;

use DateTimeImmutable;
use RowBuddy\Auctions\Application\AuctionClosingEvaluator;
use RowBuddy\Auctions\Application\LiveProximityChecker;
use RowBuddy\Auctions\Application\SoftCloseExtender;
use RowBuddy\Auctions\Auction;
use RowBuddy\Auctions\Contracts\AntiSnipingPolicy;
use RowBuddy\Auctions\Contracts\AuctionRepository;
use RowBuddy\Auctions\Contracts\SellerPresenceVerification;
use RowBuddy\Auctions\Contracts\WinningBidLookup;
use RowBuddy\Auctions\ValueObjects\AuctionStatus;
use RowBuddy\Bids\Contracts\AuctionGateway;
use RowBuddy\Bids\ValueObjects\AuctionLockResult;
use RowBuddy\Bids\ValueObjects\AuctionSnapshot;
use RowBuddy\SharedKernel\Contracts\ClockInterface;

/**
 * Bridges Bids' {@see AuctionGateway} port (ADR-012 §5, ADR-013 §3) to
 * Auctions' own {@see AuctionRepository}, {@see LiveProximityChecker},
 * {@see AuctionClosingEvaluator}, and {@see SoftCloseExtender} — this
 * class is the one place in the codebase allowed to depend on both
 * packages at once, because apps/web is the composition root, not either
 * module itself (see tests/Architecture/ModuleBoundaryTest.php). Neither
 * package depends on the other's internals; packages/Bids never imports
 * anything from packages/Auctions.
 *
 * Must only be called from within an active transaction — the
 * `findByIdForUpdate()` lock is only meaningful there (ADR-012 §1).
 *
 * Each method constructs its own **local** evaluator(s) for the duration
 * of the call, injecting a {@see CollectingDomainEventPublisher} instead
 * of the real, container-bound publisher — this is what keeps any
 * resulting event from reaching listeners before the enclosing
 * bid-placement transaction commits (ADR-012 §2). `lockAndCheckForBidding()`
 * is `LiveProximityChecker`'s and `AuctionClosingEvaluator`'s first real
 * caller, exactly where ADR-011 §5 and ADR-013 §4 anticipated it would
 * arrive.
 */
final class EloquentAuctionGateway implements AuctionGateway
{
    public function __construct(
        private readonly AuctionRepository $auctions,
        private readonly SellerPresenceVerification $presenceVerification,
        private readonly WinningBidLookup $winningBids,
        private readonly AntiSnipingPolicy $antiSniping,
        private readonly ClockInterface $clock,
    ) {}

    public function lockAndCheckForBidding(string $auctionId): AuctionLockResult
    {
        $auction = $this->auctions->findByIdForUpdate($auctionId);

        if ($auction === null) {
            return new AuctionLockResult(null, []);
        }

        $collector = new CollectingDomainEventPublisher;

        $proximityChecker = new LiveProximityChecker($this->auctions, $this->presenceVerification, $collector, $this->clock);
        $auction = $proximityChecker->check($auction);

        $closingEvaluator = new AuctionClosingEvaluator($this->auctions, $this->winningBids, $collector, $this->clock);
        $auction = $closingEvaluator->evaluate($auction);

        return new AuctionLockResult($this->toSnapshot($auction), $collector->events);
    }

    public function applyAcceptedBidEffects(string $auctionId, DateTimeImmutable $acceptedAt): AuctionLockResult
    {
        $auction = $this->auctions->findByIdForUpdate($auctionId);

        if ($auction === null) {
            return new AuctionLockResult(null, []);
        }

        $collector = new CollectingDomainEventPublisher;
        $extender = new SoftCloseExtender($this->auctions, $this->antiSniping, $collector, $this->clock);
        $auction = $extender->applyIfWithinWindow($auction, $acceptedAt);

        return new AuctionLockResult($this->toSnapshot($auction), $collector->events);
    }

    private function toSnapshot(Auction $auction): AuctionSnapshot
    {
        return new AuctionSnapshot(
            $auction->sellerId,
            $auction->startingPrice,
            $auction->status() === AuctionStatus::Open,
        );
    }
}
