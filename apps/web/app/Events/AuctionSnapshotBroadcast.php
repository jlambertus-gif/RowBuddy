<?php

declare(strict_types=1);

namespace App\Events;

use App\Support\AuctionPublicSnapshotAssembler;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * The only thing ever broadcast on an auction's public Reverb channel
 * (Phase 9, ADR-027 Architecture Refinements §3) — a public, unauthenticated
 * channel, and $snapshot is always produced by
 * {@see AuctionPublicSnapshotAssembler}, never a raw
 * domain-event payload. The initial HTTP response to GET /auctions/{id}
 * remains the source of the current state; this only communicates
 * subsequent changes.
 */
final class AuctionSnapshotBroadcast implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    /**
     * @param  array<string, mixed>  $snapshot
     */
    public function __construct(
        public readonly string $auctionId,
        public readonly array $snapshot,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel("auctions.{$this->auctionId}");
    }

    public function broadcastAs(): string
    {
        return 'snapshot.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->snapshot;
    }
}
