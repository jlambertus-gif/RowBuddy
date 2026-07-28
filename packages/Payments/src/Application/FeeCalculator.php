<?php

declare(strict_types=1);

namespace RowBuddy\Payments\Application;

use RowBuddy\Payments\Contracts\PlatformFeePolicy;
use RowBuddy\SharedKernel\ValueObjects\Money;

/**
 * Pure, dependency-free computation of the platform fee owed on a winning
 * bid (ADR-006: buyer-side percentage, computed server-side only). Reads
 * the rate from {@see PlatformFeePolicy} so `PaymentIntent::authorize()`
 * never has to — it only receives the already-computed fee amount this
 * class produces.
 */
final class FeeCalculator
{
    public function __construct(private readonly PlatformFeePolicy $feePolicy) {}

    public function calculate(Money $winningAmount): Money
    {
        return $winningAmount->percentage($this->feePolicy->percentage());
    }
}
