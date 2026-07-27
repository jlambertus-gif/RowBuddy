<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use RowBuddy\Bids\Bid;
use RowBuddy\Bids\Infrastructure\Eloquent\EloquentBidRepository;
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

    Capsule::schema()->create('bids', function (Blueprint $table) {
        $table->string('id')->primary();
        $table->string('auction_id');
        $table->unsignedBigInteger('bidder_id');
        $table->unsignedBigInteger('amount_minor_units');
        $table->string('amount_currency', 3);
        $table->timestamp('placed_at');
        $table->timestamps();
    });
});

afterEach(function () {
    Capsule::schema()->dropIfExists('bids');
});

it('records and finds a bid by id', function () {
    $repository = new EloquentBidRepository;
    $placedAt = new DateTimeImmutable('2026-09-23 10:00:00');

    $bid = Bid::place('bid-1', 'auction-1', '101', usd(1100), new FrozenClock($placedAt));
    $repository->record($bid);

    $found = $repository->findById('bid-1');

    expect($found)->not->toBeNull()
        ->and($found->id)->toBe('bid-1')
        ->and($found->auctionId)->toBe('auction-1')
        ->and($found->bidderId)->toBe('101')
        ->and($found->amount->equals(usd(1100)))->toBeTrue()
        ->and($found->placedAt)->toEqual($placedAt);
});

it('returns null when the bid does not exist', function () {
    expect((new EloquentBidRepository)->findById('missing'))->toBeNull();
});

it('returns null for highestAmountFor when the auction has no bids', function () {
    expect((new EloquentBidRepository)->highestAmountFor('auction-missing'))->toBeNull();
});

it('returns the actual highest bid, not the most recently inserted one', function () {
    $repository = new EloquentBidRepository;

    $repository->record(Bid::place('bid-1', 'auction-1', '101', usd(1500), new FrozenClock));
    $repository->record(Bid::place('bid-2', 'auction-1', '102', usd(1100), new FrozenClock));

    expect($repository->highestAmountFor('auction-1')->equals(usd(1500)))->toBeTrue();
});

it('scopes highestAmountFor to the given auction only', function () {
    $repository = new EloquentBidRepository;

    $repository->record(Bid::place('bid-1', 'auction-1', '101', usd(1100), new FrozenClock));
    $repository->record(Bid::place('bid-2', 'auction-2', '102', usd(5000), new FrozenClock));

    expect($repository->highestAmountFor('auction-1')->equals(usd(1100)))->toBeTrue();
});
