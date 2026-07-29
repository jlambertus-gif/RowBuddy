<?php

declare(strict_types=1);

use RowBuddy\Payments\Exceptions\InvalidWebhookSignature;
use RowBuddy\Payments\Infrastructure\Stripe\StripeWebhookSignatureVerifier;

it('verifies a correctly signed payload and returns the event id, type, and object id', function () {
    $secret = 'whsec_test_secret';
    $payload = json_encode([
        'id' => 'evt_123',
        'object' => 'event',
        'type' => 'payment_intent.succeeded',
        'data' => ['object' => ['id' => 'pi_123']],
    ]);
    $header = signedStripeWebhookHeader($payload, $secret);
    $verifier = new StripeWebhookSignatureVerifier($secret);

    $verified = $verifier->verify($payload, $header);

    expect($verified->stripeEventId)->toBe('evt_123')
        ->and($verified->eventType)->toBe('payment_intent.succeeded')
        ->and($verified->objectId)->toBe('pi_123');
});

it('rejects a payload signed with the wrong secret', function () {
    $payload = json_encode(['id' => 'evt_123', 'object' => 'event', 'type' => 'payment_intent.succeeded']);
    $header = signedStripeWebhookHeader($payload, 'whsec_wrong_secret');
    $verifier = new StripeWebhookSignatureVerifier('whsec_test_secret');

    expect(fn () => $verifier->verify($payload, $header))->toThrow(InvalidWebhookSignature::class);
});

it('rejects a payload that was tampered with after signing', function () {
    $secret = 'whsec_test_secret';
    $originalPayload = json_encode(['id' => 'evt_123', 'object' => 'event', 'type' => 'payment_intent.succeeded']);
    $header = signedStripeWebhookHeader($originalPayload, $secret);
    $tamperedPayload = json_encode(['id' => 'evt_123', 'object' => 'event', 'type' => 'payment_intent.payment_failed']);
    $verifier = new StripeWebhookSignatureVerifier($secret);

    expect(fn () => $verifier->verify($tamperedPayload, $header))->toThrow(InvalidWebhookSignature::class);
});

it('rejects a malformed signature header', function () {
    $verifier = new StripeWebhookSignatureVerifier('whsec_test_secret');

    expect(fn () => $verifier->verify('{}', 'not-a-valid-header'))->toThrow(InvalidWebhookSignature::class);
});
