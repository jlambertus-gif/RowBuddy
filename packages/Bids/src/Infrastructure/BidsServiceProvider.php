<?php

declare(strict_types=1);

namespace RowBuddy\Bids\Infrastructure;

use Illuminate\Support\ServiceProvider;
use RowBuddy\Bids\Contracts\BidPlacementLedger;
use RowBuddy\Bids\Contracts\BidRepository;
use RowBuddy\Bids\Contracts\DomainEventPublisher;
use RowBuddy\Bids\Infrastructure\Eloquent\EloquentBidPlacementLedger;
use RowBuddy\Bids\Infrastructure\Eloquent\EloquentBidRepository;
use RowBuddy\Bids\Infrastructure\Events\LaravelDomainEventPublisher;

final class BidsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(BidRepository::class, EloquentBidRepository::class);
        $this->app->bind(DomainEventPublisher::class, LaravelDomainEventPublisher::class);

        // The HTTP-retry idempotency ledger (Phase 9, ADR-027 Architecture
        // Refinements §2) is entirely internal to this package, unlike
        // AuctionGateway/TransactionManager below — no cross-module
        // bridging needed, so it is bound here rather than at apps/web's
        // composition root.
        $this->app->bind(BidPlacementLedger::class, EloquentBidPlacementLedger::class);

        // AuctionGateway and TransactionManager are deliberately NOT bound
        // here: AuctionGateway bridges to Auctions (cross-module, apps/web
        // composition root, same reasoning as QueuePresence's
        // EvidenceStorage/QueueGeofenceLookup), and TransactionManager
        // wraps Laravel's DB::transaction() — also composition-root
        // territory, not something this package's own standalone tests
        // ever need to boot.
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }
}
