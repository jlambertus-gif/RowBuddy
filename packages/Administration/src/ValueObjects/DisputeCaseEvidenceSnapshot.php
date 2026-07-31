<?php

declare(strict_types=1);

namespace RowBuddy\Administration\ValueObjects;

use DateTimeImmutable;

/**
 * One evidence record on a dispute, as Administration's review surface
 * needs it (ADR-026 §3) — primitives only, mirroring every other
 * cross-module snapshot in this codebase (e.g. Disputes' own
 * `TransferEvidenceSummary`): `$type` is Disputes' `DisputeEvidenceType`
 * value, not the enum itself, so this package never depends on
 * `packages/Disputes`.
 */
final class DisputeCaseEvidenceSnapshot
{
    public function __construct(
        public readonly string $type,
        public readonly string $storageReference,
        public readonly string $submittedBy,
        public readonly DateTimeImmutable $submittedAt,
    ) {}
}
