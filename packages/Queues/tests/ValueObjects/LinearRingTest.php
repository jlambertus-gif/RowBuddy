<?php

declare(strict_types=1);

use RowBuddy\Queues\ValueObjects\LinearRing;
use RowBuddy\SharedKernel\Exceptions\ValidationException;
use RowBuddy\SharedKernel\ValueObjects\GeoPoint;

function aSquareRingPoints(): array
{
    return [
        new GeoPoint(0.0, 0.0),
        new GeoPoint(0.0, 1.0),
        new GeoPoint(1.0, 1.0),
        new GeoPoint(1.0, 0.0),
        new GeoPoint(0.0, 0.0),
    ];
}

it('accepts a closed ring with at least 4 points', function () {
    $ring = new LinearRing(aSquareRingPoints());

    expect($ring->points)->toHaveCount(5);
});

it('rejects a ring with fewer than 4 points', function () {
    new LinearRing([
        new GeoPoint(0.0, 0.0),
        new GeoPoint(0.0, 1.0),
        new GeoPoint(0.0, 0.0),
    ]);
})->throws(ValidationException::class);

it('rejects a ring whose first and last points differ', function () {
    new LinearRing([
        new GeoPoint(0.0, 0.0),
        new GeoPoint(0.0, 1.0),
        new GeoPoint(1.0, 1.0),
        new GeoPoint(1.0, 0.0),
    ]);
})->throws(ValidationException::class);
