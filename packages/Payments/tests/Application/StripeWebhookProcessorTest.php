<?php

declare(strict_types=1);

use RowBuddy\Payments\Application\StripeCancellationReconciliationService;
use RowBuddy\Payments\Application\StripeWebhookProcessor;
use RowBuddy\Payments\Events\StripeWebhookEventProcessed;
use RowBuddy\Payments\PaymentIntent;
use RowBuddy\Payments\Tests\Fakes\InMemoryPaymentIntentRepository;
use RowBuddy\Payments\Tests\Fakes\InMemoryWebhookEventRepository;
use RowBuddy\Payments\Tests\Fakes\RecordingDomainEventPublisher;
use RowBuddy\Payments\Tests\Fakes\RecordingTransactionManager;
use RowBuddy\Payments\ValueObjects\PaymentIntentStatus;
use RowBuddy\SharedKernel\Support\FrozenClock;
use RowBuddy\SharedKernel\ValueObjects\Currency;
use RowBuddy\SharedKernel\ValueObjects\Money;

it('processes a new event, records it, and publishes StripeWebhookEventProcessed', function () {
    $webhookEvents = new InMemoryWebhookEventRepository;
    $paymentIntents = new InMemoryPaymentIntentRepository;
    $events = new RecordingDomainEventPublisher;
    $reconciler = new StripeCancellationReconciliationService($paymentIntents, new RecordingTransactionManager, $events, new FrozenClock);
    $processor = new StripeWebhookProcessor($webhookEvents, $reconciler, $events, new FrozenClock);

    $result = $processor->process('evt_123', 'payment_intent.succeeded', 'pi_123');

    expect($result)->toBeTrue()
        ->and($webhookEvents->recorded)->toHaveKey('evt_123')
        ->and($events->published)->toHaveCount(1)
        ->and($events->published[0])->toBeInstanceOf(StripeWebhookEventProcessed::class);
});

it('is idempotent: processing the same event id twice is a no-op the second time', function () {
    $webhookEvents = new InMemoryWebhookEventRepository;
    $paymentIntents = new InMemoryPaymentIntentRepository;
    $events = new RecordingDomainEventPublisher;
    $reconciler = new StripeCancellationReconciliationService($paymentIntents, new RecordingTransactionManager, $events, new FrozenClock);
    $processor = new StripeWebhookProcessor($webhookEvents, $reconciler, $events, new FrozenClock);

    $first = $processor->process('evt_123', 'payment_intent.succeeded', 'pi_123');
    $second = $processor->process('evt_123', 'payment_intent.succeeded', 'pi_123');

    expect($first)->toBeTrue()
        ->and($second)->toBeFalse()
        ->and($events->published)->toHaveCount(1);
});

it('reconciles a payment_intent.canceled event by cancelling the matching Authorized PaymentIntent', function () {
    $webhookEvents = new InMemoryWebhookEventRepository;
    $paymentIntents = new InMemoryPaymentIntentRepository;
    $events = new RecordingDomainEventPublisher;

    $paymentIntent = PaymentIntent::authorize(
        'payment-intent-1',
        'auction-1',
        'bid-1',
        '101',
        '102',
        new Money(11000, new Currency('USD')),
        new Money(1000, new Currency('USD')),
        new Money(100000, new Currency('USD')),
        'pi_123',
        new FrozenClock,
    );
    $paymentIntent->releaseEvents();
    $paymentIntents->save($paymentIntent);

    $reconciler = new StripeCancellationReconciliationService($paymentIntents, new RecordingTransactionManager, $events, new FrozenClock);
    $processor = new StripeWebhookProcessor($webhookEvents, $reconciler, $events, new FrozenClock);

    $processor->process('evt_123', 'payment_intent.canceled', 'pi_123');

    expect($paymentIntents->findById('payment-intent-1')->status())->toBe(PaymentIntentStatus::Cancelled);

    $cancelledEvents = array_filter($events->published, static fn ($event): bool => ! $event instanceof StripeWebhookEventProcessed);
    expect($cancelledEvents)->toHaveCount(1);
});

it('does nothing when a payment_intent.canceled event references an unknown Stripe payment intent id', function () {
    $webhookEvents = new InMemoryWebhookEventRepository;
    $paymentIntents = new InMemoryPaymentIntentRepository;
    $events = new RecordingDomainEventPublisher;
    $reconciler = new StripeCancellationReconciliationService($paymentIntents, new RecordingTransactionManager, $events, new FrozenClock);
    $processor = new StripeWebhookProcessor($webhookEvents, $reconciler, $events, new FrozenClock);

    $processor->process('evt_123', 'payment_intent.canceled', 'pi_unknown');

    expect($events->published)->toHaveCount(1)
        ->and($events->published[0])->toBeInstanceOf(StripeWebhookEventProcessed::class);
});

it('does not react to other event types', function () {
    $webhookEvents = new InMemoryWebhookEventRepository;
    $paymentIntents = new InMemoryPaymentIntentRepository;
    $events = new RecordingDomainEventPublisher;

    $paymentIntent = PaymentIntent::authorize(
        'payment-intent-1',
        'auction-1',
        'bid-1',
        '101',
        '102',
        new Money(11000, new Currency('USD')),
        new Money(1000, new Currency('USD')),
        new Money(100000, new Currency('USD')),
        'pi_123',
        new FrozenClock,
    );
    $paymentIntent->releaseEvents();
    $paymentIntents->save($paymentIntent);

    $reconciler = new StripeCancellationReconciliationService($paymentIntents, new RecordingTransactionManager, $events, new FrozenClock);
    $processor = new StripeWebhookProcessor($webhookEvents, $reconciler, $events, new FrozenClock);

    $processor->process('evt_123', 'payment_intent.succeeded', 'pi_123');

    expect($paymentIntents->findById('payment-intent-1')->status())->toBe(PaymentIntentStatus::Authorized);
});
