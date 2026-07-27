<?php

declare(strict_types=1);

namespace RowBuddy\QueuePresence\Tests\Fakes;

use DateTimeImmutable;
use RowBuddy\QueuePresence\Contracts\GpsPingRepository;
use RowBuddy\QueuePresence\ValueObjects\GpsPingRecord;

final class RecordingGpsPingRepository implements GpsPingRepository
{
    /** @var list<GpsPingRecord> */
    public array $recorded = [];

    public function record(GpsPingRecord $ping): void
    {
        $this->recorded[] = $ping;
    }

    public function bestAccuracyWithinGeofence(string $presenceSessionId): ?float
    {
        $accuracies = array_map(
            static fn (GpsPingRecord $ping): float => $ping->accuracyInMeters,
            array_filter(
                $this->recorded,
                static fn (GpsPingRecord $ping): bool => $ping->presenceSessionId === $presenceSessionId && $ping->withinGeofence,
            ),
        );

        return $accuracies === [] ? null : min($accuracies);
    }

    public function latestWithinGeofencePingAt(string $presenceSessionId): ?DateTimeImmutable
    {
        $timestamps = array_map(
            static fn (GpsPingRecord $ping): DateTimeImmutable => $ping->recordedAt,
            array_filter(
                $this->recorded,
                static fn (GpsPingRecord $ping): bool => $ping->presenceSessionId === $presenceSessionId && $ping->withinGeofence,
            ),
        );

        return $timestamps === [] ? null : max($timestamps);
    }
}
