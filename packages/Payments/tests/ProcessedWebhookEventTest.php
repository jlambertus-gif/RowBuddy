<?php

declare(strict_types=1);

use RowBuddy\Payments\Events\StripeWebhookEventProcessed;
use RowBuddy\Payments\ProcessedWebhookEvent;
use RowBuddy\SharedKernel\Support\FrozenClock;

it('records a processed webhook event and raises a StripeWebhookEventProcessed event', function () {
    $processedAt = new DateTimeImmutable('2026-10-21 10:00:00');

    $event = ProcessedWebhookEvent::record('evt_123', 'payment_intent.succeeded', new FrozenClock($processedAt));

    expect($event->stripeEventId)->toBe('evt_123')
        ->and($event->eventType)->toBe('payment_intent.succeeded')
        ->and($event->processedAt)->toEqual($processedAt);

    $events = $event->releaseEvents();
    expect($events)->toHaveCount(1)
        ->and($events[0])->toBeInstanceOf(StripeWebhookEventProcessed::class)
        ->and($events[0]->payload())->toBe([
            'stripe_event_id' => 'evt_123',
            'event_type' => 'payment_intent.succeeded',
        ]);
});

it('releasing events clears them so they are not dispatched twice', function () {
    $event = ProcessedWebhookEvent::record('evt_123', 'payment_intent.succeeded', new FrozenClock);

    $event->releaseEvents();

    expect($event->releaseEvents())->toBe([]);
});

it('reconstitutes from persistence without raising any events', function () {
    $processedAt = new DateTimeImmutable('2026-10-21 10:00:00');

    $event = ProcessedWebhookEvent::fromPersistence('evt_123', 'payment_intent.succeeded', $processedAt);

    expect($event->eventType)->toBe('payment_intent.succeeded')
        ->and($event->releaseEvents())->toBe([]);
});
