<?php

declare(strict_types=1);

namespace RowBuddy\Transfers\ValueObjects;

use DateTimeImmutable;

/**
 * A single, first-class piece of supplementary evidence attached to a
 * `Transfer` (ADR-020 §4) — distinct from the confirmation `GeoPoint`s,
 * which are core confirmation facts, not evidence (ADR-020 §5).
 * `storageReference` is an opaque handle into whatever storage backs it;
 * this value object never knows about disks, EXIF, or signed URLs.
 */
final class TransferEvidenceRecord
{
    public function __construct(
        public readonly TransferEvidenceType $type,
        public readonly string $storageReference,
        public readonly string $submittedBy,
        public readonly DateTimeImmutable $submittedAt,
    ) {}
}
