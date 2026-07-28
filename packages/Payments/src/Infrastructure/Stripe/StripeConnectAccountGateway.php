<?php

declare(strict_types=1);

namespace RowBuddy\Payments\Infrastructure\Stripe;

use RowBuddy\Payments\Application\SellerOnboardingService;
use RowBuddy\Payments\Contracts\ConnectAccountGateway;
use RowBuddy\Payments\ValueObjects\PayoutEligibility;
use Stripe\StripeClient;

/**
 * The only class in this package allowed to know the Stripe SDK's shape —
 * {@see SellerOnboardingService} depends only on {@see ConnectAccountGateway}.
 * `metadata.rowbuddy_seller_id` is set so a Stripe account can be traced
 * back to its seller from the Stripe dashboard/API alone, independent of
 * this package's own storage.
 */
final class StripeConnectAccountGateway implements ConnectAccountGateway
{
    public function __construct(private readonly StripeClient $client) {}

    public function createExpressAccount(string $sellerId): string
    {
        $account = $this->client->accounts->create([
            'type' => 'express',
            'metadata' => [
                'rowbuddy_seller_id' => $sellerId,
            ],
        ]);

        return $account->id;
    }

    public function createOnboardingLink(string $stripeAccountId, string $returnUrl, string $refreshUrl): string
    {
        $link = $this->client->accountLinks->create([
            'account' => $stripeAccountId,
            'type' => 'account_onboarding',
            'return_url' => $returnUrl,
            'refresh_url' => $refreshUrl,
        ]);

        return $link->url;
    }

    public function fetchEligibility(string $stripeAccountId): PayoutEligibility
    {
        $account = $this->client->accounts->retrieve($stripeAccountId);

        return new PayoutEligibility(
            (bool) $account->charges_enabled,
            (bool) $account->payouts_enabled,
        );
    }
}
