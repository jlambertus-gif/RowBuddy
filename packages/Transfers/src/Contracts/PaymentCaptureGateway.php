<?php

declare(strict_types=1);

namespace RowBuddy\Transfers\Contracts;

/**
 * The Transfers-to-Payments capture contract (ADR-019 §4), implemented by
 * an apps/web adapter bridging to Payments' own `PaymentCaptureService`.
 *
 * Deliberately `void` on both operations, not returning a result object —
 * unlike ADR-019 §4's original sketch (`CaptureAttempt`/
 * `ReauthorizationAttempt` flowing back to Transfers), `PaymentCaptureService`
 * (Phase 5 Sprint 3) already fully owns and audits the
 * captured/capture-failed/cancelled outcome on its own side; Transfers
 * only needs to know that it asked, not what Stripe actually did.
 *
 * `reauthorize()` is deliberately not part of this interface yet — it
 * depends on data (the original Stripe payment method id) nothing in
 * this codebase stores, and is tied to the not-yet-built scheduler
 * (ADR-018 §2/§3). It will be added here once that sprint defines it,
 * rather than included now as an unimplementable stub.
 */
interface PaymentCaptureGateway
{
    public function capture(string $auctionId): void;

    public function cancel(string $auctionId, string $reason): void;
}
