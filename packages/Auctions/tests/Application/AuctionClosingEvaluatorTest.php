<?php

declare(strict_types=1);

use RowBuddy\Auctions\Application\AuctionClosingEvaluator;
use RowBuddy\Auctions\Auction;
use RowBuddy\Auctions\Contracts\WinningBidLookup;
use RowBuddy\Auctions\Events\AuctionClosingStarted;
use RowBuddy\Auctions\Events\AuctionExpired;
use RowBuddy\Auctions\Events\AuctionWon;
use RowBuddy\Auctions\Tests\Fakes\InMemoryAuctionRepository;
use RowBuddy\Auctions\Tests\Fakes\InMemoryWinningBidLookup;
use RowBuddy\Auctions\Tests\Fakes\RecordingDomainEventPublisher;
use RowBuddy\Auctions\ValueObjects\AuctionStatus;
use RowBuddy\Auctions\ValueObjects\WinningBidCandidate;
use RowBuddy\SharedKernel\Support\FrozenClock;

/**
 * Mirrors LiveProximityCheckerTest's style exactly: no Laravel, no
 * database, plain in-memory fakes — the same pattern ADR-013 §4 expects
 * this evaluator to be exercised with.
 */
function makeClosingEvaluator(
    InMemoryAuctionRepository $auctions,
    InMemoryWinningBidLookup $winningBids,
    RecordingDomainEventPublisher $events,
    DateTimeImmutable $now,
): AuctionClosingEvaluator {
    return new AuctionClosingEvaluator($auctions, $winningBids, $events, new FrozenClock($now));
}

it('does nothing when the deadline has not yet been reached', function () {
    $auctions = new InMemoryAuctionRepository;
    $winningBids = new InMemoryWinningBidLookup;
    $events = new RecordingDomainEventPublisher;
    $openedAt = new DateTimeImmutable('2026-09-30 10:00:00');
    $closesAt = new DateTimeImmutable('2026-09-30 10:30:00');

    $auction = Auction::open('auction-1', 'queue-1', 'seller-1', 'session-1', usd(1000), $closesAt, new FrozenClock($openedAt));
    $auction->releaseEvents();

    $result = makeClosingEvaluator($auctions, $winningBids, $events, $closesAt->modify('-1 second'))->evaluate($auction);

    expect($result->status())->toBe(AuctionStatus::Open)
        ->and($events->published)->toBe([]);
});

it('closes and selects the winning bid exactly at the deadline', function () {
    $auctions = new InMemoryAuctionRepository;
    $winningBids = new InMemoryWinningBidLookup;
    $events = new RecordingDomainEventPublisher;
    $openedAt = new DateTimeImmutable('2026-09-30 10:00:00');
    $closesAt = new DateTimeImmutable('2026-09-30 10:30:00');

    $auction = Auction::open('auction-2', 'queue-1', 'seller-1', 'session-2', usd(1000), $closesAt, new FrozenClock($openedAt));
    $auction->releaseEvents();
    $winningBids->stub('auction-2', new WinningBidCandidate('bid-1', 'bidder-1', usd(1500), $closesAt->modify('-1 minute')));

    $result = makeClosingEvaluator($auctions, $winningBids, $events, $closesAt)->evaluate($auction);

    expect($result->status())->toBe(AuctionStatus::Won)
        ->and($result->winningBidId())->toBe('bid-1')
        ->and($result->winningAmount()->equals(usd(1500)))->toBeTrue()
        ->and($events->published)->toHaveCount(2)
        ->and($events->published[0])->toBeInstanceOf(AuctionClosingStarted::class)
        ->and($events->published[1])->toBeInstanceOf(AuctionWon::class);
});

it('closes and expires when no bids exist', function () {
    $auctions = new InMemoryAuctionRepository;
    $winningBids = new InMemoryWinningBidLookup;
    $events = new RecordingDomainEventPublisher;
    $openedAt = new DateTimeImmutable('2026-09-30 10:00:00');
    $closesAt = new DateTimeImmutable('2026-09-30 10:30:00');

    $auction = Auction::open('auction-3', 'queue-1', 'seller-1', 'session-3', usd(1000), $closesAt, new FrozenClock($openedAt));
    $auction->releaseEvents();

    $result = makeClosingEvaluator($auctions, $winningBids, $events, $closesAt->modify('+1 second'))->evaluate($auction);

    expect($result->status())->toBe(AuctionStatus::Expired)
        ->and($events->published)->toHaveCount(2)
        ->and($events->published[0])->toBeInstanceOf(AuctionClosingStarted::class)
        ->and($events->published[1])->toBeInstanceOf(AuctionExpired::class);
});

it('persists the closing transition in the underlying repository', function () {
    $auctions = new InMemoryAuctionRepository;
    $winningBids = new InMemoryWinningBidLookup;
    $events = new RecordingDomainEventPublisher;
    $closesAt = new DateTimeImmutable('2026-09-30 10:30:00');

    $auction = Auction::open('auction-4', 'queue-1', 'seller-1', 'session-4', usd(1000), $closesAt, new FrozenClock);
    $auction->releaseEvents();

    makeClosingEvaluator($auctions, $winningBids, $events, $closesAt)->evaluate($auction);

    expect($auctions->findById('auction-4')->status())->toBe(AuctionStatus::Expired);
});

it('does not query the winning-bid lookup at all for an auction already in a terminal state', function () {
    $auctions = new InMemoryAuctionRepository;
    $events = new RecordingDomainEventPublisher;
    $closesAt = new DateTimeImmutable('2026-09-30 10:30:00');

    $throwingLookup = new class implements WinningBidLookup
    {
        public function highestBidFor(string $auctionId): ?WinningBidCandidate
        {
            throw new RuntimeException('should never be called for a terminal auction');
        }
    };

    foreach ([AuctionStatus::Won, AuctionStatus::Expired, AuctionStatus::Cancelled] as $terminalStatus) {
        $auction = Auction::fromPersistence(
            'auction-terminal-'.$terminalStatus->value,
            'queue-1',
            'seller-1',
            'session-terminal',
            usd(1000),
            $closesAt->modify('-30 minutes'),
            $closesAt,
            $terminalStatus,
            null,
            null,
        );

        $evaluator = new AuctionClosingEvaluator(new InMemoryAuctionRepository, $throwingLookup, $events, new FrozenClock($closesAt));
        $result = $evaluator->evaluate($auction);

        expect($result->status())->toBe($terminalStatus);
    }

    expect($events->published)->toBe([]);
});
