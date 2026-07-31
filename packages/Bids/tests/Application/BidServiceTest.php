<?php

declare(strict_types=1);

use RowBuddy\Bids\Application\BidService;
use RowBuddy\Bids\Events\BidPlaced;
use RowBuddy\Bids\Exceptions\AuctionNotOpenForBidding;
use RowBuddy\Bids\Exceptions\BidderAccountSuspended;
use RowBuddy\Bids\Exceptions\BidTooLow;
use RowBuddy\Bids\Exceptions\CurrencyMismatch;
use RowBuddy\Bids\Exceptions\SellerCannotBidOnOwnAuction;
use RowBuddy\Bids\Tests\Fakes\FakeAccountStandingLookup;
use RowBuddy\Bids\Tests\Fakes\FakeAuctionGateway;
use RowBuddy\Bids\Tests\Fakes\FakeDomainEvent;
use RowBuddy\Bids\Tests\Fakes\InMemoryBidRepository;
use RowBuddy\Bids\Tests\Fakes\RecordingDomainEventPublisher;
use RowBuddy\Bids\Tests\Fakes\RecordingTransactionManager;
use RowBuddy\Bids\ValueObjects\AuctionLockResult;
use RowBuddy\Bids\ValueObjects\AuctionSnapshot;
use RowBuddy\SharedKernel\Exceptions\NotFoundException;
use RowBuddy\SharedKernel\Support\FrozenClock;

/**
 * This entire suite runs without Laravel, without a database, and
 * without Eloquent — the same in-memory-fake style as AuctionServiceTest
 * and LiveProximityCheckerTest. Every scenario here also implicitly
 * proves ADR-012 §2's "no pre-commit event escapes" property: every test
 * uses RecordingTransactionManager + RecordingDomainEventPublisher
 * together, and the latter throws immediately if BidService ever
 * publishes while the former's callback is still executing.
 */
function makeBidService(
    InMemoryBidRepository $bids,
    FakeAuctionGateway $auctionGateway,
    RecordingTransactionManager $transactions,
    RecordingDomainEventPublisher $events,
    ?FakeAccountStandingLookup $accountStanding = null,
): BidService {
    return new BidService($bids, $auctionGateway, $transactions, $events, new FrozenClock(new DateTimeImmutable('2026-09-23 10:00:00')), $accountStanding ?? new FakeAccountStandingLookup);
}

function anOpenSnapshot(string $sellerId = 'seller-1'): AuctionSnapshot
{
    return new AuctionSnapshot($sellerId, usd(1000), true);
}

it('accepts a valid bid and publishes BidPlaced only after the transaction returns', function () {
    $bids = new InMemoryBidRepository;
    $auctionGateway = new FakeAuctionGateway;
    $auctionGateway->stub('auction-1', new AuctionLockResult(anOpenSnapshot(), []));
    $transactions = new RecordingTransactionManager;
    $events = new RecordingDomainEventPublisher($transactions);

    $service = makeBidService($bids, $auctionGateway, $transactions, $events);
    $bid = $service->place('bid-1', 'auction-1', 'bidder-1', usd(1100));

    expect($bid->amount->equals(usd(1100)))->toBeTrue()
        ->and($bids->findById('bid-1'))->not->toBeNull()
        ->and($events->published)->toHaveCount(1)
        ->and($events->published[0])->toBeInstanceOf(BidPlaced::class);
});

it('accepts a bid exceeding the floor by only the smallest unit, proving no minimum increment', function () {
    $bids = new InMemoryBidRepository;
    $auctionGateway = new FakeAuctionGateway;
    $auctionGateway->stub('auction-1', new AuctionLockResult(anOpenSnapshot(), []));
    $transactions = new RecordingTransactionManager;
    $events = new RecordingDomainEventPublisher($transactions);

    $service = makeBidService($bids, $auctionGateway, $transactions, $events);
    $bid = $service->place('bid-1', 'auction-1', 'bidder-1', usd(1001));

    expect($bid->amount->equals(usd(1001)))->toBeTrue();
});

