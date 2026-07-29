<?php

declare(strict_types=1);

namespace RowBuddy\Transfers\Contracts;

use RowBuddy\SharedKernel\Exceptions\NotFoundException;
use RowBuddy\SharedKernel\ValueObjects\Geofence;

/**
 * Transfers-owned port supplying the geofence to cross-check handoff
 * confirmations against (ADR-020 §1) — mirrors `QueueGeofenceLookup`'s
 * (QueuePresence-owned) and `SellerPresenceVerification`'s (Auctions-
 * owned) shape: each consuming module gets its own copy of this kind of
 * port rather than sharing one. Implemented by an apps/web adapter
 * bridging Auctions (to resolve the queue) and Queues (for its geofence)
 * — packages/Transfers gains no dependency on either package's internals.
 */
interface TransferGeofenceLookup
{
    /**
     * @throws NotFoundException if the auction or its queue cannot be
     *                           resolved
     */
    public function geofenceForAuction(string $auctionId): Geofence;
}
