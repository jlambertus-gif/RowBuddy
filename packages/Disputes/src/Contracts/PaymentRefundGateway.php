<?php

declare(strict_types=1);

namespace RowBuddy\Disputes\Contracts;

use RowBuddy\SharedKernel\ValueObjects\Money;

/**
 * The Disputes-to-Payments refund contract (ADR-022), implemented by an
 * apps/web adapter bridging to Payments' own `PaymentCaptureService`,
 * mirroring `PaymentCaptureGateway`'s (Transfers-owned) identical shape
 * one hop further: `void`, not a result object — `PaymentCaptureService`
 * already fully owns and audits the refunded outcome on its own side;
 * Disputes only needs to know that it asked.
 *
 * `disputeId` is passed through so Payments can derive a deterministic
 * Stripe idempotency key from stable domain data (the auction id and the
 * dispute resolving it) — required so retrying the same logical refund
 * (e.g. after a local failure downstream of an already-accepted Stripe
 * call) never risks a second, duplicate refund.
 */
interface PaymentRefundGateway
{
    public function refund(string $auctionId, string $disputeId, Money $amount, string $reason): void;
}
