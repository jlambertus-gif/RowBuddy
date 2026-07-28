<?php

declare(strict_types=1);

namespace RowBuddy\Payments\Application;

use RowBuddy\Payments\Contracts\PaymentProcessingCostPolicy;
use RowBuddy\SharedKernel\ValueObjects\Money;

/**
 * Computes a seller's expected net settlement from their winning-bid
 * portion of an authorized PaymentIntent (ADR-015 §1's "expected
 * settlement calculation") — the platform fee never enters this
 * calculation (ADR-006: it is deducted from the buyer's side only), only
 * the estimated standard payment-processing cost is.
 */
final class SellerSettlementCalculator
{
    public function __construct(private readonly PaymentProcessingCostPolicy $processingCostPolicy) {}

    public function calculate(Money $winningAmount): Money
    {
        return $winningAmount->subtract($this->processingCostPolicy->estimate($winningAmount));
    }
}
