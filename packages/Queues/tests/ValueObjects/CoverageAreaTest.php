<?php

declare(strict_types=1);

use RowBuddy\Queues\ValueObjects\CoverageArea;
use RowBuddy\Queues\ValueObjects\LinearRing;
use RowBuddy\Queues\ValueObjects\Polygon;
use RowBuddy\SharedKernel\Exceptions\ValidationException;

it('accepts one or more polygons', function () {
    $polygon = new Polygon(new LinearRing(aSquareRingPoints()));

    $coverageArea = new CoverageArea([$polygon]);

    expect($coverageArea->polygons)->toHaveCount(1)
        ->and($coverageArea->polygons[0])->toBe($polygon);
});

it('rejects an empty polygon list', function () {
    new CoverageArea([]);
})->throws(ValidationException::class);
