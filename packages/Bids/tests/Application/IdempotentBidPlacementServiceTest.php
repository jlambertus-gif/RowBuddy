<?php

declare(strict_types=1);

use RowBuddy\Bids\Application\BidService;
use RowBuddy\Bids\Application\IdempotentBidPlacementService;
use RowBuddy\Bids\Contracts\AuctionGateway;
use RowBuddy\Bids\Exceptions\BidIdempotencyKeyReused;
use RowBuddy\Bids\Exceptions\BidPlacementInProgress;
use RowBuddy\Bids\Exceptions\BidTooLow;
use RowBuddy\Bids\Exceptions\SellerCannotBidOnOwnAuction;
use RowBuddy\Bids\Tests\Fakes\FakeAccountStandingLookup;
use RowBuddy\Bids\Tests\Fakes\FakeAuctionGateway;
use RowBuddy\Bids\Tests\Fakes\FakeBidPlacementLedger;
use RowBuddy\Bids\Tests\Fakes\InMemoryBidRepository;
use RowBuddy\Bids\Tests\Fakes\RecordingDomainEventPublisher;
use RowBuddy\Bids\Tests\Fakes\RecordingTransactionManager;
use RowBuddy\Bids\ValueObjects\AuctionLockResult;
use RowBuddy\Bids\ValueObjects\AuctionSnapshot;
use RowBuddy\SharedKernel\Exceptions\NotFoundException;
use RowBuddy\SharedKernel\Support\FrozenClock;

/**
 * Proves the HTTP-retry idempotency contract (Phase 9, ADR-027
 * Architecture Refinements §2) at the application layer, without booting
 * Eloquent — the same in-memory-fake style as BidServiceTest.
 */
function makeIdempotentBidPlacementService(
    InMemoryBidRepository $bids,
    AuctionGateway $auctionGateway,
    FakeBidPlacementLedger $ledger,
): IdempotentBidPlacementService {
    $transactions = new RecordingTransactionManager;
    $events = new RecordingDomainEventPublisher($transactions);
    $bidService = new BidService($bids, $auctionGateway, $transactions, $events, new FrozenClock(new DateTimeImmutable('2026-09-23 10:00:00')), new FakeAccountStandingLookup);

    return new IdempotentBidPlacementService($bidService, $ledger, $bids);
}

it('places a fresh bid and records the accepted outcome in the ledger', function () {
    $bids = new InMemoryBidRepository;
    $auctionGateway = new FakeAuctionGateway;
    $auctionGateway->stub('auction-1', new AuctionLockResult(new AuctionSnapshot('seller-1', usd(1000), true), []));
    $ledger = new FakeBidPlacementLedger;

    $service = makeIdempotentBidPlacementService($bids, $auctionGateway, $ledger);
    $bid = $service->place('key-1', 'bid-1', 'auction-1', 'bidder-1', usd(1100));

    expect($bid->amount->equals(usd(1100)))->toBeTrue()
        ->and($ledger->claims['bidder-1|key-1']['outcome'])->toBe('accepted')
        ->and($ledger->claims['bidder-1|key-1']['bidId'])->toBe('bid-1');
});

it('replays the exact same accepted bid on a retry with the same idempotency key, without calling BidService again', function () {
    $bids = new InMemoryBidRepository;
    $auctionGateway = new FakeAuctionGateway;
    $auctionGateway->stub('auction-1', new AuctionLockResult(new AuctionSnapshot('seller-1', usd(1000), true), []));
    $ledger = new FakeBidPlacementLedger;

    $service = makeIdempotentBidPlacementService($bids, $auctionGateway, $ledger);
    $first = $service->place('key-1', 'bid-1', 'auction-1', 'bidder-1', usd(1100));
    $second = $service->place('key-1', 'bid-2', 'auction-1', 'bidder-1', usd(1100));

    expect($second->id)->toBe($first->id)
        ->and($bids->recorded)->toHaveCount(1);
});

it('replays the exact same rejection on a retry of a bid that was too low', function () {
    $bids = new InMemoryBidRepository;
    $auctionGateway = new FakeAuctionGateway;
    $auctionGateway->stub('auction-1', new AuctionLockResult(new AuctionSnapshot('seller-1', usd(1000), true), []));
    $ledger = new FakeBidPlacementLedger;

    $service = makeIdempotentBidPlacementService($bids, $auctionGateway, $ledger);

    expect(fn () => $service->place('key-1', 'bid-1', 'auction-1', 'bidder-1', usd(500)))
        ->toThrow(BidTooLow::class);

    // Retry: BidService is never called again (no bid-2 recorded), yet the
    // caller still observes the identical rejection.
    expect(fn () => $service->place('key-1', 'bid-2', 'auction-1', 'bidder-1', usd(500)))
        ->toThrow(BidTooLow::class);

    expect($bids->recorded)->toBe([]);
});

