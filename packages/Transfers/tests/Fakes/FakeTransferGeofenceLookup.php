<?php

declare(strict_types=1);

namespace RowBuddy\Transfers\Tests\Fakes;

use RowBuddy\SharedKernel\ValueObjects\Geofence;
use RowBuddy\SharedKernel\ValueObjects\GeoPoint;
use RowBuddy\Transfers\Contracts\TransferGeofenceLookup;

final class FakeTransferGeofenceLookup implements TransferGeofenceLookup
{
    public Geofence $geofence;

    public function __construct()
    {
        $this->geofence = new Geofence(new GeoPoint(32.7157, -117.1611), 100.0);
    }

    public function geofenceForAuction(string $auctionId): Geofence
    {
        return $this->geofence;
    }
}
