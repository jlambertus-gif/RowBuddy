<?php

declare(strict_types=1);

namespace RowBuddy\Payments\Tests\Fakes;

use RowBuddy\Payments\Contracts\ConnectAccountGateway;
use RowBuddy\Payments\ValueObjects\PayoutEligibility;

final class FakeConnectAccountGateway implements ConnectAccountGateway
{
    /** @var list<string> */
    public array $createExpressAccountCalls = [];

    /** @var list<array{stripeAccountId: string, returnUrl: string, refreshUrl: string}> */
    public array $createOnboardingLinkCalls = [];

    public string $nextStripeAccountId = 'acct_fake';

    public string $nextOnboardingUrl = 'https://connect.stripe.com/setup/fake';

    public PayoutEligibility $nextEligibility;

    public function __construct()
    {
        $this->nextEligibility = new PayoutEligibility(false, false);
    }

    public function createExpressAccount(string $sellerId): string
    {
        $this->createExpressAccountCalls[] = $sellerId;

        return $this->nextStripeAccountId;
    }

    public function createOnboardingLink(string $stripeAccountId, string $returnUrl, string $refreshUrl): string
    {
        $this->createOnboardingLinkCalls[] = [
            'stripeAccountId' => $stripeAccountId,
            'returnUrl' => $returnUrl,
            'refreshUrl' => $refreshUrl,
        ];

        return $this->nextOnboardingUrl;
    }

    public function fetchEligibility(string $stripeAccountId): PayoutEligibility
    {
        return $this->nextEligibility;
    }
}
