<?php

declare(strict_types=1);

namespace RowBuddy\QueuePresence\Infrastructure\Eloquent;

use RowBuddy\QueuePresence\Contracts\EvidencePhotoRepository;
use RowBuddy\QueuePresence\ValueObjects\EvidencePhotoRecord;

final class EloquentEvidencePhotoRepository implements EvidencePhotoRepository
{
    public function record(EvidencePhotoRecord $photo): void
    {
        EvidencePhotoModel::query()->create([
            'id' => $photo->id,
            'presence_session_id' => $photo->presenceSessionId,
            'storage_reference' => $photo->storageReference,
            'mime_type' => $photo->mimeType,
            'size_bytes' => $photo->sizeBytes,
            'recorded_at' => $photo->recordedAt,
        ]);
    }

    public function findById(string $id): ?EvidencePhotoRecord
    {
        $model = EvidencePhotoModel::query()->find($id);

        if (! $model instanceof EvidencePhotoModel) {
            return null;
        }

        return new EvidencePhotoRecord(
            $model->id,
            $model->presence_session_id,
            $model->storage_reference,
            $model->mime_type,
            $model->size_bytes,
            $model->recorded_at->toDateTimeImmutable(),
        );
    }
}