it('replays the exact same rejection for a seller bidding on their own auction', function () {
    $bids = new InMemoryBidRepository;
    $auctionGateway = new FakeAuctionGateway;
    $auctionGateway->stub('auction-1', new AuctionLockResult(new AuctionSnapshot('seller-1', usd(1000), true), []));
    $ledger = new FakeBidPlacementLedger;

    $service = makeIdempotentBidPlacementService($bids, $auctionGateway, $ledger);

    expect(fn () => $service->place('key-1', 'bid-1', 'auction-1', 'seller-1', usd(1100)))
        ->toThrow(SellerCannotBidOnOwnAuction::class);

    expect(fn () => $service->place('key-1', 'bid-2', 'auction-1', 'seller-1', usd(1100)))
        ->toThrow(SellerCannotBidOnOwnAuction::class);
});

it('replays a not-found rejection when the auction never existed', function () {
    $bids = new InMemoryBidRepository;
    $auctionGateway = new FakeAuctionGateway;
    $ledger = new FakeBidPlacementLedger;

    $service = makeIdempotentBidPlacementService($bids, $auctionGateway, $ledger);

    expect(fn () => $service->place('key-1', 'bid-1', 'auction-missing', 'bidder-1', usd(1100)))
        ->toThrow(NotFoundException::class);

    expect(fn () => $service->place('key-1', 'bid-2', 'auction-missing', 'bidder-1', usd(1100)))
        ->toThrow(NotFoundException::class);
});

it('rejects reusing the same key for a different auction as a client error, without calling BidService again', function () {
    $bids = new InMemoryBidRepository;
    $auctionGateway = new FakeAuctionGateway;
    $auctionGateway->stub('auction-1', new AuctionLockResult(new AuctionSnapshot('seller-1', usd(1000), true), []));
    $auctionGateway->stub('auction-2', new AuctionLockResult(new AuctionSnapshot('seller-2', usd(1000), true), []));
    $ledger = new FakeBidPlacementLedger;

    $service = makeIdempotentBidPlacementService($bids, $auctionGateway, $ledger);
    $service->place('key-1', 'bid-1', 'auction-1', 'bidder-1', usd(1100));

    expect(fn () => $service->place('key-1', 'bid-2', 'auction-2', 'bidder-1', usd(1100)))
        ->toThrow(BidIdempotencyKeyReused::class);

    expect($bids->recorded)->toHaveCount(1);
});

it('rejects reusing the same key for a different amount as a client error', function () {
    $bids = new InMemoryBidRepository;
    $auctionGateway = new FakeAuctionGateway;
    $auctionGateway->stub('auction-1', new AuctionLockResult(new AuctionSnapshot('seller-1', usd(1000), true), []));
    $ledger = new FakeBidPlacementLedger;

    $service = makeIdempotentBidPlacementService($bids, $auctionGateway, $ledger);
    $service->place('key-1', 'bid-1', 'auction-1', 'bidder-1', usd(1100));

    expect(fn () => $service->place('key-1', 'bid-2', 'auction-1', 'bidder-1', usd(1200)))
        ->toThrow(BidIdempotencyKeyReused::class);
});

it('reports a still-in-progress conflict when a concurrent claim has not resolved yet', function () {
    $bids = new InMemoryBidRepository;
    $auctionGateway = new FakeAuctionGateway;
    $ledger = new FakeBidPlacementLedger;
    // Simulate a concurrent, still-unresolved claim by pre-seeding the ledger.
    $ledger->claims['bidder-1|key-1'] = [
        'auctionId' => 'auction-1',
        'fingerprint' => hash('sha256', 'auction-1|1100|USD'),
        'outcome' => null,
        'bidId' => null,
        'reason' => null,
    ];

    $service = makeIdempotentBidPlacementService($bids, $auctionGateway, $ledger);

    expect(fn () => $service->place('key-1', 'bid-1', 'auction-1', 'bidder-1', usd(1100)))
        ->toThrow(BidPlacementInProgress::class);

    expect($bids->recorded)->toBe([]);
});

it('releases the claim when BidService fails unexpectedly, allowing a genuine retry to proceed', function () {
    $bids = new InMemoryBidRepository;
    $ledger = new FakeBidPlacementLedger;
    $failingGateway = new class implements AuctionGateway
    {
        public function lockAndCheckForBidding(string $auctionId): AuctionLockResult
        {
            throw new RuntimeException('Database connection lost.');
        }

        public function applyAcceptedBidEffects(string $auctionId, DateTimeImmutable $acceptedAt): AuctionLockResult
        {
            throw new RuntimeException('Unreachable in this test.');
        }
    };

    $service = makeIdempotentBidPlacementService($bids, $failingGateway, $ledger);

    expect(fn () => $service->place('key-1', 'bid-1', 'auction-1', 'bidder-1', usd(1100)))
        ->toThrow(RuntimeException::class);

    // The claim was released, not recorded as resolved — a genuine retry
    // with the same key may attempt placement again.
    expect($ledger->claims)->toBe([]);

    $recoveredGateway = new FakeAuctionGateway;
    $recoveredGateway->stub('auction-1', new AuctionLockResult(new AuctionSnapshot('seller-1', usd(1000), true), []));
    $retryService = makeIdempotentBidPlacementService($bids, $recoveredGateway, $ledger);
    $bid = $retryService->place('key-1', 'bid-2', 'auction-1', 'bidder-1', usd(1100));

    expect($bid->id)->toBe('bid-2');
});
