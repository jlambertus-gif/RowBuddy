<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RowBuddy\Payments\Contracts\PaymentIntentRepository;
use RowBuddy\Payments\PaymentIntent;
use RowBuddy\Payments\ValueObjects\PaymentIntentStatus;
use RowBuddy\SharedKernel\Support\FrozenClock;
use RowBuddy\SharedKernel\ValueObjects\Currency;
use RowBuddy\SharedKernel\ValueObjects\Money;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['services.stripe.webhook_secret' => 'whsec_test_secret']);
});

it('accepts a correctly signed webhook and records it in the idempotency ledger', function () {
    $payload = json_encode([
        'id' => 'evt_test_1',
        'object' => 'event',
        'type' => 'payment_intent.succeeded',
        'data' => ['object' => []],
    ]);
    $header = signedStripeWebhookHeader($payload, 'whsec_test_secret');

    // Sent via call() with the raw signed payload as the request body,
    // rather than postJson()'s array-encoded body, so the exact bytes the
    // server receives match what was signed.
    $response = $this->call('POST', '/webhooks/stripe', [], [], [], [
        'HTTP_STRIPE_SIGNATURE' => $header,
        'CONTENT_TYPE' => 'application/json',
    ], $payload);

    $response->assertOk()->assertJson(['received' => true]);

    $this->assertDatabaseHas('webhook_events', [
        'stripe_event_id' => 'evt_test_1',
        'event_type' => 'payment_intent.succeeded',
    ]);
});

it('rejects a webhook with an invalid signature and records nothing', function () {
    $payload = json_encode(['id' => 'evt_test_2', 'object' => 'event', 'type' => 'payment_intent.succeeded']);

    $response = $this->call('POST', '/webhooks/stripe', [], [], [], [
        'HTTP_STRIPE_SIGNATURE' => 'not-a-valid-signature',
        'CONTENT_TYPE' => 'application/json',
    ], $payload);

    $response->assertStatus(400)->assertJson(['error' => 'invalid_signature']);

    $this->assertDatabaseMissing('webhook_events', ['stripe_event_id' => 'evt_test_2']);
});

it('is idempotent: redelivering the same event id only records it once', function () {
    $payload = json_encode(['id' => 'evt_test_3', 'object' => 'event', 'type' => 'payment_intent.succeeded']);
    $header = signedStripeWebhookHeader($payload, 'whsec_test_secret');

    $first = $this->call('POST', '/webhooks/stripe', [], [], [], [
        'HTTP_STRIPE_SIGNATURE' => $header,
        'CONTENT_TYPE' => 'application/json',
    ], $payload);
    $second = $this->call('POST', '/webhooks/stripe', [], [], [], [
        'HTTP_STRIPE_SIGNATURE' => $header,
        'CONTENT_TYPE' => 'application/json',
    ], $payload);

    $first->assertOk();
    $second->assertOk();

    expect(DB::table('webhook_events')->where('stripe_event_id', 'evt_test_3')->count())->toBe(1);
});

it('reconciles a payment_intent.canceled webhook by cancelling the matching Authorized PaymentIntent', function () {
    $paymentIntents = app(PaymentIntentRepository::class);
    $paymentIntent = PaymentIntent::authorize(
        (string) Str::uuid(),
        (string) Str::uuid(),
        (string) Str::uuid(),
        '1',
        '2',
        new Money(11000, new Currency('USD')),
        new Money(1000, new Currency('USD')),
        new Money(100000, new Currency('USD')),
        'pi_reconcile_test',
        new FrozenClock,
    );
    $paymentIntent->releaseEvents();
    $paymentIntents->save($paymentIntent);

    $payload = json_encode([
        'id' => 'evt_test_4',
        'object' => 'event',
        'type' => 'payment_intent.canceled',
        'data' => ['object' => ['id' => 'pi_reconcile_test']],
    ]);
    $header = signedStripeWebhookHeader($payload, 'whsec_test_secret');

    $response = $this->call('POST', '/webhooks/stripe', [], [], [], [
        'HTTP_STRIPE_SIGNATURE' => $header,
        'CONTENT_TYPE' => 'application/json',
    ], $payload);

    $response->assertOk();

    expect($paymentIntents->findById($paymentIntent->id)->status())->toBe(PaymentIntentStatus::Cancelled);
});
