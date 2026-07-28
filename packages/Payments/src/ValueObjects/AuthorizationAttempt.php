<?php

declare(strict_types=1);

namespace RowBuddy\Payments\ValueObjects;

/**
 * The outcome of a single call to {@see
 * \RowBuddy\Payments\Contracts\PaymentAuthorizationGateway::authorize()} —
 * mirrors the same "outcome as data, not an exception" shape as Bids'
 * BidPlacementOutcome (ADR-012 §1a): a declined card is an expected
 * business outcome, not an infrastructure failure.
 */
final class AuthorizationAttempt
{
    private function __construct(
        public readonly bool $succeeded,
        public readonly ?string $stripePaymentIntentId,
        public readonly ?string $failureReason,
    ) {}

    public static function succeeded(string $stripePaymentIntentId): self
    {
        return new self(true, $stripePaymentIntentId, null);
    }

    public static function failed(string $failureReason): self
    {
        return new self(false, null, $failureReason);
    }
}
