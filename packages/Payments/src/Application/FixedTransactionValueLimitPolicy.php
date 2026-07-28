<?php

declare(strict_types=1);

namespace RowBuddy\Payments\Application;

use RowBuddy\Payments\Contracts\TransactionValueLimitPolicy;
use RowBuddy\SharedKernel\ValueObjects\Money;

/**
 * The MVP default: a single fixed maximum, configurable through
 * application configuration (bound in PaymentsServiceProvider), defaulting
 * to USD 500 as a temporary fraud/AML control — not a permanent domain
 * invariant. Framework-agnostic — the actual limit lives in exactly one
 * binding, not here, so it can be changed or removed without touching
 * this class or PaymentIntent.
 */
final class FixedTransactionValueLimitPolicy implements TransactionValueLimitPolicy
{
    public function __construct(private readonly Money $maximum) {}

    public function maximum(): Money
    {
        return $this->maximum;
    }
}
