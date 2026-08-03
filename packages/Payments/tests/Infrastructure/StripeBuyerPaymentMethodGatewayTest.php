<?php

declare(strict_types=1);

use RowBuddy\Payments\Exceptions\SetupIntentBuyerMismatch;
use RowBuddy\Payments\Exceptions\SetupIntentNotConfirmed;
use RowBuddy\Payments\Infrastructure\Stripe\StripeBuyerPaymentMethodGateway;
use Stripe\StripeClient;

/**
 * Real Stripe test-mode integration (ADR-027 Sprint 3) — exercises the
 * actual Stripe API, not a fake. Skipped with an explicit reason unless
 * real Stripe test-mode credentials are supplied via STRIPE_SECRET, the
 * same "external input, never fabricated" discipline Decision 2/7
 * already established for legal conclusions. Uses Stripe's own test
 * PaymentMethod token (`pm_card_visa`) to confirm a SetupIntent
 * server-side, standing in for what Stripe.js/Elements would otherwise do
 * client-side.
 */
function realStripeSecretOrSkip(): string
{
    $secret = getenv('STRIPE_SECRET') ?: '';

    if (! str_starts_with($secret, 'sk_test_')) {
        test()->markTestSkipped('Real Stripe test-mode credentials not configured (STRIPE_SECRET is not a sk_test_ key).');
    }

    return $secret;
}

it('creates a real Stripe Customer stamped with rowbuddy_buyer_id metadata', function () {
    $client = new StripeClient(realStripeSecretOrSkip());
    $gateway = new StripeBuyerPaymentMethodGateway($client);

    $stripeCustomerId = $gateway->createCustomer('501');

    $customer = $client->customers->retrieve($stripeCustomerId);
    expect($customer->metadata['rowbuddy_buyer_id'])->toBe('501');

    $client->customers->delete($stripeCustomerId);
});

it('creates a real SetupIntent scoped to the given Customer', function () {
    $client = new StripeClient(realStripeSecretOrSkip());
    $gateway = new StripeBuyerPaymentMethodGateway($client);
    $stripeCustomerId = $gateway->createCustomer('502');

    $draft = $gateway->createSetupIntent($stripeCustomerId);

    expect($draft->stripeCustomerId)->toBe($stripeCustomerId)
        ->and($draft->clientSecret)->toContain($draft->setupIntentId);

    $client->customers->delete($stripeCustomerId);
});

it('retrieves the confirmed PaymentMethod once the SetupIntent actually succeeds', function () {
    $client = new StripeClient(realStripeSecretOrSkip());
    $gateway = new StripeBuyerPaymentMethodGateway($client);
    $stripeCustomerId = $gateway->createCustomer('503');
    $draft = $gateway->createSetupIntent($stripeCustomerId);

    // Stands in for Stripe.js confirming the SetupIntent client-side.
    $client->setupIntents->confirm($draft->setupIntentId, ['payment_method' => 'pm_card_visa']);

    $confirmed = $gateway->retrieveConfirmedPaymentMethod($draft->setupIntentId, '503');

    expect($confirmed->stripeCustomerId)->toBe($stripeCustomerId)
        ->and($confirmed->stripePaymentMethodId)->toStartWith('pm_');

    $client->customers->delete($stripeCustomerId);
});

it('rejects a SetupIntent that has not been confirmed yet', function () {
    $client = new StripeClient(realStripeSecretOrSkip());
    $gateway = new StripeBuyerPaymentMethodGateway($client);
    $stripeCustomerId = $gateway->createCustomer('504');
    $draft = $gateway->createSetupIntent($stripeCustomerId);

    expect(fn () => $gateway->retrieveConfirmedPaymentMethod($draft->setupIntentId, '504'))
        ->toThrow(SetupIntentNotConfirmed::class);

    $client->customers->delete($stripeCustomerId);
});

it('rejects a confirmed SetupIntent when the requesting buyer id does not match', function () {
    $client = new StripeClient(realStripeSecretOrSkip());
    $gateway = new StripeBuyerPaymentMethodGateway($client);
    $stripeCustomerId = $gateway->createCustomer('505');
    $draft = $gateway->createSetupIntent($stripeCustomerId);
    $client->setupIntents->confirm($draft->setupIntentId, ['payment_method' => 'pm_card_visa']);

    expect(fn () => $gateway->retrieveConfirmedPaymentMethod($draft->setupIntentId, '999'))
        ->toThrow(SetupIntentBuyerMismatch::class);

    $client->customers->delete($stripeCustomerId);
});
