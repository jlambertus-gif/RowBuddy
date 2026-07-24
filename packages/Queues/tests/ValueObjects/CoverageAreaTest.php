<?php

declare(strict_types=1);

use RowBuddy\Queues\ValueObjects\CoverageArea;
use RowBuddy\Queues\ValueObjects\LinearRing;
use RowBuddy\Queues\ValueObjects\Polygon;
use RowBuddy\SharedKernel\Exceptions\ValidationException;
use RowBuddy\SharedKernel\ValueObjects\GeoPoint;

it('accepts one or more polygons', function () {
    $polygon = new Polygon(new LinearRing(aSquareRingPoints()));

    $coverageArea = new CoverageArea([$polygon]);

    expect($coverageArea->polygons)->toHaveCount(1)
        ->and($coverageArea->polygons[0])->toBe($polygon);
});

it('rejects an empty polygon list', function () {
    new CoverageArea([]);
})->throws(ValidationException::class);

it('approximates a circle as a single closed polygon', function () {
    $center = new GeoPoint(32.7157, -117.1611);

    $coverageArea = CoverageArea::approximatingCircle($center, 500.0, 16);

    expect($coverageArea->polygons)->toHaveCount(1);

    $ring = $coverageArea->polygons[0]->exteriorRing;
    expect($ring->points)->toHaveCount(17) // 16 distinct vertices + closing point
        ->and($ring->points[0]->equals($ring->points[16]))->toBeTrue();
});

it('places every vertex approximately at the given radius from the center', function () {
    $center = new GeoPoint(32.7157, -117.1611);
    $radius = 500.0;

    $coverageArea = CoverageArea::approximatingCircle($center, $radius, 32);

    $ring = $coverageArea->polygons[0]->exteriorRing;

    foreach (array_slice($ring->points, 0, 32) as $vertex) {
        expect($center->distanceInMetersTo($vertex))->toEqualWithDelta($radius, 1.0);
    }
});

it('rejects fewer than 3 segments', function () {
    CoverageArea::approximatingCircle(new GeoPoint(0, 0), 500.0, 2);
})->throws(ValidationException::class);
