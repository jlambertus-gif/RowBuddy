<?php

declare(strict_types=1);

namespace RowBuddy\Disputes\ValueObjects;

/**
 * `Dispute`'s own evidence concept — distinct from `Transfer`'s
 * (ADR-020 §4), which `Dispute` reads read-only rather than duplicates
 * (Phase 6 architecture review §4/§13). A deliberately extensible enum,
 * mirroring `TransferEvidenceType`'s own shape, since a dispute case
 * plausibly grows more submission types later without redesigning the
 * aggregate.
 */
enum DisputeEvidenceType: string
{
    case Photo = 'photo';
    case WrittenStatement = 'written_statement';
}
