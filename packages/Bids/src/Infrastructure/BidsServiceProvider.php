<?php

declare(strict_types=1);

namespace RowBuddy\Bids\Infrastructure;

use Illuminate\Support\ServiceProvider;
use RowBuddy\Bids\Contracts\BidRepository;
use RowBuddy\Bids\Contracts\DomainEventPublisher;
use RowBuddy\Bids\Infrastructure\Eloquent\EloquentBidRepository;
use RowBuddy\Bids\Infrastructure\Events\LaravelDomainEventPublisher;

final class BidsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(BidRepository::class, EloquentBidRepository::class);
        $this->app->bind(DomainEventPublisher::class, LaravelDomainEventPublisher::class);

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
