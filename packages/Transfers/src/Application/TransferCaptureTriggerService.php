<?php

declare(strict_types=1);

namespace RowBuddy\Transfers\Application;

use RowBuddy\Transfers\Contracts\PaymentCaptureGateway;

/**
 * The real `TransferConfirmed` consumer (ADR-019 §6) — mirrors
 * `TransferInitiationService`'s own shape reacting to `PaymentAuthorized`
 * and `AuctionWinAuthorizationService`'s reacting to `AuctionWon`: a
 * dedicated service invoked off a committed domain event's payload, never
 * triggered by a caller reading `Transfer`'s own status directly.
 *
 * `TransferConfirmationService` only validates, mutates, persists, and
 * publishes — it has no knowledge of Payments or capture. This service is
 * the sole caller of `PaymentCaptureGateway::capture()`, and must only ever
 * be invoked after `TransferConfirmed` has actually been published (i.e.
 * after the confirming transaction has committed) — never nested inside
 * that transaction, mirroring ADR-012 §1a's "expected rejection vs.
 * unexpected failure" principle: a legitimate confirmation must never be
 * rolled back merely because this later, separate capture step fails or
 * errors.
 *
 * No caller wires this to a real Laravel event listener yet — mirroring
 * `TransferInitiationService`'s/`AuctionWinAuthorizationService`'s own
 * shape, which likewise has no real listener registered in apps/web to
 * this day. That wiring is delivery-layer work for a later sprint.
 *
 * Safe to invoke more than once for the same auction: `PaymentCaptureService`
 * (Payments) already no-ops unless the underlying `PaymentIntent` is still
 * `Authorized`.
 */
final class TransferCaptureTriggerService
{
    public function __construct(
        private readonly PaymentCaptureGateway $captureGateway,
    ) {}

    public function handle(string $auctionId): void
    {
        $this->captureGateway->capture($auctionId);
    }
}
