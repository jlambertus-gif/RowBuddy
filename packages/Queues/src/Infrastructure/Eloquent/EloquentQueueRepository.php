<?php

declare(strict_types=1);

namespace RowBuddy\Queues\Infrastructure\Eloquent;

use RowBuddy\Queues\Contracts\QueueRepository;
use RowBuddy\Queues\Queue;
use RowBuddy\Queues\ValueObjects\QueueAuthorship;
use RowBuddy\Queues\ValueObjects\QueueStatus;
use RowBuddy\SharedKernel\ValueObjects\Geofence;
use RowBuddy\SharedKernel\ValueObjects\GeoPoint;

/**
 * Translates between the {@see QueueModel} Eloquent record and the
 * {@see Queue} domain aggregate. This is the only place in the Queues
 * module allowed to know both shapes at once.
 */
final class EloquentQueueRepository implements QueueRepository
{
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

        return Queue::fromPersistence(
            id: $model->id,
            category: $model->category,
            jurisdictionCountry: $model->jurisdiction_country,
            geofence: new Geofence(
                new GeoPoint((float) $model->center_latitude, (float) $model->center_longitude),
                (float) $model->radius_meters,
            ),
            authorship: QueueAuthorship::from($model->authorship),
            organizerReference: $model->organizer_reference,
            status: QueueStatus::from($model->status),
        );
    }
}
