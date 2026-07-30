<?php

declare(strict_types=1);

namespace RowBuddy\Notifications\Infrastructure;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use RowBuddy\Auctions\Events\AuctionWon;
use RowBuddy\Notifications\Contracts\NotificationDeliveryLedger;
use RowBuddy\Notifications\Infrastructure\Eloquent\EloquentNotificationDeliveryLedger;
use RowBuddy\Notifications\Listeners\SendAuctionWonNotification;

final class NotificationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(NotificationDeliveryLedger::class, EloquentNotificationDeliveryLedger::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        // This module's own reactions, registered against real,
        // committed domain events — this codebase's first genuine
        // Laravel Event::listen() wiring (ADR-025 §Consequences).
        Event::listen(AuctionWon::class, SendAuctionWonNotification::class);
    }
}
