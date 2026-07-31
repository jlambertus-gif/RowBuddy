<?php

declare(strict_types=1);

namespace RowBuddy\Bids\Contracts;

/**
 * Bids-owned read port into Administration (ADR-026 §4/Sprint 2) —
 * extending the "consumer owns the port" pattern: `packages/Bids` reads
 * whether a bidder's account is currently suspended read-only and gains
 * no dependency on Administration's internals. Deliberately independent
 * of Queues' and Ratings' identically-shaped ports of the same name —
 * the three modules share no dependency on each other or on
 * Administration directly. Implemented by an `apps/web` adapter
 * bridging to `AccountStandingRepository`.
 */
interface AccountStandingLookup
{
    public function isSuspended(string $userId): bool;
}
