<?php

declare(strict_types=1);

namespace App\Infrastructure;

use RowBuddy\Auctions\Application\LiveProximityChecker;
use RowBuddy\Auctions\Contracts\AuctionRepository;
use RowBuddy\Auctions\Contracts\SellerPresenceVerification;
use RowBuddy\Auctions\ValueObjects\AuctionStatus;
use RowBuddy\Bids\Contracts\AuctionGateway;
use RowBuddy\Bids\ValueObjects\AuctionLockResult;
use RowBuddy\Bids\ValueObjects\AuctionSnapshot;
use RowBuddy\SharedKernel\Contracts\ClockInterface;

/**
 * Bridges Bids' {@see AuctionGateway} port (ADR-012 §5) to Auctions' own
 * {@see AuctionRepository} and {@see LiveProximityChecker} — this class
 * is the one place in the codebase allowed to depend on both packages at
 * once, because apps/web is the composition root, not either module
 * itself (see tests/Architecture/ModuleBoundaryTest.php). Neither
 * package depends on the other's internals; packages/Bids never imports
 * anything from packages/Auctions.
 *
 * Must only be called from within an active transaction — the
 * `findByIdForUpdate()` lock is only meaningful there (ADR-012 §1).
 *
 * Constructs its own **local** `LiveProximityChecker` for the duration of
 * each call, injecting a {@see CollectingDomainEventPublisher} instead of
 * the real, container-bound publisher — this is what keeps any resulting
 * proximity event (at-risk, restored, or cancelled) from reaching
 * listeners before the enclosing bid-placement transaction commits
 * (ADR-012 §2). This is also `LiveProximityChecker`'s first real caller,
 * exactly where ADR-011 §5 anticipated it would arrive.
 */
final class EloquentAuctionGateway implements AuctionGateway
{
    public function __construct(
        private readonly AuctionRepository $auctions,
        private readonly SellerPresenceVerification $presenceVerification,
        private readonly ClockInterface $clock,
    ) {}

    public function lockAndCheckForBidding(string $auctionId): AuctionLockResult
    {
        $auction = $this->auctions->findByIdForUpdate($auctionId);

        if ($auction === null) {
            return new AuctionLockResult(null, []);
        }

        $collector = new CollectingDomainEventPublisher;
        $checker = new LiveProximityChecker($this->auctions, $this->presenceVerification, $collector, $this->clock);
        $auction = $checker->check($auction);

        $snapshot = new AuctionSnapshot(
            $auction->sellerId,
            $auction->startingPrice,
            $auction->status() === AuctionStatus::Open,
        );

        return new AuctionLockResult($snapshot, $collector->events);
    }
}
