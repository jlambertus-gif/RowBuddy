<?php

declare(strict_types=1);

namespace RowBuddy\Administration\Contracts;

use RowBuddy\Administration\ValueObjects\DisputeCaseSnapshot;

/**
 * Administration's own consumer-owned read port onto Disputes' data
 * (ADR-026 §3) — mirrors the "consumer owns the port" discipline already
 * proven across this codebase (e.g. Ratings'/Notifications' own copies
 * of TransferParticipantLookup). An apps/web adapter bridges to
 * `RowBuddy\Disputes\Contracts\DisputeRepository`; `packages/Administration`
 * never depends on `packages/Disputes` directly. Read-only: nothing here
 * can mutate a Dispute or select its resolution outcome.
 */
interface DisputeCaseLookup
{
    /**
     * Every dispute, most recently opened first.
     *
     * @return list<DisputeCaseSnapshot>
     */
    public function listAll(): array;

    public function findById(string $disputeId): ?DisputeCaseSnapshot;
}
