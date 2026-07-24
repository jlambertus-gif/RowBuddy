<?php

declare(strict_types=1);

namespace RowBuddy\Queues\Infrastructure\Eloquent;

use RowBuddy\Queues\Contracts\QueueRepository;
use RowBuddy\Queues\Queue;
use RowBuddy\Queues\ValueObjects\QueueStatus;

/**
 * Translates between the {@see QueueModel} Eloquent record and the
 * {@see Queue} domain aggregate (via {@see QueueModelMapper}). This is
 * the only place in the Queues module allowed to write a Queue back to
 * storage.
 */
final class EloquentQueueRepository implements QueueRepository
{
    public function __construct(private readonly QueueModelMapper $mapper = new QueueModelMapper) {}

    public function save(Queue $queue): void
    {
        QueueModel::query()->updateOrCreate(
            ['id' => $queue->id],
            [
                'category' => $queue->category,
                'jurisdiction_country' => $queue->jurisdictionCountry,
                'center_latitude' => $queue->geofence->center->latitude,
                'center_longitude' => $queue->geofence->center->longitude,
                'radius_meters' => $queue->geofence->radiusInMeters,
                'authorship' => $queue->authorship->value,
                'organizer_reference' => $queue->organizerReference,
                'status' => $queue->status()->value,
            ],
        );
    }

    public function findById(string $id): ?Queue
    {
        $model = QueueModel::query()->find($id);

        if (! $model instanceof QueueModel) {
            return null;
        }

        return $this->mapper->toDomain($model);
    }

    public function findByStatus(QueueStatus $status): array
    {
        return QueueModel::query()
            ->where('status', $status->value)
            ->get()
            ->map(fn (QueueModel $model): Queue => $this->mapper->toDomain($model))
            ->values()
            ->all();
    }
}
