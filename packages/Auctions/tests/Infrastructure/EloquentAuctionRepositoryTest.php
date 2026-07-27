<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use RowBuddy\Auctions\Auction;
use RowBuddy\Auctions\Exceptions\PresenceSessionAlreadyConsumed;
use RowBuddy\Auctions\Infrastructure\Eloquent\EloquentAuctionRepository;
use RowBuddy\Auctions\ValueObjects\AuctionStatus;
use RowBuddy\SharedKernel\Support\FrozenClock;

beforeEach(function () {
    $capsule = new Capsule;
    $capsule->addConnection([
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
    ]);
    $capsule->setAsGlobal();
    $capsule->bootEloquent();

    Capsule::schema()->create('auctions', function (Blueprint $table) {
        $table->string('id')->primary();
        $table->string('queue_id');
        $table->unsignedBigInteger('seller_id');
        $table->string('presence_session_id')->unique();
        $table->unsignedBigInteger('starting_price_minor_units');
        $table->string('starting_price_currency', 3);
        $table->timestamp('opened_at');
        $table->string('status');
        $table->string('winning_bid_id')->nullable();
        $table->unsignedBigInteger('winning_amount_minor_units')->nullable();
        $table->string('winning_amount_currency', 3)->nullable();
        $table->timestamps();
    });
});

afterEach(function () {
    Capsule::schema()->dropIfExists('auctions');
});

it('round-trips an open auction through the repository', function () {
    $repository = new EloquentAuctionRepository;
    $openedAt = new DateTimeImmutable('2026-09-10 09:00:00');

    $auction = Auction::open('auction-1', 'queue-1', '101', 'session-1', usd(1000), new FrozenClock($openedAt));

    $repository->save($auction);
    $found = $repository->findById('auction-1');

    expect($found)->not->toBeNull()
        ->and($found->id)->toBe('auction-1')
        ->and($found->queueId)->toBe('queue-1')
        ->and($found->sellerId)->toBe('101')
        ->and($found->presenceSessionId)->toBe('session-1')
        ->and($found->startingPrice->equals(usd(1000)))->toBeTrue()
        ->and($found->openedAt)->toEqual($openedAt)
        ->and($found->status())->toBe(AuctionStatus::Open)
        ->and($found->winningBidId())->toBeNull()
        ->and($found->winningAmount())->toBeNull()
        ->and($found->releaseEvents())->toBe([]);
});

it('round-trips a won auction with its winning bid', function () {
    $repository = new EloquentAuctionRepository;

    $auction = Auction::open('auction-2', 'queue-1', '101', 'session-2', usd(1000), new FrozenClock);
    $auction->startClosing(new FrozenClock);
    $auction->selectWinningBid('bid-1', usd(1500), new FrozenClock);
    $repository->save($auction);

    $found = $repository->findById('auction-2');

    expect($found->status())->toBe(AuctionStatus::Won)
        ->and($found->winningBidId())->toBe('bid-1')
        ->and($found->winningAmount()->equals(usd(1500)))->toBeTrue();
});

it('round-trips an expired auction with no winning bid', function () {
    $repository = new EloquentAuctionRepository;

    $auction = Auction::open('auction-3', 'queue-1', '101', 'session-3', usd(1000), new FrozenClock);
    $auction->startClosing(new FrozenClock);
    $auction->expireWithoutWinningBid(new FrozenClock);
    $repository->save($auction);

    $found = $repository->findById('auction-3');

    expect($found->status())->toBe(AuctionStatus::Expired)
        ->and($found->winningBidId())->toBeNull()
        ->and($found->winningAmount())->toBeNull();
});

it('returns null when the auction does not exist', function () {
    expect((new EloquentAuctionRepository)->findById('missing'))->toBeNull();
});

it('persists a status transition made after reloading from the repository', function () {
    $repository = new EloquentAuctionRepository;

    $auction = Auction::open('auction-4', 'queue-1', '101', 'session-4', usd(1000), new FrozenClock);
    $repository->save($auction);

    $reloaded = $repository->findById('auction-4');
    $reloaded->startClosing(new FrozenClock);
    $repository->save($reloaded);

    expect($repository->findById('auction-4')->status())->toBe(AuctionStatus::Closing);
});

it('rejects a second auction backed by the same presence session', function () {
    $repository = new EloquentAuctionRepository;

    $repository->save(Auction::open('auction-5', 'queue-1', '101', 'session-5', usd(1000), new FrozenClock));

    $repository->save(Auction::open('auction-6', 'queue-1', '101', 'session-5', usd(1000), new FrozenClock));
})->throws(PresenceSessionAlreadyConsumed::class);

it('allows two different presence sessions to each back an auction', function () {
    $repository = new EloquentAuctionRepository;

    $repository->save(Auction::open('auction-7', 'queue-1', '101', 'session-7', usd(1000), new FrozenClock));
    $repository->save(Auction::open('auction-8', 'queue-1', '101', 'session-8', usd(1000), new FrozenClock));

    expect($repository->findById('auction-7')->presenceSessionId)->toBe('session-7')
        ->and($repository->findById('auction-8')->presenceSessionId)->toBe('session-8');
});
