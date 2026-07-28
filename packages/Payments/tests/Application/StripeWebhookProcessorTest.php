<?php

declare(strict_types=1);

use RowBuddy\Payments\Application\StripeWebhookProcessor;
use RowBuddy\Payments\Events\StripeWebhookEventProcessed;
use RowBuddy\Payments\Tests\Fakes\InMemoryWebhookEventRepository;
use RowBuddy\Payments\Tests\Fakes\RecordingDomainEventPublisher;
use RowBuddy\SharedKernel\Support\FrozenClock;

it('processes a new event, records it, and publishes StripeWebhookEventProcessed', function () {
    $webhookEvents = new InMemoryWebhookEventRepository;
    $events = new RecordingDomainEventPublisher;
    $processor = new StripeWebhookProcessor($webhookEvents, $events, new FrozenClock);

    $result = $processor->process('evt_123', 'payment_intent.succeeded');

    expect($result)->toBeTrue()
        ->and($webhookEvents->recorded)->toHaveKey('evt_123')
        ->and($events->published)->toHaveCount(1)
        ->and($events->published[0])->toBeInstanceOf(StripeWebhookEventProcessed::class);
});

it('is idempotent: processing the same event id twice is a no-op the second time', function () {
    $webhookEvents = new InMemoryWebhookEventRepository;
    $events = new RecordingDomainEventPublisher;
    $processor = new StripeWebhookProcessor($webhookEvents, $events, new FrozenClock);

    $first = $processor->process('evt_123', 'payment_intent.succeeded');
    $second = $processor->process('evt_123', 'payment_intent.succeeded');

    expect($first)->toBeTrue()
        ->and($second)->toBeFalse()
        ->and($events->published)->toHaveCount(1);
});
