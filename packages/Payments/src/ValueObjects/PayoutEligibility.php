<?php

declare(strict_types=1);

namespace RowBuddy\Payments\ValueObjects;

/**
 * A live read of a seller's Stripe Connect Express account state — never
 * cached or persisted (ADR/product decision: eligibility is whatever
 * Stripe currently reports, checked fresh each time, not derived or
 * stored by RowBuddy). No additional RowBuddy-side KYC review exists in
 * the MVP.
 */
final class PayoutEligibility
{
    public function __construct(
        public readonly bool $chargesEnabled,
        public readonly bool $payoutsEnabled,
    ) {}

    public function isPayoutReady(): bool
    {
        return $this->chargesEnabled && $this->payoutsEnabled;
    }
}
