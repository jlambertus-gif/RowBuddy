<?php

declare(strict_types=1);

use RowBuddy\SharedKernel\ValueObjects\GeoPoint;
use RowBuddy\Transfers\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

function geoPoint(float $latitude = 32.7157, float $longitude = -117.1611): GeoPoint
{
    return new GeoPoint($latitude, $longitude);
}
