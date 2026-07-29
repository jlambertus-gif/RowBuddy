<?php

declare(strict_types=1);

namespace RowBuddy\Transfers\Contracts;

/**
 * Decides how long an issued transfer's confirmation window should run
 * (ADR-018 §1). `Transfer::issue()` never knows or derives this value —
 * only `TransferInitiationService` calls this policy, computes the
 * resulting `expiresAt`, and hands that explicit value to the aggregate,
 * the same discipline `AuctionDurationPolicy` established for `closesAt`
 * (ADR-013 §1).
 *
 * Deliberately accepts `$auctionId` even though the MVP implementation
 * ignores it, so a future policy deriving the window from queue/event
 * metadata can replace the binding without changing this interface or
 * the aggregate's contract.
 */
interface TransferWindowPolicy
{
    public function durationInSecondsFor(string $auctionId): int;
}
