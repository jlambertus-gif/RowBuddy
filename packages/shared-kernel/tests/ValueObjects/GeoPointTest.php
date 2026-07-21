<?php

declare(strict_types=1);

use RowBuddy\SharedKernel\Exceptions\ValidationException;
use RowBuddy\SharedKernel\ValueObjects\GeoPoint;

it('rejects an out-of-range latitude', function () {
    new GeoPoint(90.1, 0.0);
})->throws(ValidationException::class);

it('rejects an out-of-range longitude', function () {
    new GeoPoint(0.0, -180.1);
})->throws(ValidationException::class);

it('computes zero distance to itself', function () {
    $point = new GeoPoint(32.7157, -117.1611);

    expect($point->distanceInMetersTo($point))->toBeGreaterThanOrEqual(0.0)
        ->and(round($point->distanceInMetersTo($point)))->toBe(0.0);
});

it('computes a plausible distance between two known points', function () {
    // San Diego, CA -> Los Angeles, CA is roughly 180km.
    $sanDiego = new GeoPoint(32.7157, -117.1611);
    $losAngeles = new GeoPoint(34.0522, -118.2437);

    $distanceKm = $sanDiego->distanceInMetersTo($losAngeles) / 1000;

    expect($distanceKm)->toBeGreaterThan(150)->toBeLessThan(210);
});