it('rejects with a not-found exception when the auction does not exist, publishing nothing', function () {
    $bids = new InMemoryBidRepository;
    $auctionGateway = new FakeAuctionGateway;
    $transactions = new RecordingTransactionManager;
    $events = new RecordingDomainEventPublisher($transactions);

    $service = makeBidService($bids, $auctionGateway, $transactions, $events);

    expect(fn () => $service->place('bid-1', 'auction-missing', 'bidder-1', usd(1100)))
        ->toThrow(NotFoundException::class);

    expect($bids->recorded)->toBe([])
        ->and($events->published)->toBe([]);
});

it('rejects a bid when the auction is not open, but still publishes a proximity event the gateway surfaced', function () {
    $bids = new InMemoryBidRepository;
    $auctionGateway = new FakeAuctionGateway;
    $cancelledEvent = new FakeDomainEvent('auctions.auction_cancelled');
    $auctionGateway->stub('auction-1', new AuctionLockResult(
        new AuctionSnapshot('seller-1', usd(1000), false),
        [$cancelledEvent],
    ));
    $transactions = new RecordingTransactionManager;
    $events = new RecordingDomainEventPublisher($transactions);

    $service = makeBidService($bids, $auctionGateway, $transactions, $events);

    expect(fn () => $service->place('bid-1', 'auction-1', 'bidder-1', usd(1100)))
        ->toThrow(AuctionNotOpenForBidding::class);

    // No bid inserted, but the legitimate proximity transition the gateway
    // already committed is still published — ADR-012 §1a/§2's core requirement.
    expect($bids->recorded)->toBe([])
        ->and($events->published)->toBe([$cancelledEvent]);
});

it('publishes an at-risk proximity event even when the bid itself is rejected for an unrelated reason', function () {
    $bids = new InMemoryBidRepository;
    $auctionGateway = new FakeAuctionGateway;
    $atRiskEvent = new FakeDomainEvent('auctions.auction_proximity_at_risk');
    $auctionGateway->stub('auction-1', new AuctionLockResult(anOpenSnapshot(), [$atRiskEvent]));
    $transactions = new RecordingTransactionManager;
    $events = new RecordingDomainEventPublisher($transactions);

    $service = makeBidService($bids, $auctionGateway, $transactions, $events);

    // Bid too low -> rejected, but the auction stayed Open, so the at-risk
    // flag the gateway already committed is unrelated to this rejection.
    expect(fn () => $service->place('bid-1', 'auction-1', 'bidder-1', usd(500)))
        ->toThrow(BidTooLow::class);

    expect($events->published)->toBe([$atRiskEvent]);
});

it('rejects a bid in the wrong currency', function () {
    $bids = new InMemoryBidRepository;
    $auctionGateway = new FakeAuctionGateway;
    $auctionGateway->stub('auction-1', new AuctionLockResult(anOpenSnapshot(), []));
    $transactions = new RecordingTransactionManager;
    $events = new RecordingDomainEventPublisher($transactions);

    $service = makeBidService($bids, $auctionGateway, $transactions, $events);

    expect(fn () => $service->place('bid-1', 'auction-1', 'bidder-1', eur(1100)))
        ->toThrow(CurrencyMismatch::class);

    expect($bids->recorded)->toBe([]);
});

it('rejects the seller bidding on their own auction', function () {
    $bids = new InMemoryBidRepository;
    $auctionGateway = new FakeAuctionGateway;
    $auctionGateway->stub('auction-1', new AuctionLockResult(anOpenSnapshot('seller-1'), []));
    $transactions = new RecordingTransactionManager;
    $events = new RecordingDomainEventPublisher($transactions);

    $service = makeBidService($bids, $auctionGateway, $transactions, $events);

    expect(fn () => $service->place('bid-1', 'auction-1', 'seller-1', usd(1100)))
        ->toThrow(SellerCannotBidOnOwnAuction::class);
});

