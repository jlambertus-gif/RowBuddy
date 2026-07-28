<?php

declare(strict_types=1);

namespace RowBuddy\Payments\Application;

use RowBuddy\Payments\Contracts\ConnectAccountGateway;
use RowBuddy\Payments\Contracts\DomainEventPublisher;
use RowBuddy\Payments\Contracts\SellerPayoutAccountRepository;
use RowBuddy\Payments\SellerPayoutAccount;
use RowBuddy\Payments\ValueObjects\PayoutEligibility;
use RowBuddy\SharedKernel\Contracts\ClockInterface;

/**
 * Orchestrates Stripe Connect Express onboarding (ADR-015 §1's "payout
 * preparation"). Creates and links a seller's Connect account at most
 * once, then returns a fresh onboarding link every time it's asked — it
 * never decides or stores whether the seller has finished onboarding;
 * that is always read live from Stripe (see PayoutEligibility).
 */
final class SellerOnboardingService
{
    public function __construct(
        private readonly SellerPayoutAccountRepository $accounts,
        private readonly ConnectAccountGateway $gateway,
        private readonly DomainEventPublisher $events,
        private readonly ClockInterface $clock,
    ) {}

    public function startOnboarding(string $sellerId, string $returnUrl, string $refreshUrl): string
    {
        $account = $this->accounts->findBySellerId($sellerId);

        if ($account === null) {
            $account = $this->linkNewAccount($sellerId);
        }

        return $this->gateway->createOnboardingLink($account->stripeAccountId, $returnUrl, $refreshUrl);
    }

    /**
     * Null when the seller has never started onboarding at all — distinct
     * from a linked-but-not-yet-eligible account, which returns a
     * PayoutEligibility with both flags false.
     */
    public function checkEligibility(string $sellerId): ?PayoutEligibility
    {
        $account = $this->accounts->findBySellerId($sellerId);

        if ($account === null) {
            return null;
        }

        return $this->gateway->fetchEligibility($account->stripeAccountId);
    }

    private function linkNewAccount(string $sellerId): SellerPayoutAccount
    {
        $stripeAccountId = $this->gateway->createExpressAccount($sellerId);
        $account = SellerPayoutAccount::link($sellerId, $stripeAccountId, $this->clock);

        $this->accounts->record($account);

        foreach ($account->releaseEvents() as $event) {
            $this->events->publish($event);
        }

        return $account;
    }
}
