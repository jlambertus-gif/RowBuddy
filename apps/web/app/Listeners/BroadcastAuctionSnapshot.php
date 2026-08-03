<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\AuctionSnapshotBroadcast;
use App\Support\AuctionPublicSnapshotAssembler;
use Illuminate\Contracts\Queue\ShouldQueue;
use RowBuddy\Auctions\Events\AuctionCancelled;
use RowBuddy\Auctions\Events\AuctionClosingDeadlineExtended;
use RowBuddy\Auctions\Events\AuctionClosingStarted;
use RowBuddy\Auctions\Events\AuctionExpired;
use RowBuddy\Auctions\Events\AuctionWon;
use RowBuddy\Bids\Events\BidPlaced;

/**
 * Reacts to every domain event that can change what an auction's public
 * Reverb channel should show (Phase 9, ADR-027 Architecture Refinements
 * §3) — registered against six concrete event classes in
 * AppServiceProvider, the same real Event::listen()-driven pattern
 * Notifications established in Phase 7. Queued (ShouldQueue) so
 * broadcasting never runs on the request thread that just committed the
 * triggering transaction — "after-commit broadcasting" only requires the
 * causal order to hold, not sub-millisecond simultaneity.
 *
 * handle()'s parameter is a union of exactly these six classes, never the
 * generic DomainEvent interface — an earlier version hinted the broader
 * interface and Laravel's event auto-discovery silently wired this
 * listener to every domain event in the codebase (an unrelated
 * AuctionOpened during test setup was enough to trigger a broadcast).
 * Narrowing the hint was not sufficient on its own, though: discovery
 * still auto-registered this listener against each of the six concrete
 * classes individually, stacking on top of the explicit Event::listen()
 * calls in AppServiceProvider and firing every broadcast twice — caught
 * by an assertDispatchedTimes() test. Event auto-discovery is now
 * disabled outright (`bootstrap/app.php`, `withEvents(discover: false)`),
 * so the explicit Event::listen() calls are the only registration path,
 * for this listener and any future one.
 *
 * Every one of the six event classes this listens to already includes
 * `auction_id` in its own payload() — the only thing ever read from the
 * triggering event. The rebroadcast payload itself always comes from
 * {@see AuctionPublicSnapshotAssembler}, never from the event's payload
 * directly.
 */
final class BroadcastAuctionSnapshot implements ShouldQueue
{
    public function __construct(private readonly AuctionPublicSnapshotAssembler $assembler) {}

    public function handle(BidPlaced|AuctionClosingStarted|AuctionClosingDeadlineExtended|AuctionWon|AuctionExpired|AuctionCancelled $event): void
    {
        $auctionId = $event->payload()['auction_id'] ?? null;

        if (! is_string($auctionId)) {
            return;
        }

        $snapshot = $this->assembler->assembleRegardlessOfDiscoverability($auctionId);

        if ($snapshot === null) {
            return;
        }

        AuctionSnapshotBroadcast::dispatch($auctionId, $snapshot);
    }
}
