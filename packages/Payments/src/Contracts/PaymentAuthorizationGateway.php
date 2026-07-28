<?php

declare(strict_types=1);

namespace RowBuddy\Payments\Contracts;

use RowBuddy\Payments\ValueObjects\AuthorizationAttempt;
use RowBuddy\SharedKernel\ValueObjects\Money;

/**
 * Payments-owned port to Stripe's charge/authorization API (ADR-016 §2:
 * separate charges and transfers, not a destination charge — no seller
 * Connect account is ever referenced here). `stripePaymentMethodId` is
 * assumed to already exist and belong to the buyer (ADR-016 §1) — this
 * port has no concept of how it was acquired.
 */
interface PaymentAuthorizationGateway
{
    /**
     * `idempotencyKey` should be deterministic per business operation
     * (e.g. derived from the auction id), not a fresh value per call —
     * that is what makes a retried caller (a queued listener re-running
     * after a partial failure) safe against double-charging, independent
     * of any locking this package does not implement.
     */
    public function authorize(
        string $idempotencyKey,
        Money $amount,
        string $stripePaymentMethodId,
        string $description,
    ): AuthorizationAttempt;
}
