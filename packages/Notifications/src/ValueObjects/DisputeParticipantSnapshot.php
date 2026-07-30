<?php

declare(strict_types=1);

namespace RowBuddy\Notifications\ValueObjects;

/**
 * Everything `packages/Notifications` needs to read from a `Dispute` to
 * resolve `DisputeResolved`'s recipients (ADR-025 §6) — that event
 * carries only `disputeId`, `outcome`, `refundAmount`, `resolvedBy` (an
 * administrator, not a party), `resolutionNotes`, and
 * `evidenceFoundFraudulent`, none of which identify the buyer/seller to
 * notify.
 */
final class DisputeParticipantSnapshot
{
    public function __construct(
        public readonly string $disputeId,
        public readonly string $buyerId,
        public readonly string $sellerId,
    ) {}
}
