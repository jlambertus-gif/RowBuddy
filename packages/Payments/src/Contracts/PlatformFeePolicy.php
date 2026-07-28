<?php

declare(strict_types=1);

namespace RowBuddy\Payments\Contracts;

/**
 * The platform fee percentage charged to the buyer on top of the winning
 * bid (ADR-006). `PaymentIntent` never knows or derives this value — only
 * `FeeCalculator` reads this policy and hands the aggregate an
 * already-computed fee amount.
 */
interface PlatformFeePolicy
{
    /**
     * @return int whole-number percentage basis, e.g. 10 for 10%
     */
    public function percentage(): int;
}
