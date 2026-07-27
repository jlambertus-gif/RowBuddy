<?php

declare(strict_types=1);

namespace RowBuddy\QueuePresence\Infrastructure\Eloquent;

use RowBuddy\QueuePresence\Contracts\GpsPingRepository;
use RowBuddy\QueuePresence\ValueObjects\GpsPingRecord;

final class EloquentGpsPingRepository implements GpsPingRepository
{
    public function record(GpsPingRecord $ping): void
    {
        GpsPingModel::query()->create([
            'id' => $ping->id,
            'presence_session_id' => $ping->presenceSessionId,
            'latitude' => $ping->location->latitude,
            'longitude' => $ping->location->longitude,
            'accuracy_meters' => $ping->accuracyInMeters,
            'within_geofence' => $ping->withinGeofence,
            'recorded_at' => $ping->recordedAt,
        ]);
    }

    public function bestAccuracyWithinGeofence(string $presenceSessionId): ?float
    {
        $value = GpsPingModel::query()
            ->where('presence_session_id', $presenceSessionId)
            ->where('within_geofence', true)
            ->min('accuracy_meters');

        return $value !== null ? (float) $value : null;
    }
}
