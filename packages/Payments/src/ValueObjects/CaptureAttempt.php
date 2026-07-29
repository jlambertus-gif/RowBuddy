<?php

declare(strict_types=1);

namespace RowBuddy\Payments\ValueObjects;

use RowBuddy\Payments\Contracts\PaymentAuthorizationGateway;

/**
 * The outcome of a single call to
 * {@see PaymentAuthorizationGateway::capture()} — mirrors
 * `AuthorizationAttempt`'s "outcome as data, not an exception" shape: a
 * capture failing because the authorization already expired Stripe-side
 * is an expected business outcome (ADR-019 §2), not an infrastructure
 * failure.
 */
final class CaptureAttempt
{
    private function __construct(
        public readonly bool $succeeded,
        public readonly ?string $failureReason,
    ) {}

    public static function succeeded(): self
    {
        return new self(true, null);
    }

    public static function failed(string $failureReason): self
    {
        return new self(false, $failureReason);
    }
}
