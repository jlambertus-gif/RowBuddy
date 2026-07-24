<?php

declare(strict_types=1);

namespace RowBuddy\Queues\Infrastructure\Eloquent;

use RowBuddy\Queues\Queue;
use RowBuddy\Queues\ValueObjects\QueueAuthorship;
use RowBuddy\Queues\ValueObjects\QueueStatus;
use RowBuddy\SharedKernel\ValueObjects\Geofence;
use RowBuddy\SharedKernel\ValueObjects\GeoPoint;

/**
 * Translates a {@see QueueModel} Eloquent record into the {@see Queue}
 * domain aggregate. Shared by every repository that reads queue rows
 * (EloquentQueueRepository, PostGISQueueDiscoveryRepository) so this
 * mapping exists in exactly one place — the same duplication risk
 * QueueGateChecker (Sprint 5) was extracted to avoid.
 */
final class QueueModelMapper
{
    public function toDomain(QueueModel $model): Queue
    {
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
