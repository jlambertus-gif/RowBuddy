<?php

declare(strict_types=1);

namespace RowBuddy\Notifications\Contracts;

use RowBuddy\Notifications\ValueObjects\DisputeParticipantSnapshot;

/**
 * Notifications-owned read port into Disputes, extending the
 * "consumer owns the port" pattern — `DisputeResolved` (ADR-025 §6)
 * carries only `disputeId` and resolution facts, not the dispute's
 * buyer/seller, so resolving its recipients needs this one hop.
 * Implemented by an `apps/web` adapter bridging to `DisputeRepository`.
 */
interface DisputeParticipantLookup
{
    public function findByDisputeId(string $disputeId): ?DisputeParticipantSnapshot;
}
