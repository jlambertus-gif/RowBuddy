<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use RowBuddy\Payments\Infrastructure\Eloquent\EloquentPaymentIntentRepository;
use RowBuddy\Payments\PaymentIntent;
use RowBuddy\Payments\ValueObjects\PaymentIntentStatus;
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

    Capsule::schema()->create('payment_intents', function (Blueprint $table) {
        $table->string('id')->primary();
        $table->string('auction_id');
        $table->string('winning_bid_id');
        $table->unsignedBigInteger('seller_id');
        $table->unsignedBigInteger('buyer_id');
        $table->unsignedBigInteger('amount_minor_units');
        $table->string('amount_currency', 3);
        $table->unsignedBigInteger('fee_amount_minor_units');
        $table->string('status');
        $table->timestamp('decided_at');
        $table->timestamps();
    });
});

afterEach(function () {
    Capsule::schema()->dropIfExists('payment_intents');
});

it('records an authorized payment intent and finds it by id', function () {
    $repository = new EloquentPaymentIntentRepository;
    $decidedAt = new DateTimeImmutable('2026-10-10 10:00:00');

    $paymentIntent = PaymentIntent::authorize(
        'payment-1',
        'auction-1',
        'bid-1',
        '101',
        '102',
        usd(10000),
        usd(1000),
        usd(50000),
        new FrozenClock($decidedAt),
    );
    $repository->record($paymentIntent);

    $found = $repository->findById('payment-1');

    expect($found)->not->toBeNull()
        ->and($found->id)->toBe('payment-1')
        ->and($found->auctionId)->toBe('auction-1')
        ->and($found->winningBidId)->toBe('bid-1')
        ->and($found->sellerId)->toBe('101')
        ->and($found->buyerId)->toBe('102')
        ->and($found->amount->equals(usd(10000)))->toBeTrue()
        ->and($found->feeAmount->equals(usd(1000)))->toBeTrue()
        ->and($found->status())->toBe(PaymentIntentStatus::Authorized)
        ->and($found->decidedAt)->toEqual($decidedAt);
});

it('records a failed payment intent and finds it by id', function () {
    $repository = new EloquentPaymentIntentRepository;

    $paymentIntent = PaymentIntent::declineAuthorization(
        'payment-2',
        'auction-1',
        'bid-1',
        '101',
        '102',
        usd(10000),
        usd(1000),
        usd(50000),
        'card_declined',
        new FrozenClock,
    );
    $repository->record($paymentIntent);

    $found = $repository->findById('payment-2');

    expect($found->status())->toBe(PaymentIntentStatus::Failed);
});

it('returns null when the payment intent does not exist', function () {
    expect((new EloquentPaymentIntentRepository)->findById('missing'))->toBeNull();
});

it('finds a payment intent by auction id', function () {
    $repository = new EloquentPaymentIntentRepository;

    $repository->record(PaymentIntent::authorize(
        'payment-1',
        'auction-1',
        'bid-1',
        '101',
        '102',
        usd(10000),
        usd(1000),
        usd(50000),
        new FrozenClock,
    ));

    $found = $repository->findByAuctionId('auction-1');

    expect($found)->not->toBeNull()
        ->and($found->id)->toBe('payment-1');
});

it('returns null for findByAuctionId when no payment intent exists for that auction', function () {
    expect((new EloquentPaymentIntentRepository)->findByAuctionId('auction-missing'))->toBeNull();
});
