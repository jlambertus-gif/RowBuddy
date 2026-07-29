<?php

declare(strict_types=1);

namespace RowBuddy\Payments\Contracts;

use RowBuddy\Payments\ValueObjects\AuthorizationAttempt;
use RowBuddy\Payments\ValueObjects\CaptureAttempt;
use RowBuddy\SharedKernel\ValueObjects\Money;

/**
 * Payments-owned port to Stripe's charge/authorization API (ADR-016 §2:
 * separate charges and transfers, not a destination charge — no seller
 * Connect account is ever referenced here). `stripePaymentMethodId` is
 * assumed to already exist and belong to the buyer (ADR-016 §1) — this
 * port has no concept of how it was acquired.
 *
 * Gained `capture()`/`cancel()` in Phase 5 (ADR-019 §4/§6) — a second and
 * third method on the existing gateway rather than a new interface,
 * mirroring ADR-013 §3's precedent (`AuctionGateway` gaining
 * `applyAcceptedBidEffects()`): these operations already share this
 * gateway's Stripe client and error-handling posture, so splitting them
 * out would separate methods that already belong together.
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

    /**
     * Captures a previously authorized Stripe PaymentIntent in full (no
     * partial capture is modeled). A failure here (e.g. the authorization
     * already expired Stripe-side) is an expected business outcome,
     * returned as data, not thrown (ADR-019 §2).
     */
    public function capture(string $stripePaymentIntentId): CaptureAttempt;

    /**
     * Voids a previously authorized, never-captured Stripe PaymentIntent.
     * Unlike `capture()`, a failure here is not modeled as an expected
     * outcome — this is a deliberate action taken only after the caller
     * has already confirmed the payment intent is in an authorized state
     * under lock, so any Stripe-side failure indicates a genuine
     * inconsistency worth propagating loudly rather than converting to a
     * domain state.
     */
    public function cancel(string $stripePaymentIntentId, string $reason): void;
}