it('rejects a first bid that does not exceed the starting price', function () {
    $bids = new InMemoryBidRepository;
    $auctionGateway = new FakeAuctionGateway;
    $auctionGateway->stub('auction-1', new AuctionLockResult(anOpenSnapshot(), []));
    $transactions = new RecordingTransactionManager;
    $events = new RecordingDomainEventPublisher($transactions);

    $service = makeBidService($bids, $auctionGateway, $transactions, $events);

    expect(fn () => $service->place('bid-1', 'auction-1', 'bidder-1', usd(1000)))
        ->toThrow(BidTooLow::class);
});

it('rejects a subsequent bid that does not exceed the current highest bid', function () {
    $bids = new InMemoryBidRepository;
    $auctionGateway = new FakeAuctionGateway;
    $auctionGateway->stub('auction-1', new AuctionLockResult(anOpenSnapshot(), []));
    $transactions = new RecordingTransactionManager;
    $events = new RecordingDomainEventPublisher($transactions);

    $service = makeBidService($bids, $auctionGateway, $transactions, $events);
    $service->place('bid-1', 'auction-1', 'bidder-1', usd(1100));

    expect(fn () => $service->place('bid-2', 'auction-1', 'bidder-2', usd(1100)))
        ->toThrow(BidTooLow::class);
});

it('accepts a second bid that exceeds the first', function () {
    $bids = new InMemoryBidRepository;
    $auctionGateway = new FakeAuctionGateway;
    $auctionGateway->stub('auction-1', new AuctionLockResult(anOpenSnapshot(), []));
    $transactions = new RecordingTransactionManager;
    $events = new RecordingDomainEventPublisher($transactions);

    $service = makeBidService($bids, $auctionGateway, $transactions, $events);
    $service->place('bid-1', 'auction-1', 'bidder-1', usd(1100));
    $bid = $service->place('bid-2', 'auction-1', 'bidder-2', usd(1200));

    expect($bid->amount->equals(usd(1200)))->toBeTrue();
});

it('applies accepted-bid effects only after a bid is accepted, with the bid\'s own placedAt', function () {
    $bids = new InMemoryBidRepository;
    $auctionGateway = new FakeAuctionGateway;
    $auctionGateway->stub('auction-1', new AuctionLockResult(anOpenSnapshot(), []));
    $transactions = new RecordingTransactionManager;
    $events = new RecordingDomainEventPublisher($transactions);

    $service = makeBidService($bids, $auctionGateway, $transactions, $events);
    $bid = $service->place('bid-1', 'auction-1', 'bidder-1', usd(1100));

    expect($auctionGateway->acceptedBidEffectsCalls)->toHaveCount(1)
        ->and($auctionGateway->acceptedBidEffectsCalls[0][0])->toBe('auction-1')
        ->and($auctionGateway->acceptedBidEffectsCalls[0][1])->toEqual($bid->placedAt);
});

it('never applies accepted-bid effects when the bid is rejected for any reason', function () {
    $bids = new InMemoryBidRepository;
    $auctionGateway = new FakeAuctionGateway;
    $auctionGateway->stub('auction-1', new AuctionLockResult(anOpenSnapshot(), []));
    $transactions = new RecordingTransactionManager;
    $events = new RecordingDomainEventPublisher($transactions);

    $service = makeBidService($bids, $auctionGateway, $transactions, $events);

    // Too low — rejected before ever reaching record() or the effects call.
    try {
        $service->place('bid-1', 'auction-1', 'bidder-1', usd(500));
    } catch (BidTooLow) {
        // expected
    }

    expect($auctionGateway->acceptedBidEffectsCalls)->toBe([]);
});

it('late attempt triggers closing (surfaced by the gateway) and is rejected, but the closing event still publishes', function () {
    $bids = new InMemoryBidRepository;
    $auctionGateway = new FakeAuctionGateway;
    $closingEvent = new FakeDomainEvent('auctions.auction_expired');
    $auctionGateway->stub('auction-1', new AuctionLockResult(
        new AuctionSnapshot('seller-1', usd(1000), false),
        [$closingEvent],
    ));
    $transactions = new RecordingTransactionManager;
    $events = new RecordingDomainEventPublisher($transactions);

    $service = makeBidService($bids, $auctionGateway, $transactions, $events);

    expect(fn () => $service->place('bid-1', 'auction-1', 'bidder-1', usd(1100)))
        ->toThrow(AuctionNotOpenForBidding::class);

    expect($bids->recorded)->toBe([])
        ->and($auctionGateway->acceptedBidEffectsCalls)->toBe([])
        ->and($events->published)->toBe([$closingEvent]);
});

