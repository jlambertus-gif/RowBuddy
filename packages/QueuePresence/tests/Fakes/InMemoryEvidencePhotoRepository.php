<?php

declare(strict_types=1);

namespace RowBuddy\QueuePresence\Tests\Fakes;

use RowBuddy\QueuePresence\Contracts\EvidencePhotoRepository;
use RowBuddy\QueuePresence\ValueObjects\EvidencePhotoRecord;

final class InMemoryEvidencePhotoRepository implements EvidencePhotoRepository
{
    /** @var array<string, EvidencePhotoRecord> */
    public array $saved = [];

    public function record(EvidencePhotoRecord $photo): void
    {
        $this->saved[$photo->id] = $photo;
    }

    public function findById(string $id): ?EvidencePhotoRecord
    {
        return $this->saved[$id] ?? null;
    }
}
