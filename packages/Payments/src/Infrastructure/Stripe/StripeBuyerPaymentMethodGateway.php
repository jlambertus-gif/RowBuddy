<?php

declare(strict_types=1);

namespace RowBuddy\Payments\Infrastructure\Stripe;

use RowBuddy\Payments\Contracts\BuyerPaymentMethodGateway;
use RowBuddy\Payments\Exceptions\SetupIntentBuyerMismatch;
use RowBuddy\Payments\Exceptions\SetupIntentNotConfirmed;
use RowBuddy\Payments\ValueObjects\ConfirmedPaymentMethod;
use RowBuddy\Payments\ValueObjects\SetupIntentDraft;
use Stripe\StripeClient;

/**
 * The only class in this package allowed to know the Stripe SDK's shape
 * for Customer/SetupIntent. Every Stripe error surfaced here (besides the
 * two expected business conditions {@see BuyerPaymentMethodGateway}
 * documents) is left to propagate uncaught, the same unexpected-
 * infrastructure-failure posture {@see StripePaymentAuthorizationGateway}'s
 * `cancel()`/`refund()` already use.
 */
final class StripeBuyerPaymentMethodGateway implements BuyerPaymentMethodGateway
{
    public function __construct(private readonly StripeClient $client) {}

    public function createCustomer(string $buyerId): string
    {
        $customer = $this->client->customers->create([
            'metadata' => [
                'rowbuddy_buyer_id' => $buyerId,
            ],
        ]);

        return $customer->id;
    }

    public function createSetupIntent(string $stripeCustomerId): SetupIntentDraft
    {
        $setupIntent = $this->client->setupIntents->create([
            'customer' => $stripeCustomerId,
            'usage' => 'off_session',
        ]);

        return new SetupIntentDraft($stripeCustomerId, $setupIntent->id, (string) $setupIntent->client_secret);
    }

    public function retrieveConfirmedPaymentMethod(string $setupIntentId, string $buyerId): ConfirmedPaymentMethod
    {
        $setupIntent = $this->client->setupIntents->retrieve($setupIntentId);

        if ($setupIntent->status !== 'succeeded') {
            throw SetupIntentNotConfirmed::forSetupIntentId($setupIntentId);
        }

        $stripeCustomerId = (string) $setupIntent->customer;
        $customer = $this->client->customers->retrieve($stripeCustomerId);

        if (($customer->metadata['rowbuddy_buyer_id'] ?? null) !== $buyerId) {
            throw SetupIntentBuyerMismatch::forSetupIntentId($setupIntentId);
        }

        return new ConfirmedPaymentMethod($stripeCustomerId, (string) $setupIntent->payment_method);
    }
}
