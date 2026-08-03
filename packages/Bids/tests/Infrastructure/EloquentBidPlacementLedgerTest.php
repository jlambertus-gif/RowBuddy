<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use RowBuddy\Bids\Exceptions\BidIdempotencyKeyReused;
use RowBuddy\Bids\Exceptions\BidPlacementInProgress;
use RowBuddy\Bids\Infrastructure\Eloquent\EloquentBidPlacementLedger;
use RowBuddy\Bids\ValueObjects\BidRejectionReason;

beforeEach(function () {
    $capsule = new Capsule;
    $capsule->addConnection([
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
    ]);
    $capsule->setAsGlobal();
    $capsule->bootEloquent();

    Capsule::schema()->create('bid_placement_claims', function (Blueprint $table) {
        $table->unsignedBigInteger('bidder_id');
        $table->string('idempotency_key');
        $table->string('auction_id');
        $table->string('request_fingerprint');
        $table->string('outcome')->nullable();
        $table->string('bid_id')->nullable();
        $table->string('rejection_reason')->nullable();
        $table->timestamps();

        $table->primary(['bidder_id', 'idempotency_key']);
    });
});

afterEach(function () {
    Capsule::schema()->dropIfExists('bid_placement_claims');
});

it('returns a fresh claim result the first time a key is claimed', function () {
    $ledger = new EloquentBidPlacementLedger;

    $result = $ledger->claim('1', 'key-1', 'auction-1', 'fp-1');

    expect($result->isFresh)->toBeTrue();
});

it('reports the claim as still in progress when it has not been resolved yet', function () {
    $ledger = new EloquentBidPlacementLedger;
    $ledger->claim('1', 'key-1', 'auction-1', 'fp-1');

    expect(fn () => $ledger->claim('1', 'key-1', 'auction-1', 'fp-1'))
        ->toThrow(BidPlacementInProgress::class);
});

it('rejects a claim reusing the same key with a different fingerprint', function () {
    $ledger = new EloquentBidPlacementLedger;
    $ledger->claim('1', 'key-1', 'auction-1', 'fp-1');

    expect(fn () => $ledger->claim('1', 'key-1', 'auction-1', 'fp-2'))
        ->toThrow(BidIdempotencyKeyReused::class);
});

it('does not conflict across different bidders using the same key', function () {
    $ledger = new EloquentBidPlacementLedger;
    $ledger->claim('1', 'key-1', 'auction-1', 'fp-1');

    $result = $ledger->claim('2', 'key-1', 'auction-1', 'fp-1');

    expect($result->isFresh)->toBeTrue();
});

it('replays a resolved accepted claim', function () {
    $ledger = new EloquentBidPlacementLedger;
    $ledger->claim('1', 'key-1', 'auction-1', 'fp-1');
    $ledger->recordAccepted('1', 'key-1', 'bid-1');

    $result = $ledger->claim('1', 'key-1', 'auction-1', 'fp-1');

    expect($result->isResolved())->toBeTrue()
        ->and($result->bidId)->toBe('bid-1');
});

it('replays a resolved rejected claim with the original reason', function () {
    $ledger = new EloquentBidPlacementLedger;
    $ledger->claim('1', 'key-1', 'auction-1', 'fp-1');
    $ledger->recordRejected('1', 'key-1', BidRejectionReason::BidTooLow);

    $result = $ledger->claim('1', 'key-1', 'auction-1', 'fp-1');

    expect($result->isResolved())->toBeTrue()
        ->and($result->rejectionReason)->toBe(BidRejectionReason::BidTooLow);
});

it('allows a fresh claim again after release', function () {
    $ledger = new EloquentBidPlacementLedger;
    $ledger->claim('1', 'key-1', 'auction-1', 'fp-1');
    $ledger->release('1', 'key-1');

    $result = $ledger->claim('1', 'key-1', 'auction-1', 'fp-1');

    expect($result->isFresh)->toBeTrue();
});
