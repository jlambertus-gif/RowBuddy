<?php

declare(strict_types=1);

namespace RowBuddy\QueuePresence\Contracts;

use RowBuddy\QueuePresence\ValueObjects\EvidencePhotoRecord;

/**
 * Domain-facing persistence port for recorded evidence photos. Unlike
 * {@see GpsPingRepository} (write-only for now), a read method is needed
 * this sprint: retrieving a signed URL for a specific photo requires
 * looking it up by id.
 */
interface EvidencePhotoRepository
{
    public function record(EvidencePhotoRecord $photo): void;

    public function findById(string $id): ?EvidencePhotoRecord;
}
