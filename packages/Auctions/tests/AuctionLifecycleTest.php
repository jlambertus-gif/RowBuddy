<?php

declare(strict_types=1);

use RowBuddy\Auctions\Auction;
use RowBuddy\Auctions\Events\AuctionCancelled;
use RowBuddy\Auctions\Events\AuctionClosingStarted;
use RowBuddy\Auctions\Events\AuctionExpired;
use RowBuddy\Auctions\Events\AuctionOpened;
use RowBuddy\Auctions\Events\AuctionProximityAtRisk;
use RowBuddy\Auctions\Events\AuctionProximityRestored;
use RowBuddy\Auctions\Events\AuctionWon;
use RowBuddy\Auctions\Exceptions\IllegalStateTransition;
use RowBuddy\Auctions\ValueObjects\AuctionStatus;
use RowBuddy\SharedKernel\Support\FrozenClock;

it('opens an auction as open and raises an opened event', function () {
    $openedAt = new DateTimeImmutable('2026-09-10 09:00:00');
    $auction = Auction::open(
        'auction-1',
        'queue-1',
        'seller-1',
        'session-1',
        usd(1000),
        new FrozenClock($openedAt),
    );

    expect($auction->status())->toBe(AuctionStatus::Open)
        ->and($auction->queueId)->toBe('queue-1')
        ->and($auction->sellerId)->toBe('seller-1')
        ->and($auction->presenceSessionId)->toBe('session-1')
        ->and($auction->startingPrice->equals(usd(1000)))->toBeTrue()
        ->and($auction->openedAt)->toEqual($openedAt)
        ->and($auction->winningBidId())->toBeNull()
        ->and($auction->winningAmount())->toBeNull();

    $events = $auction->releaseEvents();
    expect($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(AuctionOpened::class);
});

it('releasing events clears them so they are not dispatched twice', function () {
    $auction = Auction::open('auction-2', 'queue-1', 'seller-1', 'session-2', usd(1000), new FrozenClock);

    $auction->releaseEvents();

    expect($auction->releaseEvents())->toBe([]);
});

it('transitions from open to closing and raises a closing-started event', function () {
    $auction = Auction::open('auction-3', 'queue-1', 'seller-1', 'session-3', usd(1000), new FrozenClock);
    $auction->releaseEvents();

    $auction->startClosing(new FrozenClock);

    expect($auction->status())->toBe(AuctionStatus::Closing);
    $events = $auction->releaseEvents();
    expect($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(AuctionClosingStarted::class);
});

it('selects a winning bid from closing and raises a won event', function () {
    $auction = Auction::open('auction-4', 'queue-1', 'seller-1', 'session-4', usd(1000), new FrozenClock);
    $auction->startClosing(new FrozenClock);
    $auction->releaseEvents();

    $auction->selectWinningBid('bid-1', usd(1500), new FrozenClock);

    expect($auction->status())->toBe(AuctionStatus::Won)
        ->and($auction->winningBidId())->toBe('bid-1')
        ->and($auction->winningAmount()->equals(usd(1500)))->toBeTrue();

    $events = $auction->releaseEvents();
    expect($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(AuctionWon::class)
        ->and($events[0]->payload())->toBe([
            'auction_id' => 'auction-4',
            'winning_bid_id' => 'bid-1',
            'winning_amount_minor_units' => 1500,
            'winning_amount_currency' => 'USD',
        ]);
});

it('expires from closing without a winning bid and raises an expired event', function () {
    $auction = Auction::open('auction-5', 'queue-1', 'seller-1', 'session-5', usd(1000), new FrozenClock);
    $auction->startClosing(new FrozenClock);
    $auction->releaseEvents();

    $auction->expireWithoutWinningBid(new FrozenClock);

    expect($auction->status())->toBe(AuctionStatus::Expired)
        ->and($auction->winningBidId())->toBeNull()
        ->and($auction->winningAmount())->toBeNull();

    $events = $auction->releaseEvents();
    expect($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(AuctionExpired::class);
});

it('cannot start closing an auction that is already closing', function () {
    $auction = Auction::open('auction-6', 'queue-1', 'seller-1', 'session-6', usd(1000), new FrozenClock);
    $auction->startClosing(new FrozenClock);

    $auction->startClosing(new FrozenClock);
})->throws(IllegalStateTransition::class);

it('cannot select a winning bid on an auction that is still open', function () {
    $auction = Auction::open('auction-7', 'queue-1', 'seller-1', 'session-7', usd(1000), new FrozenClock);

    $auction->selectWinningBid('bid-1', usd(1500), new FrozenClock);
})->throws(IllegalStateTransition::class);

it('cannot select a winning bid twice, protecting the accepted-bid invariant', function () {
    $auction = Auction::open('auction-8', 'queue-1', 'seller-1', 'session-8', usd(1000), new FrozenClock);
    $auction->startClosing(new FrozenClock);
    $auction->selectWinningBid('bid-1', usd(1500), new FrozenClock);

    $auction->selectWinningBid('bid-2', usd(2000), new FrozenClock);
})->throws(IllegalStateTransition::class);

it('cannot expire an auction that is still open', function () {
    $auction = Auction::open('auction-9', 'queue-1', 'seller-1', 'session-9', usd(1000), new FrozenClock);

    $auction->expireWithoutWinningBid(new FrozenClock);
})->throws(IllegalStateTransition::class);

it('cannot expire an auction that already has a winning bid', function () {
    $auction = Auction::open('auction-10', 'queue-1', 'seller-1', 'session-10', usd(1000), new FrozenClock);
    $auction->startClosing(new FrozenClock);
    $auction->selectWinningBid('bid-1', usd(1500), new FrozenClock);

    $auction->expireWithoutWinningBid(new FrozenClock);
})->throws(IllegalStateTransition::class);

it('reconstitutes from persistence without raising any events', function () {
    $auction = Auction::fromPersistence(
        'auction-11',
        'queue-1',
        'seller-1',
        'session-11',
        usd(1000),
        new DateTimeImmutable('2026-09-10 09:00:00'),
        AuctionStatus::Won,
        'bid-1',
        usd(1500),
    );

    expect($auction->status())->toBe(AuctionStatus::Won)
        ->and($auction->winningBidId())->toBe('bid-1')
        ->and($auction->winningAmount()->equals(usd(1500)))->toBeTrue()
        ->and($auction->releaseEvents())->toBe([]);
});

it('reconstitutes proximityAtRiskSince from persistence', function () {
    $flaggedAt = new DateTimeImmutable('2026-09-10 09:10:00');
    $auction = Auction::fromPersistence(
        'auction-12',
        'queue-1',
        'seller-1',
        'session-12',
        usd(1000),
        new DateTimeImmutable('2026-09-10 09:00:00'),
        AuctionStatus::Open,
        null,
        null,
        $flaggedAt,
    );

    expect($auction->proximityAtRiskSince())->toEqual($flaggedAt);
});

it('has no proximityAtRiskSince right after opening', function () {
    $auction = Auction::open('auction-13', 'queue-1', 'seller-1', 'session-13', usd(1000), new FrozenClock);

    expect($auction->proximityAtRiskSince())->toBeNull();
});

it('flags an open auction as proximity-at-risk and raises the matching event', function () {
    $auction = Auction::open('auction-14', 'queue-1', 'seller-1', 'session-14', usd(1000), new FrozenClock);
    $auction->releaseEvents();

    $flaggedAt = new DateTimeImmutable('2026-09-10 09:15:00');
    $auction->flagProximityAtRisk(new FrozenClock($flaggedAt));

    expect($auction->status())->toBe(AuctionStatus::Open)
        ->and($auction->proximityAtRiskSince())->toEqual($flaggedAt);

    $events = $auction->releaseEvents();
    expect($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(AuctionProximityAtRisk::class);
});

it('flags a closing auction as proximity-at-risk too', function () {
    $auction = Auction::open('auction-15', 'queue-1', 'seller-1', 'session-15', usd(1000), new FrozenClock);
    $auction->startClosing(new FrozenClock);

    $auction->flagProximityAtRisk(new FrozenClock);

    expect($auction->status())->toBe(AuctionStatus::Closing)
        ->and($auction->proximityAtRiskSince())->not->toBeNull();
});

it('cannot flag proximity at risk twice', function () {
    $auction = Auction::open('auction-16', 'queue-1', 'seller-1', 'session-16', usd(1000), new FrozenClock);
    $auction->flagProximityAtRisk(new FrozenClock);

    $auction->flagProximityAtRisk(new FrozenClock);
})->throws(IllegalStateTransition::class);

it('cannot flag proximity at risk on a won auction', function () {
    $auction = Auction::open('auction-17', 'queue-1', 'seller-1', 'session-17', usd(1000), new FrozenClock);
    $auction->startClosing(new FrozenClock);
    $auction->selectWinningBid('bid-1', usd(1500), new FrozenClock);

    $auction->flagProximityAtRisk(new FrozenClock);
})->throws(IllegalStateTransition::class);

it('restores proximity and clears proximityAtRiskSince, raising the matching event', function () {
    $auction = Auction::open('auction-18', 'queue-1', 'seller-1', 'session-18', usd(1000), new FrozenClock);
    $auction->flagProximityAtRisk(new FrozenClock);
    $auction->releaseEvents();

    $auction->restoreProximity(new FrozenClock);

    expect($auction->proximityAtRiskSince())->toBeNull();
    $events = $auction->releaseEvents();
    expect($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(AuctionProximityRestored::class);
});

it('cannot restore proximity when it was never flagged at risk', function () {
    $auction = Auction::open('auction-19', 'queue-1', 'seller-1', 'session-19', usd(1000), new FrozenClock);

    $auction->restoreProximity(new FrozenClock);
})->throws(IllegalStateTransition::class);

it('cancels an open auction for proximity loss and raises a cancelled event', function () {
    $auction = Auction::open('auction-20', 'queue-1', 'seller-1', 'session-20', usd(1000), new FrozenClock);
    $auction->releaseEvents();

    $auction->cancelForProximityLoss(new FrozenClock);

    expect($auction->status())->toBe(AuctionStatus::Cancelled);
    $events = $auction->releaseEvents();
    expect($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(AuctionCancelled::class);
});

it('cancels a closing auction for proximity loss even if never flagged at risk first', function () {
    $auction = Auction::open('auction-21', 'queue-1', 'seller-1', 'session-21', usd(1000), new FrozenClock);
    $auction->startClosing(new FrozenClock);

    $auction->cancelForProximityLoss(new FrozenClock);

    expect($auction->status())->toBe(AuctionStatus::Cancelled);
});

it('cannot cancel for proximity loss an auction that has already been won', function () {
    $auction = Auction::open('auction-22', 'queue-1', 'seller-1', 'session-22', usd(1000), new FrozenClock);
    $auction->startClosing(new FrozenClock);
    $auction->selectWinningBid('bid-1', usd(1500), new FrozenClock);

    $auction->cancelForProximityLoss(new FrozenClock);
})->throws(IllegalStateTransition::class);
