<?php

declare(strict_types=1);

namespace RowBuddy\Auctions\Contracts;

/**
 * Decides how long a newly opened auction should run (ADR-013 §1).
 * `Auction::open()` never knows or derives this value — only
 * `AuctionService::open()` calls this policy, computes the resulting
 * `closesAt`, and hands that explicit value to the aggregate.
 *
 * Deliberately accepts `$queueId` even though the MVP implementation
 * ignores it, so a future policy deriving duration from event/queue
 * metadata can replace the binding without changing this interface or
 * the aggregate's contract.
 */
interface AuctionDurationPolicy
{
    public function durationInSecondsFor(string $queueId): int;
}
