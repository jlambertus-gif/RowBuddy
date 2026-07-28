<?php

declare(strict_types=1);

namespace RowBuddy\Payments\Infrastructure\Stripe;

use RowBuddy\Payments\Contracts\PaymentAuthorizationGateway;
use RowBuddy\Payments\ValueObjects\AuthorizationAttempt;
use RowBuddy\SharedKernel\ValueObjects\Money;
use Stripe\Exception\CardException;
use Stripe\StripeClient;

/**
 * The only class in this package allowed to know the Stripe SDK's shape
 * for authorization. Creates a manual-capture PaymentIntent on RowBuddy's
 * own platform Stripe account (ADR-016 §2 — separate charges and
 * transfers, no `transfer_data`, no seller Connect account referenced).
 *
 * Mirrors ADR-012 §1a's "expected rejection vs. unexpected failure"
 * distinction: only {@see CardException} (a real card decline) is caught
 * and translated into a Failed outcome, since that is the one genuinely
 * expected business rejection this call can produce. Every other Stripe
 * exception (auth errors, rate limits, connection failures — all other
 * `ApiErrorException` subclasses) is left to propagate uncaught, exactly
 * like an unexpected infrastructure failure elsewhere in this codebase.
 */
final class StripePaymentAuthorizationGateway implements PaymentAuthorizationGateway
{
    public function __construct(private readonly StripeClient $client) {}

    public function authorize(
        string $idempotencyKey,
        Money $amount,
        string $stripePaymentMethodId,
        string $description,
    ): AuthorizationAttempt {
        try {
            $paymentIntent = $this->client->paymentIntents->create(
                [
                    'amount' => $amount->minorUnits,
                    'currency' => strtolower((string) $amount->currency),
                    'payment_method' => $stripePaymentMethodId,
                    'capture_method' => 'manual',
                    'confirm' => true,
                    'off_session' => true,
                    'description' => $description,
                ],
                ['idempotency_key' => $idempotencyKey],
            );

            return AuthorizationAttempt::succeeded($paymentIntent->id);
        } catch (CardException $exception) {
            return AuthorizationAttempt::failed($exception->getStripeCode() ?? $exception->getMessage());
        }
    }
}
