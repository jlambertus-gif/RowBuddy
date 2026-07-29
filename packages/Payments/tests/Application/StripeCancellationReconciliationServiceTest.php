<?php

declare(strict_types=1);

use RowBuddy\Payments\Application\StripeCancellationReconciliationService;
use RowBuddy\Payments\Events\AuthorizationCancelled;
use RowBuddy\Payments\PaymentIntent;
use RowBuddy\Payments\Tests\Fakes\InMemoryPaymentIntentRepository;
use RowBuddy\Payments\Tests\Fakes\RecordingDomainEventPublisher;
use RowBuddy\Payments\Tests\Fakes\RecordingTransactionManager;
use RowBuddy\Payments\ValueObjects\PaymentIntentStatus;
use RowBuddy\SharedKernel\Support\FrozenClock;
use RowBuddy\SharedKernel\ValueObjects\Currency;
use RowBuddy\SharedKernel\ValueObjects\Money;

it('cancels the matching Authorized PaymentIntent and publishes AuthorizationCancelled', function () {
    $paymentIntents = new InMemoryPaymentIntentRepository;
    $paymentIntent = PaymentIntent::authorize(
        'payment-intent-1', 'auction-1', 'bid-1', '101', '102',
        new Money(11000, new Currency('USD')),
        new Money(1000, new Currency('USD')),
        new Money(100000, new Currency('USD')),
        'pi_123',
        new FrozenClock,
    );
    $paymentIntent->releaseEvents();
    $paymentIntents->save($paymentIntent);

    $events = new RecordingDomainEventPublisher;
    $service = new StripeCancellationReconciliationService($paymentIntents, new RecordingTransactionManager, $events, new FrozenClock);

    $service->reconcileCancellation('pi_123');

    expect($paymentIntents->findById('payment-intent-1')->status())->toBe(PaymentIntentStatus::Cancelled)
        ->and($events->published)->toHaveCount(1)
        ->and($events->published[0])->toBeInstanceOf(AuthorizationCancelled::class);
});

it('does nothing when no PaymentIntent carries the given Stripe payment intent id', function () {
    $events = new RecordingDomainEventPublisher;
    $service = new StripeCancellationReconciliationService(new InMemoryPaymentIntentRepository, new RecordingTransactionManager, $events, new FrozenClock);

    $service->reconcileCancellation('pi_unknown');

    expect($events->published)->toBe([]);
});

it('is idempotent: reconciling an already-Cancelled PaymentIntent is a no-op', function () {
    $paymentIntents = new InMemoryPaymentIntentRepository;
    $paymentIntent = PaymentIntent::authorize(
        'payment-intent-1', 'auction-1', 'bid-1', '101', '102',
        new Money(11000, new Currency('USD')),
        new Money(1000, new Currency('USD')),
        new Money(100000, new Currency('USD')),
        'pi_123',
        new FrozenClock,
    );
    $paymentIntent->releaseEvents();
    $paymentIntent->cancelAuthorization('already cancelled', new FrozenClock);
    $paymentIntent->releaseEvents();
    $paymentIntents->save($paymentIntent);

    $events = new RecordingDomainEventPublisher;
    $service = new StripeCancellationReconciliationService($paymentIntents, new RecordingTransactionManager, $events, new FrozenClock);

    $service->reconcileCancellation('pi_123');

    expect($events->published)->toBe([]);
});

it('does not reconcile a PaymentIntent that already Captured', function () {
    $paymentIntents = new InMemoryPaymentIntentRepository;
    $paymentIntent = PaymentIntent::authorize(
        'payment-intent-1', 'auction-1', 'bid-1', '101', '102',
        new Money(11000, new Currency('USD')),
        new Money(1000, new Currency('USD')),
        new Money(100000, new Currency('USD')),
        'pi_123',
        new FrozenClock,
    );
    $paymentIntent->releaseEvents();
    $paymentIntent->capture(new FrozenClock);
    $paymentIntent->releaseEvents();
    $paymentIntents->save($paymentIntent);

    $events = new RecordingDomainEventPublisher;
    $service = new StripeCancellationReconciliationService($paymentIntents, new RecordingTransactionManager, $events, new FrozenClock);

    $service->reconcileCancellation('pi_123');

    expect($paymentIntents->findById('payment-intent-1')->status())->toBe(PaymentIntentStatus::Captured)
        ->and($events->published)->toBe([]);
});
