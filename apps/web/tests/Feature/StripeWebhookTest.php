<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

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
