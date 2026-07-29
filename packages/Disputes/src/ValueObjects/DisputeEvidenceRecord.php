<?php

declare(strict_types=1);

namespace RowBuddy\Disputes\ValueObjects;

use DateTimeImmutable;

/**
 * Mirrors `TransferEvidenceRecord`'s exact shape. `submittedBy` is
 * whichever party attached it — the filing buyer or the respondent
 * seller (ADR-021 §1) — this record itself carries no notion of
 * "buyer's evidence" vs. "seller's evidence" beyond that id, the same
 * way `TransferEvidenceRecord` doesn't distinguish seller/buyer evidence
 * by anything other than `submittedBy`.
 */
final class DisputeEvidenceRecord
{
    public function __construct(
        public readonly DisputeEvidenceType $type,
        public readonly string $storageReference,
        public readonly string $submittedBy,
        public readonly DateTimeImmutable $submittedAt,
    ) {}
}
