<?php

declare(strict_types=1);

namespace RowBuddy\Disputes\Application;

use RowBuddy\Disputes\Contracts\PaymentRefundGateway;
use RowBuddy\Disputes\ValueObjects\DisputeResolutionOutcome;
use RowBuddy\SharedKernel\ValueObjects\Money;

/**
 * The real `DisputeResolved` consumer (ADR-022) — mirrors
 * `TransferCaptureTriggerService`'s exact shape: a dedicated service
 * invoked off a committed domain event's payload, never triggered by a
 * caller reading `Dispute`'s own resolution outcome directly.
 *
 * `DisputeResolutionService` only validates, mutates, persists, and
 * publishes — it has no knowledge of Payments or refunds. This service
 * is the sole caller of `PaymentRefundGateway::refund()`, and must only
 * ever be invoked after `DisputeResolved` has actually been published
 * (i.e. after the resolving transaction has committed) — never nested
 * inside that transaction.
 *
 * No caller wires this to a real Laravel event listener yet — mirroring
 * every other cross-module reactor in this codebase (delivery-layer work
 * for a later sprint).
 *
 * **Outcome filtering lives here, not in the caller**: `handle()` takes
 * the full `DisputeResolved` payload (outcome included) and only calls
 * Payments for `RefundToBuyer`/`Split` — `ReleaseToSeller`/`Cancelled`
 * return immediately with no refund call. Making this service the single
 * source of truth for "should Payments be called at all" means the
 * decision is directly testable here, rather than depending on a
 * not-yet-built listener to have gotten the gating right.
 *
 * **Idempotency**: this service holds no state and performs no guard of
 * its own — retrying `handle()` for the same dispute is safe only
 * because `PaymentCaptureService::refund()` (Payments) guards on
 * `PaymentIntent` status and Stripe's own idempotency key does the rest
 * (see that class's own docblock for the exact sequence). This mirrors
 * `TransferCaptureTriggerService`, which likewise carries no idempotency
 * logic of its own, relying entirely on `PaymentCaptureService::capture()`'s
 * guard.
 */
final class DisputeRefundTriggerService
{
    public function __construct(
        private readonly PaymentRefundGateway $refundGateway,
    ) {}

    public function handle(
        string $disputeId,
        string $auctionId,
        DisputeResolutionOutcome $outcome,
        ?Money $refundAmount,
        string $reason,
    ): void {
        if ($outcome !== DisputeResolutionOutcome::RefundToBuyer && $outcome !== DisputeResolutionOutcome::Split) {
            return;
        }

        if ($refundAmount === null) {
            // Defensive only — Dispute::resolve() already guarantees
            // RefundToBuyer/Split never reach DisputeResolved without an
            // amount (ADR-021 §6/InvalidDisputeResolution). This should
            // be unreachable in practice.
            return;
        }

        $this->refundGateway->refund($auctionId, $disputeId, $refundAmount, $reason);
    }
}
