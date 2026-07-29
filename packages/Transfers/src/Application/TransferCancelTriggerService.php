<?php

declare(strict_types=1);

namespace RowBuddy\Transfers\Application;

use RowBuddy\Transfers\Contracts\PaymentCaptureGateway;

/**
 * The real `TransferExpired`/`TransferCancelled` consumer (ADR-018 §3/§4,
 * ADR-019) — mirrors `TransferCaptureTriggerService`'s own shape reacting
 * to `TransferConfirmed`: a dedicated service invoked off a committed
 * domain event's payload, never triggered by a caller reading `Transfer`'s
 * own status directly.
 *
 * Covers both events deliberately: `TransferExpired` (window ran out with
 * no confirmation, ADR-018 §4's symmetric no-fault policy — the only path
 * reachable in Sprint 5) and `TransferCancelled` (an explicit default,
 * not yet raised by any Sprint 5 code path, but sharing the identical
 * "no capture, cancel the authorization" consequence) — so this trigger
 * needs no change when a future sprint starts raising the latter.
 *
 * No caller wires this to a real Laravel event listener yet — mirroring
 * every other cross-module reactor in this codebase (delivery-layer work
 * for a later sprint).
 */
final class TransferCancelTriggerService
{
    public function __construct(
        private readonly PaymentCaptureGateway $captureGateway,
    ) {}

    public function handle(string $auctionId, string $reason): void
    {
        $this->captureGateway->cancel($auctionId, $reason);
    }
}
