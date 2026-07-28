<?php

declare(strict_types=1);

use RowBuddy\Payments\Events\PaymentAuthorizationFailed;
use RowBuddy\Payments\Events\PaymentAuthorized;
use RowBuddy\Payments\Exceptions\TransactionValueLimitExceeded;
use RowBuddy\Payments\Exceptions\UnsupportedCurrency;
use RowBuddy\Payments\PaymentIntent;
use RowBuddy\Payments\ValueObjects\PaymentIntentStatus;
use RowBuddy\SharedKernel\Support\FrozenClock;

it('authorizes a payment and raises a PaymentAuthorized event', function () {
    $decidedAt = new DateTimeImmutable('2026-10-10 10:00:00');

    $paymentIntent = PaymentIntent::authorize(
        'payment-1',
        'auction-1',
        'bid-1',
        'seller-1',
        'buyer-1',
        usd(10000),
        usd(1000),
        usd(50000),
        new FrozenClock($decidedAt),
    );

    expect($paymentIntent->id)->toBe('payment-1')
        ->and($paymentIntent->auctionId)->toBe('auction-1')
        ->and($paymentIntent->winningBidId)->toBe('bid-1')
        ->and($paymentIntent->sellerId)->toBe('seller-1')
        ->and($paymentIntent->buyerId)->toBe('buyer-1')
        ->and($paymentIntent->amount->equals(usd(10000)))->toBeTrue()
        ->and($paymentIntent->feeAmount->equals(usd(1000)))->toBeTrue()
        ->and($paymentIntent->status())->toBe(PaymentIntentStatus::Authorized)
        ->and($paymentIntent->decidedAt)->toEqual($decidedAt);

    $events = $paymentIntent->releaseEvents();
    expect($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(PaymentAuthorized::class)
        ->and($events[0]->payload())->toBe([
            'payment_intent_id' => 'payment-1',
            'auction_id' => 'auction-1',
            'winning_bid_id' => 'bid-1',
            'amount_minor_units' => 10000,
            'amount_currency' => 'USD',
            'fee_amount_minor_units' => 1000,
        ]);
});

it('rejects authorization when the amount currency does not match the transaction value limit', function () {
    expect(fn () => PaymentIntent::authorize(
        'payment-1',
        'auction-1',
        'bid-1',
        'seller-1',
        'buyer-1',
        eur(10000),
        eur(1000),
        usd(50000),
        new FrozenClock,
    ))->toThrow(UnsupportedCurrency::class);
});

it('rejects authorization when the amount exceeds the transaction value limit', function () {
    expect(fn () => PaymentIntent::authorize(
        'payment-1',
        'auction-1',
        'bid-1',
        'seller-1',
        'buyer-1',
        usd(50001),
        usd(5000),
        usd(50000),
        new FrozenClock,
    ))->toThrow(TransactionValueLimitExceeded::class);
});

it('allows authorization when the amount exactly equals the transaction value limit', function () {
    $paymentIntent = PaymentIntent::authorize(
        'payment-1',
        'auction-1',
        'bid-1',
        'seller-1',
        'buyer-1',
        usd(50000),
        usd(5000),
        usd(50000),
        new FrozenClock,
    );

    expect($paymentIntent->status())->toBe(PaymentIntentStatus::Authorized);
});

it('records a declined authorization and raises a PaymentAuthorizationFailed event', function () {
    $decidedAt = new DateTimeImmutable('2026-10-10 10:00:00');

    $paymentIntent = PaymentIntent::declineAuthorization(
        'payment-2',
        'auction-1',
        'bid-1',
        'seller-1',
        'buyer-1',
        usd(10000),
        usd(1000),
        usd(50000),
        'card_declined',
        new FrozenClock($decidedAt),
    );

    expect($paymentIntent->status())->toBe(PaymentIntentStatus::Failed)
        ->and($paymentIntent->decidedAt)->toEqual($decidedAt);

    $events = $paymentIntent->releaseEvents();
    expect($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(PaymentAuthorizationFailed::class)
        ->and($events[0]->payload())->toBe([
            'payment_intent_id' => 'payment-2',
            'auction_id' => 'auction-1',
            'winning_bid_id' => 'bid-1',
            'amount_minor_units' => 10000,
            'amount_currency' => 'USD',
            'reason' => 'card_declined',
        ]);
});

it('rejects a declined-authorization record when the amount currency does not match the limit', function () {
    expect(fn () => PaymentIntent::declineAuthorization(
        'payment-2',
        'auction-1',
        'bid-1',
        'seller-1',
        'buyer-1',
        eur(10000),
        eur(1000),
        usd(50000),
        'card_declined',
        new FrozenClock,
    ))->toThrow(UnsupportedCurrency::class);
});

it('rejects a declined-authorization record when the amount exceeds the limit', function () {
    expect(fn () => PaymentIntent::declineAuthorization(
        'payment-2',
        'auction-1',
        'bid-1',
        'seller-1',
        'buyer-1',
        usd(50001),
        usd(5000),
        usd(50000),
        'card_declined',
        new FrozenClock,
    ))->toThrow(TransactionValueLimitExceeded::class);
});

it('releasing events clears them so they are not dispatched twice', function () {
    $paymentIntent = PaymentIntent::authorize(
        'payment-1',
        'auction-1',
        'bid-1',
        'seller-1',
        'buyer-1',
        usd(10000),
        usd(1000),
        usd(50000),
        new FrozenClock,
    );

    $paymentIntent->releaseEvents();

    expect($paymentIntent->releaseEvents())->toBe([]);
});

it('reconstitutes from persistence without raising any events', function () {
    $decidedAt = new DateTimeImmutable('2026-10-10 10:00:00');

    $paymentIntent = PaymentIntent::fromPersistence(
        'payment-1',
        'auction-1',
        'bid-1',
        'seller-1',
        'buyer-1',
        usd(10000),
        usd(1000),
        PaymentIntentStatus::Authorized,
        $decidedAt,
    );

    expect($paymentIntent->amount->equals(usd(10000)))->toBeTrue()
        ->and($paymentIntent->status())->toBe(PaymentIntentStatus::Authorized)
        ->and($paymentIntent->decidedAt)->toEqual($decidedAt)
        ->and($paymentIntent->releaseEvents())->toBe([]);
});
