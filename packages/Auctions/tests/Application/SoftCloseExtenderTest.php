<?php

declare(strict_types=1);

use RowBuddy\Auctions\Application\FixedAntiSnipingPolicy;
use RowBuddy\Auctions\Application\SoftCloseExtender;
use RowBuddy\Auctions\Auction;
use RowBuddy\Auctions\Events\AuctionClosingDeadlineExtended;
use RowBuddy\Auctions\Tests\Fakes\InMemoryAuctionRepository;
use RowBuddy\Auctions\Tests\Fakes\RecordingDomainEventPublisher;
use RowBuddy\SharedKernel\Support\FrozenClock;

/**
 * ADR-013 §2: only ever reachable after a bid has already been accepted
 * and recorded — these tests exercise the extender directly with an
 * already-accepted timestamp, the same way BidService would call it
 * through the gateway.
 */
function makeExtender(
    InMemoryAuctionRepository $auctions,
    RecordingDomainEventPublisher $events,
    int $windowSeconds = 120,
    int $extensionSeconds = 120,
): SoftCloseExtender {
    return new SoftCloseExtender($auctions, new FixedAntiSnipingPolicy($windowSeconds, $extensionSeconds), $events, new FrozenClock);
}

it('extends the deadline when the accepted bid lands inside the soft-close window', function () {
    $auctions = new InMemoryAuctionRepository;
    $events = new RecordingDomainEventPublisher;
    $closesAt = new DateTimeImmutable('2026-09-30 10:30:00');

    $auction = Auction::open('auction-1', 'queue-1', 'seller-1', 'session-1', usd(1000), $closesAt, new FrozenClock);
    $auction->releaseEvents();

    $acceptedAt = $closesAt->modify('-30 seconds');
    $result = makeExtender($auctions, $events)->applyIfWithinWindow($auction, $acceptedAt);

    expect($result->closesAt())->toEqual($closesAt->modify('+2 minutes'))
        ->and($events->published)->toHaveCount(1)
        ->and($events->published[0])->toBeInstanceOf(AuctionClosingDeadlineExtended::class);
});

it('does nothing when the accepted bid lands well before the soft-close window', function () {
    $auctions = new InMemoryAuctionRepository;
    $events = new RecordingDomainEventPublisher;
    $closesAt = new DateTimeImmutable('2026-09-30 10:30:00');

    $auction = Auction::open('auction-2', 'queue-1', 'seller-1', 'session-2', usd(1000), $closesAt, new FrozenClock);
    $auction->releaseEvents();

    $acceptedAt = $closesAt->modify('-10 minutes');
    $result = makeExtender($auctions, $events)->applyIfWithinWindow($auction, $acceptedAt);

    expect($result->closesAt())->toEqual($closesAt)
        ->and($events->published)->toBe([]);
});

it('does nothing when the accepted bid lands at or after the current deadline', function () {
    $auctions = new InMemoryAuctionRepository;
    $events = new RecordingDomainEventPublisher;
    $closesAt = new DateTimeImmutable('2026-09-30 10:30:00');

    $auction = Auction::open('auction-3', 'queue-1', 'seller-1', 'session-3', usd(1000), $closesAt, new FrozenClock);
    $auction->releaseEvents();

    $result = makeExtender($auctions, $events)->applyIfWithinWindow($auction, $closesAt);

    expect($result->closesAt())->toEqual($closesAt)
        ->and($events->published)->toBe([]);
});

it('calculates repeated extensions from the current, already-extended closesAt, not the original', function () {
    $auctions = new InMemoryAuctionRepository;
    $events = new RecordingDomainEventPublisher;
    $originalClosesAt = new DateTimeImmutable('2026-09-30 10:30:00');

    $auction = Auction::open('auction-4', 'queue-1', 'seller-1', 'session-4', usd(1000), $originalClosesAt, new FrozenClock);
    $auction->releaseEvents();

    $extender = makeExtender($auctions, $events);
    $extender->applyIfWithinWindow($auction, $originalClosesAt->modify('-30 seconds'));
    $firstExtension = $auction->closesAt();
    expect($firstExtension)->toEqual($originalClosesAt->modify('+2 minutes'));

    // A second accepted bid, itself inside the window relative to the
    // *extended* deadline — must push from firstExtension, not from
    // originalClosesAt again.
    $extender->applyIfWithinWindow($auction, $firstExtension->modify('-30 seconds'));

    expect($auction->closesAt())->toEqual($firstExtension->modify('+2 minutes'))
        ->and($events->published)->toHaveCount(2);
});

it('does nothing for an auction that is not open', function () {
    $auctions = new InMemoryAuctionRepository;
    $events = new RecordingDomainEventPublisher;
    $closesAt = new DateTimeImmutable('2026-09-30 10:30:00');

    $auction = Auction::open('auction-5', 'queue-1', 'seller-1', 'session-5', usd(1000), $closesAt, new FrozenClock);
    $auction->startClosing(new FrozenClock);
    $auction->expireWithoutWinningBid(new FrozenClock);
    $auction->releaseEvents();

    $result = makeExtender($auctions, $events)->applyIfWithinWindow($auction, $closesAt->modify('-30 seconds'));

    expect($result->closesAt())->toEqual($closesAt)
        ->and($events->published)->toBe([]);
});
