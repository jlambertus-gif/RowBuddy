<?php

declare(strict_types=1);

namespace RowBuddy\Disputes\ValueObjects;

use DateTimeImmutable;
use RowBuddy\Disputes\Contracts\TransferCaseLookup;

/**
 * A decoupled, Disputes-owned copy of `Transfer`'s evidence shape
 * (`TransferEvidenceRecord`) — `packages/Disputes` reads this via
 * {@see TransferCaseLookup} and never
 * touches `RowBuddy\Transfers\ValueObjects\TransferEvidenceType` or
 * `TransferEvidenceRecord` directly (Phase 6 architecture review §4/§13:
 * "packages/Disputes gains no dependency on packages/Transfers'
 * internals"). `type` is a plain string, not an enum shared with
 * Transfers, for the same reason.
 */
final class TransferEvidenceSummary
{
    public function __construct(
        public readonly string $type,
        public readonly string $storageReference,
        public readonly string $submittedBy,
        public readonly DateTimeImmutable $submittedAt,
    ) {}
}
