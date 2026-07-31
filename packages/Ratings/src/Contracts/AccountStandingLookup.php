<?php

declare(strict_types=1);

namespace RowBuddy\Ratings\Contracts;

/**
 * Ratings-owned read port into Administration (ADR-026 §4/Sprint 2) —
 * extending the "consumer owns the port" pattern: `packages/Ratings`
 * reads whether a rater's account is currently suspended read-only and
 * gains no dependency on Administration's internals. Deliberately
 * independent of Bids' and Queues' identically-shaped ports of the same
 * name. Implemented by an `apps/web` adapter bridging to
 * `AccountStandingRepository`.
 */
interface AccountStandingLookup
{
    public function isSuspended(string $userId): bool;
}
