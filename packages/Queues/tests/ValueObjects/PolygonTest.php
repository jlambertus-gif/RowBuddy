<?php

declare(strict_types=1);

use RowBuddy\Queues\ValueObjects\LinearRing;
use RowBuddy\Queues\ValueObjects\Polygon;
use RowBuddy\SharedKernel\ValueObjects\GeoPoint;

it('accepts an exterior ring with no holes', function () {
    $exterior = new LinearRing(aSquareRingPoints());

    $polygon = new Polygon($exterior);

    expect($polygon->exteriorRing)->toBe($exterior)
        ->and($polygon->interiorRings)->toBe([]);
});

it('accepts an exterior ring plus interior holes', function () {
    $exterior = new LinearRing(aSquareRingPoints());
    $hole = new LinearRing([
        new GeoPoint(0.25, 0.25),
        new GeoPoint(0.25, 0.75),
        new GeoPoint(0.75, 0.75),
        new GeoPoint(0.25, 0.25),
    ]);

    $polygon = new Polygon($exterior, [$hole]);

    expect($polygon->interiorRings)->toHaveCount(1)
        ->and($polygon->interiorRings[0])->toBe($hole);
});
