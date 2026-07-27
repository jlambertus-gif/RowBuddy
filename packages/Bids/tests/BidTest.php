<?php

declare(strict_types=1);

use RowBuddy\Bids\Bid;
use RowBuddy\Bids\Events\BidPlaced;
use RowBuddy\SharedKernel\Support\FrozenClock;

it('places a bid and raises a BidPlaced event', function () {
    $placedAt = new DateTimeImmutable('2026-09-23 10:00:00');
    $bid = Bid::place('bid-1', 'auction-1', 'bidder-1', usd(1100), new FrozenClock($placedAt));

    expect($bid->id)->toBe('bid-1')
        ->and($bid->auctionId)->toBe('auction-1')
        ->and($bid->bidderId)->toBe('bidder-1')
        ->and($bid->amount->equals(usd(1100)))->toBeTrue()
        ->and($bid->placedAt)->toEqual($placedAt);

    $events = $bid->releaseEvents();
    expect($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(BidPlaced::class)
        ->and($events[0]->payload())->toBe([
            'bid_id' => 'bid-1',
            'auction_id' => 'auction-1',
            'bidder_id' => 'bidder-1',
            'amount_minor_units' => 1100,
            'amount_currency' => 'USD',
        ]);
});

it('releasing events clears them so they are not dispatched twice', function () {
    $bid = Bid::place('bid-2', 'auction-1', 'bidder-1', usd(1100), new FrozenClock);

    $bid->releaseEvents();

    expect($bid->releaseEvents())->toBe([]);
});

it('reconstitutes from persistence without raising any events', function () {
    $placedAt = new DateTimeImmutable('2026-09-23 10:00:00');
    $bid = Bid::fromPersistence('bid-3', 'auction-1', 'bidder-1', usd(1100), $placedAt);

    expect($bid->amount->equals(usd(1100)))->toBeTrue()
        ->and($bid->placedAt)->toEqual($placedAt)
        ->and($bid->releaseEvents())->toBe([]);
});