it('publishes events in order: lock-check events, then accepted-bid-effects events, then BidPlaced', function () {
    $bids = new InMemoryBidRepository;
    $auctionGateway = new FakeAuctionGateway;
    $atRiskEvent = new FakeDomainEvent('auctions.auction_proximity_at_risk');
    $extensionEvent = new FakeDomainEvent('auctions.auction_closing_deadline_extended');
    $auctionGateway->stub('auction-1', new AuctionLockResult(anOpenSnapshot(), [$atRiskEvent]));
    $auctionGateway->stubAcceptedBidEffects('auction-1', new AuctionLockResult(anOpenSnapshot(), [$extensionEvent]));
    $transactions = new RecordingTransactionManager;
    $events = new RecordingDomainEventPublisher($transactions);

    $service = makeBidService($bids, $auctionGateway, $transactions, $events);
    $service->place('bid-1', 'auction-1', 'bidder-1', usd(1100));

    expect($events->published)->toHaveCount(3)
        ->and($events->published[0])->toBe($atRiskEvent)
        ->and($events->published[1])->toBe($extensionEvent)
        ->and($events->published[2])->toBeInstanceOf(BidPlaced::class);
});

// --- Account standing (ADR-026 §4) ---

it('allows an active bidder to place a bid', function () {
    $bids = new InMemoryBidRepository;
    $auctionGateway = new FakeAuctionGateway;
    $auctionGateway->stub('auction-1', new AuctionLockResult(anOpenSnapshot(), []));
    $transactions = new RecordingTransactionManager;
    $events = new RecordingDomainEventPublisher($transactions);
    $accountStanding = new FakeAccountStandingLookup;

    $service = makeBidService($bids, $auctionGateway, $transactions, $events, $accountStanding);
    $bid = $service->place('bid-1', 'auction-1', 'bidder-1', usd(1100));

    expect($bid->amount->equals(usd(1100)))->toBeTrue();
});

it('rejects a bid from a suspended bidder before any domain mutation or event publication', function () {
    $bids = new InMemoryBidRepository;
    $auctionGateway = new FakeAuctionGateway;
    $auctionGateway->stub('auction-1', new AuctionLockResult(anOpenSnapshot(), []));
    $transactions = new RecordingTransactionManager;
    $events = new RecordingDomainEventPublisher($transactions);
    $accountStanding = new FakeAccountStandingLookup;
    $accountStanding->suspended['bidder-1'] = true;

    $service = makeBidService($bids, $auctionGateway, $transactions, $events, $accountStanding);

    expect(fn () => $service->place('bid-1', 'auction-1', 'bidder-1', usd(1100)))
        ->toThrow(BidderAccountSuspended::class);

    expect($bids->recorded)->toBe([])
        ->and($auctionGateway->acceptedBidEffectsCalls)->toBe([])
        ->and($events->published)->toBe([]);
});

it('still publishes a legitimate proximity event the lock surfaced even when the bidder is suspended', function () {
    $bids = new InMemoryBidRepository;
    $auctionGateway = new FakeAuctionGateway;
    $atRiskEvent = new FakeDomainEvent('auctions.auction_proximity_at_risk');
    $auctionGateway->stub('auction-1', new AuctionLockResult(anOpenSnapshot(), [$atRiskEvent]));
    $transactions = new RecordingTransactionManager;
    $events = new RecordingDomainEventPublisher($transactions);
    $accountStanding = new FakeAccountStandingLookup;
    $accountStanding->suspended['bidder-1'] = true;

    $service = makeBidService($bids, $auctionGateway, $transactions, $events, $accountStanding);

    expect(fn () => $service->place('bid-1', 'auction-1', 'bidder-1', usd(1100)))
        ->toThrow(BidderAccountSuspended::class);

    expect($bids->recorded)->toBe([])
        ->and($events->published)->toBe([$atRiskEvent]);
});
