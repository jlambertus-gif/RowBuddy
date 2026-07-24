<?php

declare(strict_types=1);

namespace RowBuddy\Queues\Infrastructure\PostGIS;

use Illuminate\Support\Collection;
use RowBuddy\Queues\Contracts\QueueDiscoveryRepository;
use RowBuddy\Queues\Infrastructure\Eloquent\QueueModel;
use RowBuddy\Queues\Infrastructure\Eloquent\QueueModelMapper;
use RowBuddy\Queues\Queue;
use RowBuddy\Queues\ValueObjects\CoverageArea;
use RowBuddy\Queues\ValueObjects\LinearRing;
use RowBuddy\Queues\ValueObjects\Polygon;
use RowBuddy\Queues\ValueObjects\QueueStatus;
use RowBuddy\SharedKernel\ValueObjects\GeoPoint;

/**
 * The only class in the Queues module allowed to know PostGIS exists
 * (ADR-007). Converts {@see CoverageArea} to/from Well-Known Text and
 * issues the `ST_*` calls directly — nothing above this class (domain,
 * application, or the {@see QueueDiscoveryRepository} port it
 * implements) ever sees a PostGIS function name or a WKT string.
 */
final class PostGISQueueDiscoveryRepository implements QueueDiscoveryRepository
{
    public function __construct(private readonly QueueModelMapper $mapper = new QueueModelMapper) {}

    public function defineCoverageArea(string $queueId, CoverageArea $coverageArea): void
    {
        QueueModel::query()->getConnection()->statement(
            'UPDATE queues SET coverage_area = ST_GeomFromText(?, 4326) WHERE id = ?',
            [$this->toWkt($coverageArea), $queueId],
        );
    }

    public function discoverByLocation(GeoPoint $point): array
    {
        /** @var Collection<int, QueueModel> $models */
        $models = QueueModel::query()
            ->where('status', QueueStatus::Published->value)
            ->whereNotNull('coverage_area')
            ->whereRaw(
                'ST_Contains(coverage_area, ST_SetSRID(ST_MakePoint(?, ?), 4326))',
                [$point->longitude, $point->latitude],
            )
            ->get();

        return $models
            ->map(fn (QueueModel $model): Queue => $this->mapper->toDomain($model))
            ->values()
            ->all();
    }

    private function toWkt(CoverageArea $coverageArea): string
    {
        $polygons = array_map(
            fn (Polygon $polygon): string => '('.implode(', ', array_map(
                $this->ringToWkt(...),
                [$polygon->exteriorRing, ...$polygon->interiorRings],
            )).')',
            $coverageArea->polygons,
        );

        return 'MULTIPOLYGON('.implode(', ', $polygons).')';
    }

    private function ringToWkt(LinearRing $ring): string
    {
        $points = array_map(
            static fn (GeoPoint $point): string => "{$point->longitude} {$point->latitude}",
            $ring->points,
        );

        return '('.implode(', ', $points).')';
    }
}
