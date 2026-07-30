<?php

declare(strict_types=1);

namespace RowBuddy\Ratings\Contracts;

use RowBuddy\Ratings\ValueObjects\TransferParticipantSnapshot;

/**
 * Ratings-owned read port into Transfers (ADR-024 §2/§7/Consequences),
 * extending the "consumer owns the port" pattern a fifth hop —
 * mirroring Disputes' `TransferCaseLookup` shape exactly, adapted to
 * Ratings' narrower needs. `packages/Ratings` reads a `Transfer`'s
 * eligibility-relevant facts read-only and gains no dependency on
 * `packages/Transfers`' internals. Implemented by an `apps/web` adapter
 * bridging to `TransferRepository`.
 */
interface TransferParticipantLookup
{
    public function findByTransferId(string $transferId): ?TransferParticipantSnapshot;
}
