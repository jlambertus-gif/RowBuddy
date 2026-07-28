<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use RowBuddy\Payments\Exceptions\WebhookEventAlreadyProcessed;
use RowBuddy\Payments\Infrastructure\Eloquent\EloquentWebhookEventRepository;
use RowBuddy\Payments\ProcessedWebhookEvent;
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

    Capsule::schema()->create('webhook_events', function (Blueprint $table) {
        $table->string('stripe_event_id')->primary();
        $table->string('event_type');
        $table->timestamp('processed_at');
        $table->timestamps();
    });
});

afterEach(function () {
    Capsule::schema()->dropIfExists('webhook_events');
});

it('records a processed webhook event', function () {
    $repository = new EloquentWebhookEventRepository;

    $repository->record(ProcessedWebhookEvent::record('evt_123', 'payment_intent.succeeded', new FrozenClock));

    expect(true)->toBeTrue(); // no exception means the insert succeeded
});

it('rejects recording the same Stripe event id twice', function () {
    $repository = new EloquentWebhookEventRepository;
    $repository->record(ProcessedWebhookEvent::record('evt_123', 'payment_intent.succeeded', new FrozenClock));

    expect(fn () => $repository->record(ProcessedWebhookEvent::record('evt_123', 'payment_intent.payment_failed', new FrozenClock)))
        ->toThrow(WebhookEventAlreadyProcessed::class);
});
