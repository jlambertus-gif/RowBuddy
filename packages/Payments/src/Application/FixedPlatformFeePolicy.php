<?php

declare(strict_types=1);

namespace RowBuddy\Payments\Application;

use RowBuddy\Payments\Contracts\PlatformFeePolicy;

/**
 * The MVP default: a single fixed percentage, configurable through
 * application configuration (bound in PaymentsServiceProvider), defaulting
 * to 10%. Framework-agnostic — the actual percentage lives in exactly one
 * binding, not here, so it can be changed without touching this class,
 * FeeCalculator, or PaymentIntent.
 */
final class FixedPlatformFeePolicy implements PlatformFeePolicy
{
    public function __construct(private readonly int $percentage) {}

    public function percentage(): int
    {
        return $this->percentage;
    }
}
