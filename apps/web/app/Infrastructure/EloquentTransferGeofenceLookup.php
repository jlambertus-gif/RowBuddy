<?php

declare(strict_types=1);

namespace App\Infrastructure;

use RowBuddy\Auctions\Contracts\AuctionRepository;
use RowBuddy\Queues\Contracts\QueueRepository;
use RowBuddy\SharedKernel\Exceptions\NotFoundException;
use RowBuddy\SharedKernel\ValueObjects\Geofence;
use RowBuddy\Transfers\Contracts\TransferGeofenceLookup;

/**
 * Bridges Transfers' read-only {@see TransferGeofenceLookup} port to
 * Auctions' {@see AuctionRepository} (to resolve the queue) and Queues'
 * {@see QueueRepository} (for its geofence) — this class is the one
 * place in the codebase allowed to depend on all three packages at once,
 * because apps/web is the composition root, not any one module itself
 * (see tests/Architecture/ModuleBoundaryTest.php). No package depends on
 * another's internals.
 */
final class EloquentTransferGeofenceLookup implements TransferGeofenceLookup
{
    public function __construct(
        private readonly AuctionRepository $auctions,
        private readonly QueueRepository $queues,
    ) {}

    public function geofenceForAuction(string $auctionId): Geofence
    {
        $auction = $this->auctions->findById($auctionId);

        if ($auction === null) {
            throw new NotFoundException("Auction [{$auctionId}] not found.");
        }

        $queue = $this->queues->findById($auction->queueId);

        if ($queue === null) {
            throw new NotFoundException("Queue [{$auction->queueId}] not found.");
        }

        return $queue->geofence;
    }
}
