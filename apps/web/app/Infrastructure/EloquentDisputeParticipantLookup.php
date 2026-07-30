<?php

declare(strict_types=1);

namespace App\Infrastructure;

use RowBuddy\Disputes\Contracts\DisputeRepository;
use RowBuddy\Notifications\Contracts\DisputeParticipantLookup;
use RowBuddy\Notifications\ValueObjects\DisputeParticipantSnapshot;

/**
 * Bridges Notifications' read-only {@see DisputeParticipantLookup} port
 * to Disputes' {@see DisputeRepository} — the one place allowed to know
 * both packages' internals, per the composition-root pattern every
 * prior cross-module read port in this codebase already uses.
 */
final class EloquentDisputeParticipantLookup implements DisputeParticipantLookup
{
    public function __construct(
        private readonly DisputeRepository $disputes,
    ) {}

    public function findByDisputeId(string $disputeId): ?DisputeParticipantSnapshot
    {
        $dispute = $this->disputes->findById($disputeId);

        if ($dispute === null) {
            return null;
        }

        return new DisputeParticipantSnapshot(
            disputeId: $dispute->id,
            buyerId: $dispute->buyerId,
            sellerId: $dispute->sellerId,
        );
    }
}
