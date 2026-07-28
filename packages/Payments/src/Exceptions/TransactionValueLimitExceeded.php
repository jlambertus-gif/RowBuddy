<?php

declare(strict_types=1);

namespace RowBuddy\Payments\Exceptions;

use RowBuddy\Payments\Contracts\TransactionValueLimitPolicy;
use RowBuddy\SharedKernel\Exceptions\DomainException;
use RowBuddy\SharedKernel\ValueObjects\Money;

/**
 * Raised when a `PaymentIntent`'s amount exceeds the currently-configured
 * {@see TransactionValueLimitPolicy} maximum — a temporary MVP fraud/AML
 * control (not a permanent domain invariant), enforced by the aggregate
 * itself regardless of which caller resolved the limit.
 */
final class TransactionValueLimitExceeded extends DomainException
{
    public static function forPaymentIntent(string $paymentIntentId, Money $amount, Money $limit): self
    {
        return new self(
            "PaymentIntent [{$paymentIntentId}] amount [{$amount->minorUnits} {$amount->currency}] exceeds the transaction value limit [{$limit->minorUnits} {$limit->currency}]."
        );
    }
}
