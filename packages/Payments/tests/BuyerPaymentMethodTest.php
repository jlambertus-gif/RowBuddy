<?php

declare(strict_types=1);

use RowBuddy\Payments\BuyerPaymentMethod;
use RowBuddy\Payments\Events\BuyerPaymentMethodSaved;
use RowBuddy\SharedKernel\Support\FrozenClock;

it('saves a buyer payment method and raises a BuyerPaymentMethodSaved event', function () {
    $savedAt = new DateTimeImmutable('2026-11-22 10:00:00');

    $method = BuyerPaymentMethod::save('501', 'cus_123', 'pm_123', new FrozenClock($savedAt));

    expect($method->buyerId)->toBe('501')
        ->and($method->stripeCustomerId)->toBe('cus_123')
        ->and($method->stripePaymentMethodId)->toBe('pm_123')
        ->and($method->savedAt)->toEqual($savedAt);

    $events = $method->releaseEvents();
    expect($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(BuyerPaymentMethodSaved::class)
        ->and($events[0]->payload())->toBe([
            'buyer_id' => '501',
            'stripe_customer_id' => 'cus_123',
            'stripe_payment_method_id' => 'pm_123',
        ]);
});

it('releasing events clears them so they are not dispatched twice', function () {
    $method = BuyerPaymentMethod::save('501', 'cus_123', 'pm_123', new FrozenClock);

    $method->releaseEvents();

    expect($method->releaseEvents())->toBe([]);
});

it('reconstitutes from persistence without raising any events', function () {
    $savedAt = new DateTimeImmutable('2026-11-22 10:00:00');

    $method = BuyerPaymentMethod::fromPersistence('501', 'cus_123', 'pm_123', $savedAt);

    expect($method->stripePaymentMethodId)->toBe('pm_123')
        ->and($method->releaseEvents())->toBe([]);
});
