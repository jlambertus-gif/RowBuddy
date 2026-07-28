<?php

declare(strict_types=1);

namespace RowBuddy\Payments\Application;

use RowBuddy\Payments\Contracts\PaymentProcessingCostPolicy;
use RowBuddy\SharedKernel\ValueObjects\Money;

/**
 * The MVP default: a flat percentage plus a fixed fee, approximating
 * Stripe's real published card-processing rate — configurable through
 * application configuration (bound in PaymentsServiceProvider), not a
 * permanent domain invariant. `Money::percentage()` only supports
 * whole-number percentages, so this rounds Stripe's real ~2.9% to a
 * whole-number MVP estimate rather than extending the shared-kernel
 * `Money` value object for a figure that is already only an estimate.
 */
final class FixedPaymentProcessingCostPolicy implements PaymentProcessingCostPolicy
{
    public function __construct(
        private readonly int $percentage,
        private readonly Money $fixedFee,
    ) {}

    public function estimate(Money $amount): Money
    {
        return $amount->percentage($this->percentage)->add($this->fixedFee);
    }
}
