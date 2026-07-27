<?php

declare(strict_types=1);

namespace RowBuddy\QueuePresence\ValueObjects;

use DateTimeImmutable;
use RowBuddy\QueuePresence\Contracts\EvidenceStorage;

/**
 * A single recorded evidence photo. `storageReference` is opaque outside
 * whatever {@see EvidenceStorage}
 * adapter produced it — never a public URL, never assumed to be a
 * filesystem path.
 */
final class EvidencePhotoRecord
{
    public function __construct(
        public readonly string $id,
        public readonly string $presenceSessionId,
        public readonly string $storageReference,
        public readonly string $mimeType,
        public readonly int $sizeBytes,
        public readonly DateTimeImmutable $recordedAt,
    ) {}
}
