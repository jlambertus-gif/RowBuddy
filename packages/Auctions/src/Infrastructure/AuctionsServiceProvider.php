<?php

declare(strict_types=1);

namespace RowBuddy\Auctions\Infrastructure;

use Illuminate\Support\ServiceProvider;
use RowBuddy\Auctions\Contracts\AuctionRepository;
use RowBuddy\Auctions\Contracts\DomainEventPublisher;
use RowBuddy\Auctions\Infrastructure\Eloquent\EloquentAuctionRepository;
use RowBuddy\Auctions\Infrastructure\Events\LaravelDomainEventPublisher;

final class AuctionsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AuctionRepository::class, EloquentAuctionRepository::class);
        $this->app->bind(DomainEventPublisher::class, LaravelDomainEventPublisher::class);

        // SellerPresenceVerification is deliberately NOT bound here: its
        // only implementation bridges to QueuePresence (ADR-009 §1), so it
        // is bound at apps/web's composition root instead, the same
        // reasoning as QueuePresence's own EvidenceStorage/QueueGeofenceLookup.
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }
}
