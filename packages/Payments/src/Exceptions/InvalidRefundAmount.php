<?php

declare(strict_types=1);

namespace RowBuddy\Payments\Exceptions;

use RowBuddy\SharedKernel\Exceptions\DomainException;
use RowBuddy\SharedKernel\ValueObjects\Currency;
use RowBuddy\SharedKernel\ValueObjects\Money;

/**
 * Raised when a `PaymentIntent::refund()` amount violates one of ADR-022
 * §2's invariants: positive, same currency as the captured total, and
 * never exceeding it. `capturedAmount` (`PaymentIntent::amount`) is the
 * only financial ceiling this package enforces — it is never decomposed
 * into bid/fee components (ADR-022 §5).
 */
final class InvalidRefundAmount extends DomainException
{
    public static function mustBePositive(string $paymentIntentId): self
    {
        return new self("Refund amount for PaymentIntent [{$paymentIntentId}] must be greater than zero.");
    }

    public static function currencyMismatch(string $paymentIntentId, Currency $given, Currency $expected): self
    {
        return new self(
            "Refund amount for PaymentIntent [{$paymentIntentId}] is denominated in [{$given}], expected [{$expected}]."
        );
    }

    public static function exceedsCapturedTotal(string $paymentIntentId, Money $amount, Money $capturedTotal): self
    {
        return new self(
            "Refund amount [{$amount->minorUnits} {$amount->currency}] for PaymentIntent [{$paymentIntentId}] exceeds the captured total [{$capturedTotal->minorUnits} {$capturedTotal->currency}]."
        );
    }
}
