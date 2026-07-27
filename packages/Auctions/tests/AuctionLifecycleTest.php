<?php

declare(strict_types=1);

use RowBuddy\Auctions\Auction;
use RowBuddy\Auctions\Events\AuctionClosingStarted;
use RowBuddy\Auctions\Events\AuctionExpired;
use RowBuddy\Auctions\Events\AuctionOpened;
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
