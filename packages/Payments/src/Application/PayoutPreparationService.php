<?php

declare(strict_types=1);

namespace RowBuddy\Payments\Application;

use RowBuddy\Payments\Contracts\ConnectAccountGateway;
use RowBuddy\Payments\Contracts\PaymentIntentRepository;
use RowBuddy\Payments\Contracts\SellerPayoutAccountRepository;
use RowBuddy\Payments\ValueObjects\PaymentIntentStatus;
use RowBuddy\Payments\ValueObjects\PayoutPreparation;

/**
 * Combines the facts ADR-015 §1's "payout preparation" names — an
 * authorized PaymentIntent, a linked Stripe Connect account, and its live
 * eligibility — into a single read-only readiness snapshot, plus the
 * seller's expected net settlement. Never executes a payout: no code
 * path here calls Stripe to move money, matching ADR-015 §8 exactly.
 */
final class PayoutPreparationService
{
    public function __construct(
        private readonly PaymentIntentRepository $paymentIntents,
        private readonly SellerPayoutAccountRepository $payoutAccounts,
        private readonly ConnectAccountGateway $connectGateway,
        private readonly SellerSettlementCalculator $settlementCalculator,
    ) {}

    public function prepareFor(string $auctionId, string $sellerId): PayoutPreparation
    {
        $paymentIntent = $this->paymentIntents->findByAuctionId($auctionId);
        $paymentAuthorized = $paymentIntent !== null && $paymentIntent->status() === PaymentIntentStatus::Authorized;

        $account = $this->payoutAccounts->findBySellerId($sellerId);
        $sellerAccountLinked = $account !== null;
        $eligibility = $account !== null ? $this->connectGateway->fetchEligibility($account->stripeAccountId) : null;

        $expectedSettlementAmount = null;

        if ($paymentIntent !== null && $paymentAuthorized) {
            $winningAmount = $paymentIntent->amount->subtract($paymentIntent->feeAmount);
            $expectedSettlementAmount = $this->settlementCalculator->calculate($winningAmount);
        }

        return new PayoutPreparation(
            $paymentAuthorized,
            $sellerAccountLinked,
            $eligibility?->isPayoutReady() ?? false,
            $expectedSettlementAmount,
        );
    }
}
