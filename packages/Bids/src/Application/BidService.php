<?php

declare(strict_types=1);

namespace RowBuddy\Bids\Application;

use LogicException;
use RowBuddy\Bids\Bid;
use RowBuddy\Bids\Contracts\AuctionGateway;
use RowBuddy\Bids\Contracts\BidRepository;
use RowBuddy\Bids\Contracts\DomainEventPublisher;
use RowBuddy\Bids\Contracts\TransactionManager;
use RowBuddy\Bids\Exceptions\AuctionNotOpenForBidding;
use RowBuddy\Bids\Exceptions\BidTooLow;
use RowBuddy\Bids\Exceptions\CurrencyMismatch;
use RowBuddy\Bids\Exceptions\SellerCannotBidOnOwnAuction;
use RowBuddy\Bids\ValueObjects\BidPlacementOutcome;
use RowBuddy\Bids\ValueObjects\BidRejectionReason;
use RowBuddy\SharedKernel\Contracts\ClockInterface;
use RowBuddy\SharedKernel\Exceptions\NotFoundException;
use RowBuddy\SharedKernel\ValueObjects\Money;

/**
 * Orchestrates bid placement (ADR-012): locks the auction, runs
 * Auctions' LiveProximityChecker as part of that same lock, validates
 * the bid, and records it — all inside one transaction, all without
 * depending on Auctions' internals directly (only through
 * {@see AuctionGateway}, owned by this package).
 *
 * The transactional closure never throws for an expected rejection
 * (ADR-012 §1a) — it always returns a {@see BidPlacementOutcome}, so a
 * legitimate LiveProximityChecker transition is committed regardless of
 * whether the bid itself is ultimately accepted. Only after the
 * transaction has committed are the collected events published and the
 * accept/reject decision translated into a return value or exception.
 */
final class BidService
{
    public function __construct(
        private readonly BidRepository $bids,
        private readonly AuctionGateway $auctionGateway,
        private readonly TransactionManager $transactions,
        private readonly DomainEventPublisher $events,
        private readonly ClockInterface $clock,
    ) {}

    /**
     * @throws NotFoundException if the auction does not exist
     * @throws AuctionNotOpenForBidding
     * @throws CurrencyMismatch
     * @throws SellerCannotBidOnOwnAuction
     * @throws BidTooLow
     */
    public function place(string $bidId, string $auctionId, string $bidderId, Money $amount): Bid
    {
        $outcome = $this->transactions->run(
            fn (): BidPlacementOutcome => $this->attemptPlacement($bidId, $auctionId, $bidderId, $amount),
        );

        foreach ($outcome->events as $event) {
            $this->events->publish($event);
        }

        if ($outcome->bid !== null) {
            return $outcome->bid;
        }

        $rejectionReason = $outcome->rejectionReason;

        if ($rejectionReason === null) {
            throw new LogicException('BidPlacementOutcome has neither an accepted bid nor a rejection reason.');
        }

        throw match ($rejectionReason) {
            BidRejectionReason::AuctionNotFound => new NotFoundException("Auction [{$auctionId}] not found."),
            BidRejectionReason::AuctionNotOpenForBidding => AuctionNotOpenForBidding::forAuction($auctionId),
            BidRejectionReason::CurrencyMismatch => CurrencyMismatch::forAuction($auctionId),
            BidRejectionReason::SellerCannotBidOnOwnAuction => SellerCannotBidOnOwnAuction::forAuction($auctionId),
            BidRejectionReason::BidTooLow => BidTooLow::forAuction($auctionId),
        };
    }

    private function attemptPlacement(string $bidId, string $auctionId, string $bidderId, Money $amount): BidPlacementOutcome
    {
        $lockResult = $this->auctionGateway->lockAndCheckForBidding($auctionId);
        $snapshot = $lockResult->snapshot;

        if ($snapshot === null) {
            return new BidPlacementOutcome(null, BidRejectionReason::AuctionNotFound, $lockResult->proximityEvents);
        }

        if (! $snapshot->isOpenForBidding) {
            return new BidPlacementOutcome(null, BidRejectionReason::AuctionNotOpenForBidding, $lockResult->proximityEvents);
        }

        if (! $amount->currency->equals($snapshot->startingPrice->currency)) {
            return new BidPlacementOutcome(null, BidRejectionReason::CurrencyMismatch, $lockResult->proximityEvents);
        }

        if ($bidderId === $snapshot->sellerId) {
            return new BidPlacementOutcome(null, BidRejectionReason::SellerCannotBidOnOwnAuction, $lockResult->proximityEvents);
        }

        $currentHighest = $this->bids->highestAmountFor($auctionId) ?? $snapshot->startingPrice;

        if (! $amount->isGreaterThan($currentHighest)) {
            return new BidPlacementOutcome(null, BidRejectionReason::BidTooLow, $lockResult->proximityEvents);
        }

        $bid = Bid::place($bidId, $auctionId, $bidderId, $amount, $this->clock);
        $this->bids->record($bid);

        return new BidPlacementOutcome($bid, null, [...$lockResult->proximityEvents, ...$bid->releaseEvents()]);
    }
}
