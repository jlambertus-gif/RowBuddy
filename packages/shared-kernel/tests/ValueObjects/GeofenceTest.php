<?php

declare(strict_types=1);

use RowBuddy\SharedKernel\Exceptions\ValidationException;
use RowBuddy\SharedKernel\ValueObjects\Geofence;
use RowBuddy\SharedKernel\ValueObjects\GeoPoint;

it('contains a point within its radius', function () {
    $center = new GeoPoint(32.7157, -117.1611);
    $nearby = new GeoPoint(32.7160, -117.1610); // a few meters away

    $fence = new Geofence($center, 500);

    expect($fence->contains($nearby))->toBeTrue();
});

it('does not contain a point outside its radius', function () {
    $center = new GeoPoint(32.7157, -117.1611);
    $farAway = new GeoPoint(34.0522, -118.2437); // Los Angeles

    $fence = new Geofence($center, 500);

    expect($fence->contains($farAway))->toBeFalse();
});

it('rejects a non-positive radius', function () {
    new Geofence(new GeoPoint(0, 0), 0);
})->throws(ValidationException::class);
