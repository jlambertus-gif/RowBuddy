<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use RowBuddy\Queues\Infrastructure\Eloquent\QueueModel;
use RowBuddy\Queues\Infrastructure\PostGIS\PostGISQueueDiscoveryRepository;

use function RowBuddy\Queues\Tests\Support\bootPostgisTestConnection;
use function RowBuddy\Queues\Tests\Support\rollbackPostgisTestConnection;

use RowBuddy\Queues\ValueObjects\CoverageArea;
use RowBuddy\Queues\ValueObjects\LinearRing;
use RowBuddy\Queues\ValueObjects\Polygon;
use RowBuddy\Queues\ValueObjects\QueueStatus;
use RowBuddy\SharedKernel\ValueObjects\GeoPoint;

/**
 * Real PostgreSQL/PostGIS integration test (ADR-007 §5/§7). SQLite has no
 * spatial support at all, so — unlike every other repository test in
 * this package — this one cannot run against an in-memory database.
 * Connection/schema bootstrap lives in tests/Support/PostgisTestConnection.php,
 * shared with the end-to-end test in tests/Integration/.
 */
beforeEach(fn () => bootPostgisTestConnection($this));

afterEach(fn () => rollbackPostgisTestConnection());

function aStoredQueueForDiscovery(string $id, QueueStatus $status): QueueModel
{
    return QueueModel::query()->create([
        'id' => $id,
        'category' => 'concert',
        'jurisdiction_country' => 'US',
        'center_latitude' => 0,
        'center_longitude' => 0,
        'radius_meters' => 100,
        'authorship' => 'admin_curated',
        'organizer_reference' => 'venue-1',
        'status' => $status->value,
    ]);
}

function aSquareCoverageArea(float $minLng, float $minLat, float $maxLng, float $maxLat): CoverageArea
{
    return new CoverageArea([
        new Polygon(new LinearRing([
            new GeoPoint($minLat, $minLng),
            new GeoPoint($minLat, $maxLng),
            new GeoPoint($maxLat, $maxLng),
            new GeoPoint($maxLat, $minLng),
            new GeoPoint($minLat, $minLng),
        ])),
    ]);
}

it('finds a published queue whose coverage area contains the given point', function () {
    aStoredQueueForDiscovery('11111111-1111-1111-1111-111111111111', QueueStatus::Published);

    $repository = new PostGISQueueDiscoveryRepository;
    $repository->defineCoverageArea(
        '11111111-1111-1111-1111-111111111111',
        aSquareCoverageArea(0.0, 0.0, 1.0, 1.0),
    );

    $found = $repository->discoverByLocation(new GeoPoint(0.5, 0.5));

    expect($found)->toHaveCount(1)
        ->and($found[0]->id)->toBe('11111111-1111-1111-1111-111111111111');
});

it('does not return a published queue whose coverage area does not contain the point', function () {
    aStoredQueueForDiscovery('22222222-2222-2222-2222-222222222222', QueueStatus::Published);

    $repository = new PostGISQueueDiscoveryRepository;
    $repository->defineCoverageArea(
        '22222222-2222-2222-2222-222222222222',
        aSquareCoverageArea(10.0, 10.0, 11.0, 11.0),
    );

    $found = $repository->discoverByLocation(new GeoPoint(0.5, 0.5));

    expect($found)->toBe([]);
});

it('never returns a non-published queue, even with matching coverage', function () {
    aStoredQueueForDiscovery('33333333-3333-3333-3333-333333333333', QueueStatus::Pending);

    $repository = new PostGISQueueDiscoveryRepository;
    $repository->defineCoverageArea(
        '33333333-3333-3333-3333-333333333333',
        aSquareCoverageArea(0.0, 0.0, 1.0, 1.0),
    );

    $found = $repository->discoverByLocation(new GeoPoint(0.5, 0.5));

    expect($found)->toBe([]);
});

it('does not return a published queue with no coverage area defined at all', function () {
    aStoredQueueForDiscovery('44444444-4444-4444-4444-444444444444', QueueStatus::Published);

    $repository = new PostGISQueueDiscoveryRepository;

    $found = $repository->discoverByLocation(new GeoPoint(0.5, 0.5));

    expect($found)->toBe([]);
});

it('has a GIST index on the coverage_area column', function () {
    $indexes = Capsule::connection()->select(
        "SELECT indexdef FROM pg_indexes WHERE tablename = 'queues' AND indexname = 'queues_coverage_area_gist'"
    );

    expect($indexes)->toHaveCount(1)
        ->and(strtolower((string) $indexes[0]->indexdef))->toContain('using gist');
});
