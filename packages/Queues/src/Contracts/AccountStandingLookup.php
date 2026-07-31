<?php

declare(strict_types=1);

namespace RowBuddy\Queues\Contracts;

/**
 * Queues-owned read port into Administration (ADR-026 §4/Sprint 2) —
 * extending the "consumer owns the port" pattern: `packages/Queues`
 * reads whether a submitter's account is currently suspended read-only
 * and gains no dependency on Administration's internals. Deliberately
 * independent of Bids' and Ratings' identically-shaped ports of the
 * same name. Implemented by an `apps/web` adapter bridging to
 * `AccountStandingRepository`. Consulted only for user-submitted queues
 * (`submitForApproval()`) — `publishDirectly()` has no submitting user
 * to check.
 */
interface AccountStandingLookup
{
    public function isSuspended(string $userId): bool;
}
