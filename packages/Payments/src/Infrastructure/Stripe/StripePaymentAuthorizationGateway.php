<?php

declare(strict_types=1);

namespace RowBuddy\Payments\Infrastructure\Stripe;

use RowBuddy\Payments\Contracts\PaymentAuthorizationGateway;
use RowBuddy\Payments\ValueObjects\AuthorizationAttempt;
use RowBuddy\Payments\ValueObjects\CaptureAttempt;
use RowBuddy\SharedKernel\ValueObjects\Money;
use Stripe\Exception\CardException;
use Stripe\Exception\InvalidRequestException;
use Stripe\StripeClient;

/**
 * The only class in this package allowed to know the Stripe SDK's shape
 * for authorization, capture, cancellation, and refund. Creates a
 * manual-capture PaymentIntent on RowBuddy's own platform Stripe account
 * (ADR-016 §2 — separate charges and transfers, no `transfer_data`, no
 * seller Connect account referenced).
 *
 * Mirrors ADR-012 §1a's "expected rejection vs. unexpected failure"
 * distinction throughout: only {@see CardException} is caught for
 * `authorize()`, and only {@see InvalidRequestException} for `capture()`
 * (e.g. the authorization already expired Stripe-side) — the one
 * genuinely expected business rejection each call can produce. `refund()`
 * follows `cancel()`'s posture instead, deliberately not modeling a
 * "RefundFailed" state (ADR-022: avoid introducing extra lifecycle
 * states unless strictly necessary) — this is called only after
 * `PaymentIntent::refund()` has already validated the amount under lock,
 * so any Stripe-side failure indicates a genuine inconsistency worth
 * propagating loudly rather than converting to a domain state. Every
 * other Stripe exception is left to propagate uncaught, exactly like an
 * unexpected infrastructure failure elsewhere in this codebase.
 *
 * `capture()` and `cancel()` derive their own deterministic Stripe
 * idempotency key from the target PaymentIntent id (Phase 9 Sprint 5
 * security review) — matching `authorize()`/`refund()`'s existing
 * pattern in this same class. Each is a one-shot state transition Stripe
 * itself already rejects a second time even without a key, so this is a
 * defense-in-depth consistency fix, not a fix for an observed duplicate
 * side effect.
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

    public function capture(string $stripePaymentIntentId): CaptureAttempt
    {
        try {
            $this->client->paymentIntents->capture(
                $stripePaymentIntentId,
                [],
                ['idempotency_key' => "payments.capture.{$stripePaymentIntentId}"],
            );

            return CaptureAttempt::succeeded();
        } catch (InvalidRequestException $exception) {
            return CaptureAttempt::failed($exception->getMessage());
        }
    }

    public function cancel(string $stripePaymentIntentId, string $reason): void
    {
        // $reason is RowBuddy's own free-form domain reason (e.g. "window
        // expired unconfirmed") — not forwarded to Stripe's own
        // `cancellation_reason`, a fixed enum (duplicate/fraudulent/
        // requested_by_customer/abandoned) that doesn't map cleanly onto
        // it. It is preserved in AuthorizationCancelled's own payload for
        // audit purposes instead.
        $this->client->paymentIntents->cancel(
            $stripePaymentIntentId,
            [],
            ['idempotency_key' => "payments.cancel.{$stripePaymentIntentId}"],
        );
    }

    public function refund(string $stripePaymentIntentId, Money $amount, string $idempotencyKey): void
    {
        $this->client->refunds->create(
            [
                'payment_intent' => $stripePaymentIntentId,
                'amount' => $amount->minorUnits,
            ],
            ['idempotency_key' => $idempotencyKey],
        );
    }
}
