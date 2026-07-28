<?php

declare(strict_types=1);

namespace RowBuddy\Auctions\Infrastructure;

use Illuminate\Support\ServiceProvider;
use RowBuddy\Auctions\Application\FixedAntiSnipingPolicy;
use RowBuddy\Auctions\Application\FixedAuctionDurationPolicy;
use RowBuddy\Auctions\Contracts\AntiSnipingPolicy;
use RowBuddy\Auctions\Contracts\AuctionDurationPolicy;
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

        // Provisional MVP configuration values (ADR-013 §1/§2), not
        // permanent domain invariants — isolated in exactly this one
        // binding each, swappable without touching Auction, AuctionService,
        // or any test that doesn't specifically test duration/anti-sniping.
        $this->app->bind(AuctionDurationPolicy::class, fn () => new FixedAuctionDurationPolicy(30 * 60));
        $this->app->bind(AntiSnipingPolicy::class, fn () => new FixedAntiSnipingPolicy(2 * 60, 2 * 60));

        // SellerPresenceVerification and WinningBidLookup are deliberately
        // NOT bound here: their only implementations bridge to
        // QueuePresence/Bids respectively (ADR-009 §1, ADR-013 §5), so
        // they are bound at apps/web's composition root instead, the same
        // reasoning as QueuePresence's own EvidenceStorage/QueueGeofenceLookup.
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }
}
