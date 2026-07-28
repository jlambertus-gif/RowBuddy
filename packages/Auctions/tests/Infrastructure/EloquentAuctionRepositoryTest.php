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
        $table->timestamp('closes_at');
        $table->string('status');
        $table->string('winning_bid_id')->nullable();
        $table->unsignedBigInteger('winning_amount_minor_units')->nullable();
        $table->string('winning_amount_currency', 3)->nullable();
        $table->timestamp('proximity_at_risk_since')->nullable();
        $table->timestamps();
    });
});

afterEach(function () {
    Capsule::schema()->dropIfExists('auctions');
});

it('round-trips an open auction through the repository', function () {
    $repository = new EloquentAuctionRepository;
    $openedAt = new DateTimeImmutable('2026-09-10 09:00:00');

    $auction = Auction::open('auction-1', 'queue-1', '101', 'session-1', usd(1000), minutesAfter($openedAt, 30), new FrozenClock($openedAt));

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

    $auction = Auction::open('auction-2', 'queue-1', '101', 'session-2', usd(1000), aFutureClosesAt(), new FrozenClock);
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

    $auction = Auction::open('auction-3', 'queue-1', '101', 'session-3', usd(1000), aFutureClosesAt(), new FrozenClock);
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

    $auction = Auction::open('auction-4', 'queue-1', '101', 'session-4', usd(1000), aFutureClosesAt(), new FrozenClock);
    $repository->save($auction);

    $reloaded = $repository->findById('auction-4');
    $reloaded->startClosing(new FrozenClock);
    $repository->save($reloaded);

    expect($repository->findById('auction-4')->status())->toBe(AuctionStatus::Closing);
});

it('rejects a second auction backed by the same presence session', function () {
    $repository = new EloquentAuctionRepository;

    $repository->save(Auction::open('auction-5', 'queue-1', '101', 'session-5', usd(1000), aFutureClosesAt(), new FrozenClock));

    $repository->save(Auction::open('auction-6', 'queue-1', '101', 'session-5', usd(1000), aFutureClosesAt(), new FrozenClock));
})->throws(PresenceSessionAlreadyConsumed::class);

it('allows two different presence sessions to each back an auction', function () {
    $repository = new EloquentAuctionRepository;

    $repository->save(Auction::open('auction-7', 'queue-1', '101', 'session-7', usd(1000), aFutureClosesAt(), new FrozenClock));
    $repository->save(Auction::open('auction-8', 'queue-1', '101', 'session-8', usd(1000), aFutureClosesAt(), new FrozenClock));

    expect($repository->findById('auction-7')->presenceSessionId)->toBe('session-7')
        ->and($repository->findById('auction-8')->presenceSessionId)->toBe('session-8');
});

it('round-trips a cancelled auction with proximityAtRiskSince set', function () {
    $repository = new EloquentAuctionRepository;
    $flaggedAt = new DateTimeImmutable('2026-09-16 10:00:00');

    $auction = Auction::open('auction-9', 'queue-1', '101', 'session-9', usd(1000), aFutureClosesAt(), new FrozenClock);
    $auction->flagProximityAtRisk(new FrozenClock($flaggedAt));
    $repository->save($auction);

    $found = $repository->findById('auction-9');
    expect($found->status())->toBe(AuctionStatus::Open)
        ->and($found->proximityAtRiskSince())->toEqual($flaggedAt);

    $found->cancelForProximityLoss(new FrozenClock);
    $repository->save($found);

    $cancelled = $repository->findById('auction-9');
    expect($cancelled->status())->toBe(AuctionStatus::Cancelled)
        ->and($cancelled->proximityAtRiskSince())->toEqual($flaggedAt);
});

it('has no proximityAtRiskSince by default when round-tripped', function () {
    $repository = new EloquentAuctionRepository;

    $repository->save(Auction::open('auction-10', 'queue-1', '101', 'session-10', usd(1000), aFutureClosesAt(), new FrozenClock));

    expect($repository->findById('auction-10')->proximityAtRiskSince())->toBeNull();
});

it('finds the same auction via findByIdForUpdate as findById', function () {
    $repository = new EloquentAuctionRepository;

    $repository->save(Auction::open('auction-11', 'queue-1', '101', 'session-11', usd(1000), aFutureClosesAt(), new FrozenClock));

    $found = $repository->findByIdForUpdate('auction-11');

    expect($found)->not->toBeNull()
        ->and($found->id)->toBe('auction-11')
        ->and($found->status())->toBe(AuctionStatus::Open);
});

it('returns null from findByIdForUpdate when the auction does not exist', function () {
    expect((new EloquentAuctionRepository)->findByIdForUpdate('missing'))->toBeNull();
});

it('round-trips closesAt', function () {
    $repository = new EloquentAuctionRepository;
    $openedAt = new DateTimeImmutable('2026-09-30 10:00:00');
    $closesAt = new DateTimeImmutable('2026-09-30 10:30:00');

    $repository->save(Auction::open('auction-12', 'queue-1', '101', 'session-12', usd(1000), $closesAt, new FrozenClock($openedAt)));

    expect($repository->findById('auction-12')->closesAt())->toEqual($closesAt);
});

it('round-trips an extended closesAt', function () {
    $repository = new EloquentAuctionRepository;
    // A literal, whole-second closesAt — like opened_at, this column is
    // not asserted to carry sub-second precision (unlike
    // presence_confidence_scores.computed_at, which genuinely needs it
    // to break same-second ties); aFutureClosesAt()'s real "now" would
    // carry microseconds this column was never designed to round-trip.
    $originalClosesAt = new DateTimeImmutable('2026-09-30 10:30:00');

    $auction = Auction::open('auction-13', 'queue-1', '101', 'session-13', usd(1000), $originalClosesAt, new FrozenClock);
    $extended = $originalClosesAt->modify('+2 minutes');
    $auction->extendClosingDeadline($extended, new FrozenClock);
    $repository->save($auction);

    expect($repository->findById('auction-13')->closesAt())->toEqual($extended);
});
