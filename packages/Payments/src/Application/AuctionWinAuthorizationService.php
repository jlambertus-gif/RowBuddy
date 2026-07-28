<?php

declare(strict_types=1);

namespace RowBuddy\Payments\Application;

use RowBuddy\Payments\Contracts\DomainEventPublisher;
use RowBuddy\Payments\Contracts\PaymentAuthorizationGateway;
use RowBuddy\Payments\Contracts\PaymentIntentRepository;
use RowBuddy\Payments\Contracts\TransactionValueLimitPolicy;
use RowBuddy\Payments\Exceptions\TransactionValueLimitExceeded;
use RowBuddy\Payments\Exceptions\UnsupportedCurrency;
use RowBuddy\Payments\PaymentIntent;
use RowBuddy\SharedKernel\Contracts\ClockInterface;
use RowBuddy\SharedKernel\ValueObjects\Money;

/**
 * The real `AuctionWon` consumer (ADR-014: triggered solely by that
 * event's facts, never by reading Auction's own status; ADR-016: assumes
 * `stripePaymentMethodId` was already obtained by a not-yet-built
 * capability, and authorizes via the separate-charges-and-transfers
 * model — no seller Connect account is read here).
 *
 * `PaymentIntent::amount` is the buyer's full charged total — winning bid
 * plus platform fee — matching Stripe's own PaymentIntent.amount 1:1;
 * `feeAmount` is the portion of that total RowBuddy retains.
 *
 * Idempotent twice over: `findByAuctionId()` short-circuits a second call
 * for an auction that already has a PaymentIntent, and the Stripe
 * idempotency key this service derives from `auctionId` (not from the
 * freshly-generated `paymentIntentId`) protects even a concurrent or
 * retried caller from ever double-charging, independent of any locking
 * this package does not implement.
 *
 * Validates the total against the transaction value limit itself,
 * *before* ever calling Stripe (@throws TransactionValueLimitExceeded,
 * UnsupportedCurrency) — `PaymentIntent::authorize()`/`declineAuthorization()`
 * enforce the same invariant regardless of caller, but there is no reason
 * to authorize a real charge only to then be unable to construct a
 * PaymentIntent to represent it.
 */
final class AuctionWinAuthorizationService
{
    public function __construct(
        private readonly PaymentIntentRepository $paymentIntents,
        private readonly PaymentAuthorizationGateway $gateway,
        private readonly FeeCalculator $feeCalculator,
        private readonly TransactionValueLimitPolicy $valueLimitPolicy,
        private readonly DomainEventPublisher $events,
        private readonly ClockInterface $clock,
    ) {}

    public function handle(
        string $paymentIntentId,
        string $auctionId,
        string $winningBidId,
        string $sellerId,
        string $buyerId,
        Money $winningAmount,
        string $stripePaymentMethodId,
    ): PaymentIntent {
        $existing = $this->paymentIntents->findByAuctionId($auctionId);

        if ($existing !== null) {
            return $existing;
        }

        $feeAmount = $this->feeCalculator->calculate($winningAmount);
        $totalAmount = $winningAmount->add($feeAmount);
        $transactionValueLimit = $this->valueLimitPolicy->maximum();

        if (! $totalAmount->currency->equals($transactionValueLimit->currency)) {
            throw UnsupportedCurrency::forPaymentIntent($paymentIntentId, $totalAmount->currency, $transactionValueLimit->currency);
        }

        if ($totalAmount->isGreaterThan($transactionValueLimit)) {
            throw TransactionValueLimitExceeded::forPaymentIntent($paymentIntentId, $totalAmount, $transactionValueLimit);
        }

        $attempt = $this->gateway->authorize(
            "payments.auction_win_authorization.{$auctionId}",
            $totalAmount,
            $stripePaymentMethodId,
            "RowBuddy auction {$auctionId}, winning bid {$winningBidId}",
        );

        $paymentIntent = $attempt->succeeded
            ? PaymentIntent::authorize(
                $paymentIntentId,
                $auctionId,
                $winningBidId,
                $sellerId,
                $buyerId,
                $totalAmount,
                $feeAmount,
                $transactionValueLimit,
                $this->clock,
            )
            : PaymentIntent::declineAuthorization(
                $paymentIntentId,
                $auctionId,
                $winningBidId,
                $sellerId,
                $buyerId,
                $totalAmount,
                $feeAmount,
                $transactionValueLimit,
                $attempt->failureReason ?? 'unknown',
                $this->clock,
            );

        $this->paymentIntents->record($paymentIntent);

        foreach ($paymentIntent->releaseEvents() as $event) {
            $this->events->publish($event);
        }

        return $paymentIntent;
    }
}
