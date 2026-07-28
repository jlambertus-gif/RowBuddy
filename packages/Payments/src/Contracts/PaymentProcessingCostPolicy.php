<?php

declare(strict_types=1);

namespace RowBuddy\Payments\Contracts;

use RowBuddy\SharedKernel\ValueObjects\Money;

/**
 * Estimates the standard payment-processing cost a real Stripe charge of
 * this amount would incur (business-rules.md #6: "seller receives the
 * full winning bid amount, minus standard payment-processing costs
 * only" — the platform fee never reduces the seller's cut). This is
 * explicitly an *estimate* for Phase 4's "expected settlement"
 * calculation (ADR-015 §1) — the real, final cost is only known once
 * Transfers (Phase 5+) actually executes a payout against Stripe's real
 * per-transaction fee.
 */
interface PaymentProcessingCostPolicy
{
    public function estimate(Money $amount): Money;
}
