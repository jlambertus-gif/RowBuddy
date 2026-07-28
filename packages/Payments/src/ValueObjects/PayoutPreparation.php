<?php

declare(strict_types=1);

namespace RowBuddy\Payments\ValueObjects;

use RowBuddy\SharedKernel\ValueObjects\Money;

/**
 * A read-only snapshot of whether a payout for a won auction could be
 * prepared right now — the "payout-readiness validation" ADR-015 §1
 * names as in scope. This never executes, schedules, or triggers an
 * actual payout; it only reports the facts a future Phase 5 payout step
 * would need.
 */
final class PayoutPreparation
{
    public function __construct(
        public readonly bool $paymentAuthorized,
        public readonly bool $sellerAccountLinked,
        public readonly bool $sellerPayoutEligible,
        public readonly ?Money $expectedSettlementAmount,
    ) {}

    public function isReadyForPayout(): bool
    {
        return $this->paymentAuthorized && $this->sellerAccountLinked && $this->sellerPayoutEligible;
    }
}
