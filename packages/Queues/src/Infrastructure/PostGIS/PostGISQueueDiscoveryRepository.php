<?php

declare(strict_types=1);

namespace RowBuddy\Queues\Infrastructure\PostGIS;

use Illuminate\Database\Connection;
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
 *
 * Both methods no-op harmlessly on any driver other than `pgsql`
 * (ADR-007 §8) — `apps/web`'s own test suite runs against in-memory
 * SQLite (Sprint 4's env fix), and publishing a queue now
 * unconditionally attempts to assign a coverage area
 * (`CoverageAreaAssigner`), so every existing Feature test for
 * submission/moderation would otherwise break the moment this class is
 * exercised outside a real PostGIS connection.
 */
final class PostGISQueueDiscoveryRepository implements QueueDiscoveryRepository
{
    public function __construct(private readonly QueueModelMapper $mapper = new QueueModelMapper) {}

    public function defineCoverageArea(string $queueId, CoverageArea $coverageArea): void
    {
        $connection = $this->postgresConnectionOrNull();

        if ($connection === null) {
            return;
        }

        $connection->statement(
            'UPDATE queues SET coverage_area = ST_GeomFromText(?, 4326) WHERE id = ?',
            [$this->toWkt($coverageArea), $queueId],
        );
    }

    public function discoverByLocation(GeoPoint $point): array
    {
        if ($this->postgresConnectionOrNull() === null) {
            return [];
        }

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

    private function postgresConnectionOrNull(): ?Connection
    {
        $connection = QueueModel::query()->getConnection();

        if (! $connection instanceof Connection || $connection->getDriverName() !== 'pgsql') {
            return null;
        }

        return $connection;
    }
}
